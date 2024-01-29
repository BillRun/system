import Immutable from 'immutable';
import { fetchDiscountByIdQuery } from '../common/ApiQueries';
import {
  saveEntity,
  getEntity,
  clearEntity,
  updateEntityField,
  deleteEntityField,
  setCloneEntity,
  validateMandatoryField,
} from './entityActions';
import { setFormModalError } from '@/actions/guiStateActions/pageActions';


export const setCloneDiscount = () => setCloneEntity('discount', 'discount');

export const clearDiscount = () => clearEntity('discount');

export const saveDiscount = (item, action) => saveEntity('discounts', item, action);

export const updateDiscount = (path, value) => updateEntityField('discount', path, value);

export const deleteDiscountValue = path => deleteEntityField('discount', path);

export const getDiscount = id => getEntity('discount', fetchDiscountByIdQuery(id));

const validateConditions = (entity, dispatch) => {
  let hasErros = false;
  let hasCycleSupportedFields = false;
  const cycleSupportedFields = [
    //'service.*', // all fields from service condition block are supported (not used)
    'subscriber.plan',
    'subscriber.plan_activation',
    'subscriber.plan_deactivation',
  ];
  const emptyConditionError = 'Conditions can not be empty';
  const unlimitedCucleError = 'When limiting by cycles, please include at least one condition on Plan or Service';
  const cycles = entity.getIn(['params', 'cycles'], '');
  const conditionsPath = ['params', 'conditions'];
  entity.getIn(conditionsPath, Immutable.List()).forEach((conditionsGroups, conditionsGroupsIdx) => {
    conditionsGroups.forEach((conditionsTypeGroup, type) => {
      if (type === 'account') {
        conditionsTypeGroup.get('fields', Immutable.List())
          .forEach((condition, conditionIdx) => {
            if (condition.isEmpty() || condition.some(field => ([], '').includes(field))) {
              const path = [...conditionsPath, conditionsGroupsIdx, type, 'fields', conditionIdx];
              dispatch(setFormModalError(path.join('.'), emptyConditionError));
              hasErros = true;
            }
          })
      } else {
        conditionsTypeGroup.forEach((subscriberConditionsGroups, subscriberConditionsGroupsIdx) => {
          subscriberConditionsGroups.forEach((subscriberConditionsGroup, subscriberConditionsGroupType) => {
            if (subscriberConditionsGroupType === 'fields') {
              subscriberConditionsGroup.forEach((condition, conditionIdx) => {
                if (condition.isEmpty() || condition.some(field => ([], '').includes(field))) {
                  const path = [...conditionsPath, conditionsGroupsIdx, type, subscriberConditionsGroupsIdx, 'fields', conditionIdx];
                  dispatch(setFormModalError(path.join('.'), emptyConditionError));
                  hasErros = true;
                }
                if (cycles !== '') {
                  hasCycleSupportedFields = cycleSupportedFields.includes(`subscriber.${condition.get('field', '')}`);
                }
              })
            } else if (subscriberConditionsGroupType === 'service') {
              subscriberConditionsGroup.get('any', Immutable.List()).forEach((serviceConditionGroups, serviceConditionGroupsIdx) => {
                serviceConditionGroups.forEach((conditions) => {
                  conditions.forEach((condition, conditionIdx) => {
                    hasCycleSupportedFields = true;
                    if (condition.isEmpty() || condition.some(field => ([], '').includes(field))) {
                      const path = [...conditionsPath, conditionsGroupsIdx, type, subscriberConditionsGroupsIdx, subscriberConditionsGroupType, 'any', serviceConditionGroupsIdx, 'fields', conditionIdx];
                      dispatch(setFormModalError(path.join('.'), emptyConditionError));
                      hasErros = true;
                    }
                  })
                })
              })
            }
          })
        })
      }
    })
  })
  if (cycles !== '' && !hasCycleSupportedFields) {
    hasErros = true;
    dispatch(setFormModalError('params.cycles', unlimitedCucleError));
  } else {
    dispatch(setFormModalError('params.cycles'));
  }
  return hasErros;
}

export const validateEntity = (entity, fieldsConfig, mode) => (dispatch) => {
  // To field is not mandatory and will be set by BE
  const fields = fieldsConfig.map(field => {
    if (field.get('field_name', '') === 'to' && !['saveInSubscriber'].includes(mode)) {
      return field.set('mandatory', false);
    }
    return field;
  });
  return Immutable.Map().withMutations((errorsWithMutations) => {
    if (validateConditions(entity, dispatch)) {
      errorsWithMutations.set('conditions', true);
    }
    fields.forEach((field) => {
      const fieldName = field.get('field_name', '');
      const fieldValue = entity.getIn(fieldName.split('.'));
      const hasError = validateMandatoryField(fieldValue, field);
      if (hasError !== true) {
        errorsWithMutations.set(fieldName, hasError);
      }
    });
  });
};
