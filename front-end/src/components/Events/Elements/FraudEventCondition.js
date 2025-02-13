import React, { useMemo, useCallback } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { FormGroup, Col } from 'react-bootstrap';
import Field from '@/components/Field';
import ConditionValue from '../../Report/Editor/ConditionValue';
import { Actions } from '@/components/Elements';
import {
  eventConditionsFilterOptionsSelector,
  eventConditionsOperatorsSelectOptionsSelector,
  eventConditionsFieldsSelectOptionsSelector,
  eventConditionsOperatorsSelector,
  effectOnEventUsagetFieldsSelector,
} from '@/selectors/eventSelectors';

const FraudEventCondition = (props) => {
  const {
    condition,
    eventUsageTypes,
    effectOnUsagetFields,
    index,
    conditionField,
    conditionsFieldSelectOptions,
    conditionOperator,
    conditionsOperatorsSelectOptions,
    onUpdate,
    onRemove,
    setEventUsageType,
    getEventRates,
  } = props;

  const field = condition.getIn(['field'], '');
  const operator = condition.getIn(['op'], '');
  const effectOnUsagetField = effectOnUsagetFields.includes(field);
  const disableOp = field === '';
  const disableVal = operator === '' || disableOp;
  const conditionForValue = condition.set('value', condition.get('value', Immutable.List()).join(','));

  const updateEventUsageType = useCallback((types) => {
    if (effectOnUsagetField) {
      setEventUsageType(eventUsageTypes.set(field, types));
    }
  }, [effectOnUsagetField, setEventUsageType, eventUsageTypes, field])

  const onChangeConditionsField = useCallback((fieldId) => {
    const resetCondition = Immutable.Map({
      field: fieldId,
      op: '',
      value: Immutable.List(),
    });
    updateEventUsageType(Immutable.List());
    onUpdate([index], resetCondition);
  }, [index, onUpdate, updateEventUsageType]);

  const onChangeConditionsOperator = useCallback((value) => {
    onUpdate([index, 'op'], value);
  }, [index, onUpdate]);

  const onChangeConditionsValue = useCallback((value) => {
    const values = Immutable.List((value.length) ? value.split(',') : []);
    onUpdate([index, 'value'], values);
    if (effectOnUsagetField) {
      updateEventUsageType(values);
      if (field === 'arate_key') {
        const newRates = values.filter(val => !eventUsageTypes.get('arate_key', Immutable.List()).includes(val));
        getEventRates(newRates);
      }
    }
  }, [index, onUpdate, getEventRates, effectOnUsagetField, updateEventUsageType, field, eventUsageTypes]);

  const actions = useMemo(() => {
    const onRemoveCondition = (index) => {
      updateEventUsageType(Immutable.List());
      onRemove(index);
    };
    return ([{
      type: 'remove', onClick: onRemoveCondition, actionStyle: 'default', helpText: 'Remove Condition'
    }])
  }, [onRemove, updateEventUsageType]);

  return (
    <FormGroup className="form-inner-edit-row">
      <Col smHidden mdHidden lgHidden>
        <label htmlFor="condition_filter">Filter</label>
      </Col>
      <Col sm={4} className="pl0">
        <Field
          id="condition_field"
          fieldType="select"
          options={conditionsFieldSelectOptions}
          onChange={onChangeConditionsField}
          value={field}
        />
      </Col>

      <Col smHidden mdHidden lgHidden>
        <label htmlFor="condition_operator">Operator</label>
      </Col>
      <Col sm={3}>
        <Field
          id="condition_operator"
          fieldType="select"
          options={conditionsOperatorsSelectOptions}
          onChange={onChangeConditionsOperator}
          value={condition.getIn(['op'], '')}
          disabled={disableOp}
        />
      </Col>

      <Col smHidden mdHidden lgHidden>
        <label htmlFor="condition_value">Value</label>
      </Col>
      <Col sm={4}>
        <ConditionValue
          field={conditionForValue}
          config={conditionField}
          operator={conditionOperator}
          disabled={disableVal}
          onChange={onChangeConditionsValue}
        />
      </Col>
      <Col sm={1} className="actions">
        <Actions actions={actions} data={index} />
      </Col>
    </FormGroup>
  );
};

FraudEventCondition.propTypes = {
  condition: PropTypes.instanceOf(Immutable.Map),
  index: PropTypes.number.isRequired,
  eventUsageTypes: PropTypes.instanceOf(Immutable.Map),
  effectOnUsagetFields: PropTypes.instanceOf(Immutable.List),
  conditionField: PropTypes.instanceOf(Immutable.Map),
  conditionsFieldSelectOptions: PropTypes.array,
  conditionOperator: PropTypes.instanceOf(Immutable.Map),
  conditionsOperatorsSelectOptions: PropTypes.array,
  onUpdate: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  setEventUsageType: PropTypes.func.isRequired,
  getEventRates: PropTypes.func.isRequired,
};

FraudEventCondition.defaultProps = {
  condition: Immutable.Map(),
  eventUsageTypes: Immutable.Map(),
  effectOnUsagetFields: Immutable.List(),
  conditionField: Immutable.Map(),
  conditionsFieldSelectOptions: [],
  conditionOperator: Immutable.Map(),
  conditionsOperatorsSelectOptions: [],
};

const mapStateToProps = (state, props) => ({
  conditionField: eventConditionsFilterOptionsSelector(state, { eventType: 'fraud' })
    .find(condField => condField.get('id', '') === props.condition.getIn(['field'], ''), null, Immutable.Map()),
  conditionsFieldSelectOptions: eventConditionsFieldsSelectOptionsSelector(state, { ...props, eventType: 'fraud' }),
  conditionOperator: eventConditionsOperatorsSelector(state, { eventType: 'fraud' })
    .find(condOp => condOp.get('id', '') === props.condition.getIn(['op'], ''), null, Immutable.Map()),
  conditionsOperatorsSelectOptions: eventConditionsOperatorsSelectOptionsSelector(null, { eventType: 'fraud' }),
  effectOnUsagetFields: effectOnEventUsagetFieldsSelector(state, { eventType: 'fraud' }),
});

export default connect(mapStateToProps)(FraudEventCondition);
