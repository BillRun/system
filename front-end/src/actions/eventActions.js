import Immutable from 'immutable';
import uuid from 'uuid';
import isNumber from 'is-number';
import {
  saveSettings,
  updateSetting,
  getSettings,
} from './settingsActions';
import { saveEntity, deleteEntity } from './entityActions';
import { pushToList } from './listActions';
import {
  setFormModalError,
} from './guiStateActions/pageActions';
import {
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';
import {
  effectOnEventUsagetFieldsSelector,
} from '@/selectors/eventSelectors';
import {
  servicesDataSelector,
} from '@/selectors/listSelectors';
import {
  getProductsByKeysQuery,
} from '@/common/ApiQueries';
import { getValueByUnit } from '@/common/Util';
import { validateKey } from '@/common/Validators';
import {
	getPathParams,
  buildBalanceConditionPath,
  getBalancePrepaidConditionType,
  getBalanceConditionData,
} from '@/components/Events/EventsUtil.js';


const getEventConvertedConditions = (propertyTypes, usageTypes, item, toBaseUnit = true) => {
  const convertedConditions = item.get('conditions', Immutable.List()).withMutations((conditionsWithMutations) => {
    conditionsWithMutations.forEach((cond, index) => {
      const unit = cond.get('unit', '');
      const usaget = cond.get('usaget', '');
      if (unit !== '' && usaget !== '') {
        const value = cond.get('value', 0);
        const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, value, toBaseUnit);
        conditionsWithMutations.setIn([index, 'value'], newValue);
      }
    });
  });
  return !convertedConditions.isEmpty()
    ? convertedConditions
    : Immutable.List();
};

const getEventConvertedThreshold = (propertyTypes, usageTypes, item, toBaseUnit = true) => {
  const convertedThreshold = item.getIn(['threshold_conditions', 0], Immutable.List()).withMutations((thresholdWithMutations) => {
    thresholdWithMutations.forEach((threshold, index) => {
      const unit = threshold.get('unit', '');
      const usaget = threshold.get('usaget', '');
      const value = threshold.get('value', 0);
      if (unit !== '' && usaget !== '') {
        if (Immutable.List.isList(value) || Array.isArray(value)) {
          const arrayVal = value.map((val) => {
            const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, val, toBaseUnit);
            return isNumber(newValue) ? parseFloat(newValue) : newValue;
          });
          thresholdWithMutations.setIn([index, 'value'], arrayVal);
        } else {
          const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, value, toBaseUnit);
          const val = isNumber(newValue) ? parseFloat(newValue) : newValue;
          thresholdWithMutations.setIn([index, 'value'], val);
        }
      }
      if (!toBaseUnit && Immutable.Iterable.isIterable(value)) {
        thresholdWithMutations.setIn([index, 'value'], value.toList());
      }
      if (!toBaseUnit && !Immutable.Iterable.isIterable(value) && ['in', 'nin'].includes(threshold.get('op', ''))) {
        thresholdWithMutations.setIn([index, 'value'], Immutable.List([value]));
      }
    });
  });
  return !convertedThreshold.isEmpty()
    ? convertedThreshold
    : Immutable.List();
};


const isOldEventStructure = (event) => event
    .get('conditions', Immutable.List())
    .reduce((acc, condition, key) => (acc === true ? acc : condition.has('path')), false);

const convertOldEventCondition = (condition, servicesData) => {
  const { trigger, limitation, activityType, groupNames, overGroup } = getPathParams(Immutable.List([Immutable.Map({'path': condition.get('path', '')})]));
  const params = { activityType, groupNames, overGroup, servicesData };
  const paths = buildBalanceConditionPath(trigger, limitation, params); 
  return condition
    .set('paths', paths)
    .set('property_type', condition.get('usaget', ''))
    .delete('path');
}

const convertOldEventStructure = (event, servicesData) => {
  if (!isOldEventStructure(event)) {
    return event;
  }
  return event.update(
    'conditions', Immutable.List(),
    conditions => conditions.map(condition => convertOldEventCondition(condition, servicesData))
  );
}

const convertFromApiToUi = (event, eventType, params) => event.withMutations((eventWithMutations) => {
  const { propertyTypes, usageTypesData, effectOnUsagetFields } = params;
  const eventUsageTypeFromEvent = Immutable.Map().withMutations((eventUsageTypeWithMutations) => {
    if (eventType === 'fraud') {
      event.getIn(['conditions', 0]).forEach((cond) => {
        if (effectOnUsagetFields.includes(cond.get('field', ''))) {
          eventUsageTypeWithMutations.set(cond.get('field', ''), cond.get('value', Immutable.List()));
        }
      });
    }
  });
  const uiFlags = Immutable.Map({
    id: uuid.v4(),
    eventUsageType: eventUsageTypeFromEvent,
  });
  eventWithMutations.set('conditions', getEventConvertedConditions(propertyTypes, usageTypesData, event, false));
  if (eventWithMutations.has('threshold_conditions')) {
    eventWithMutations.setIn(['threshold_conditions', 0], getEventConvertedThreshold(propertyTypes, usageTypesData, event, false));
  }
  eventWithMutations.set('ui_flags', uiFlags);
});

const convertFromUiToApi = (event, eventType, params) =>
  event.withMutations((eventWithMutations) => {
    const { propertyTypes, usageTypesData } = params;
    eventWithMutations.delete('ui_flags');
    if (eventType === 'fraud') {
      const withoutEmptyConditions = eventWithMutations.getIn(['conditions', 0], Immutable.List())
        .filter(cond => (cond.get('field', '') !== '' && cond.get('op', '')));
      eventWithMutations.setIn(['conditions', 0], withoutEmptyConditions);
    }
    eventWithMutations.set('conditions', getEventConvertedConditions(propertyTypes, usageTypesData, eventWithMutations, true));
    if (eventWithMutations.has('threshold_conditions')) {
      eventWithMutations.setIn(['threshold_conditions', 0], getEventConvertedThreshold(propertyTypes, usageTypesData, eventWithMutations, true));
    }
  });

export const prepareEventForEdit = (eventType, item) => (dispatch, getState) => {
  const state = getState();
  const usageTypesData = usageTypesDataSelector(state);
  const propertyTypes = propertyTypeSelector(state);
  const effectOnUsagetFields = effectOnEventUsagetFieldsSelector(state, { eventType: 'fraud' });
  const servicesData = servicesDataSelector(state, {}) || Immutable.Map();
  const params = ({ usageTypesData, propertyTypes, effectOnUsagetFields });
  return convertOldEventStructure(convertFromApiToUi(item, eventType, params), servicesData);
};

export const saveEvent = (eventType, event) => (dispatch, getState) => {
  const state = getState();
  const usageTypesData = usageTypesDataSelector(state);
  const propertyTypes = propertyTypeSelector(state);
  const eventCategory = eventType === 'balancePrepaid' ? 'balance' : eventType;
  const params = ({ usageTypesData, propertyTypes });
  const convertedEvent = convertFromUiToApi(event, eventCategory, params)
    .set('type', eventCategory);
  const action = convertedEvent.has('_id') ? 'update' : 'create';
  return dispatch(saveEntity('eventsettings', convertedEvent, action));
};

export const deleteEvent = event => dispatch =>
  dispatch(deleteEntity('eventsettings', event));

export const setEventActive = (item, active) => dispatch =>
  dispatch(saveEntity('eventsettings', Immutable.Map({ _id: item.get('_id'), active }), 'update'));

export const saveEventSettings = () => dispatch => dispatch(saveSettings(['events.settings']));

export const updateEventSettings = (path, value) => dispatch => dispatch(updateSetting('events', ['settings', ...path], value));

export const getEventSettings = () => dispatch => dispatch(getSettings(['events.settings']));

export const getEventRates = eventRatesKeys => dispatch =>
  dispatch(pushToList('event_products', getProductsByKeysQuery(eventRatesKeys.toArray(), { key: 1, rates: 1 })));

export const validateFieldEventCode = (value = '') => {
  if (value === '') {
    return 'Event Code is required';
  }
  return true;
};

export const validateFieldKey = (value = '') => (
  validateKey(value)
    ? true
    : 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)'
);

export const validateFieldRecurrenceValue = (value = '') => {
  if (value === '') {
    return 'Field is required';
  }
  return true;
};

export const validateFieldDateRangeValue = (value = '') => {
  if (value === '') {
    return 'Field is required';
  }
  return true;
};

export const validateThresholdField = (value = Immutable.Map()) => {
  if (value.get('field', '') === '') {
    return 'Threshold field is required';
  }
  if (value.get('op', '') === '') {
    return 'Threshold Operator is required';
  }
  if (['', []].includes(value.get('value', '')) || Immutable.is(Immutable.List(), value.get('value', Immutable.List()))) {
    return 'Threshold value is required';
  }
  return true;
};

export const validateFieldBalancePrepaidOperatorCondition = (condition) => {
    if (condition.get('type', '') === '') {
      return 'Field is required';
    }
    return true;
};

export const validateFieldBalancePrepaidValueCondition = (condition) => {
    if (condition.get('value', '') === '') {
      const operatorOption = getBalanceConditionData(condition.get('type', ''));
      if (operatorOption.get('extra_field', true)) {
        return 'Field is required';
      }
    }
    return true;
};

export const validateFieldBalancePrepaidBucketCondition = (condition) => {
  if (condition.get('value', '') === '') {
    return 'Field is required';
  }
  return true;
};

export const validateEvent = (item, eventType) => (dispatch) => {
  let isValid = true;
  const keyValid = validateFieldKey(item.get('key', ''));
  if (keyValid !== true) {
    isValid = false;
    dispatch(setFormModalError('key', keyValid));
  }
  const eventCodeValid = validateFieldEventCode(item.get('event_code', ''));
  if (eventCodeValid !== true) {
    isValid = false;
    dispatch(setFormModalError('event_code', eventCodeValid));
  }
  
  if (eventType === 'balancePrepaid') {
    const conditions = item.get('conditions', Immutable.List());
    conditions.forEach(condition => {
      const type = getBalancePrepaidConditionType(condition);
      if (type === 'bucket') {
        const conditionBucketValid = validateFieldBalancePrepaidBucketCondition(condition);
        if (conditionBucketValid !== true) {
          isValid = false;
          dispatch(setFormModalError('bucket', conditionBucketValid));
        }
      } else if (type === 'value') {
        const conditionOperatorValid = validateFieldBalancePrepaidOperatorCondition(condition);
        if (conditionOperatorValid !== true) {
          isValid = false;
          dispatch(setFormModalError('operator', conditionOperatorValid));
        }
        const conditionValueValid = validateFieldBalancePrepaidValueCondition(condition);
        if (conditionValueValid !== true) {
          isValid = false;
          dispatch(setFormModalError('value', conditionValueValid));
        }
      }
    });
  }
  if (eventType === 'fraud') {
    const recurrenceValueValid = validateFieldRecurrenceValue(item.getIn(['recurrence', 'value'], ''));
    if (recurrenceValueValid !== true) {
      isValid = false;
      dispatch(setFormModalError('recurrence.value', recurrenceValueValid));
    }
    const dateRangeValueValueValid = validateFieldRecurrenceValue(item.getIn(['date_range', 'value'], ''));
    if (dateRangeValueValueValid !== true) {
      isValid = false;
      dispatch(setFormModalError('date_range.value', dateRangeValueValueValid));
    }
    const thresholdConditions = item.getIn(['threshold_conditions', 0], Immutable.List());
    if (thresholdConditions.isEmpty()) {
      isValid = false;
      dispatch(setFormModalError('threshold_condition.0', validateThresholdField()));
    } else {
      thresholdConditions.forEach((condition, idx) => {
        const thresholdField = validateThresholdField(condition);
        if (thresholdField !== true) {
          isValid = false;
          dispatch(setFormModalError(`threshold_condition.${idx}`, thresholdField));
        }
      });
    }
  }

  return isValid;
};
