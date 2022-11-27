import { List, Map, is } from 'immutable';
import { titleCase } from 'change-case';
import {
  getSettings,
  saveSettings,
  pushToSetting,
  updateSetting,
  removeSettingField,
} from './settingsActions';
import {
  setPageFlag,
  setFormModalError,
} from './guiStateActions/pageActions';
import {
  customFieldsEntityFieldsSelector,
} from '@/selectors/customFieldsSelectors';
import {
  getSettingsPath,
  getConfig,
  getFieldName,
  getFieldNameType,
} from '@/common/Util';


export const getFields = entity => (dispatch) => {
  const entitysFields = (Array.isArray(entity) ? entity : [entity]).map(entityName => getSettingsPath(entityName, false, ['fields']));
  return dispatch(getSettings(entitysFields));
};

export const saveFields = (entity = '') => (dispatch) => {
  const entitysFields = (Array.isArray(entity) ? entity : [entity]).map(entityName => getSettingsPath(entityName, false, ['fields']));
  return dispatch(saveSettings(entitysFields));
};

export const addField = (entity, field) => (dispatch) => {
  const [entityName, ...pathToField] = getSettingsPath(entity, true, ['fields']);
  return dispatch(pushToSetting(entityName, field, pathToField));
};

export const updateField = (entity, field) => (dispatch, getState) => {
  const fields = customFieldsEntityFieldsSelector(getState(), {}, entity);
  const index = fields.findIndex(f => f.getIn(['field_name'], '') === field.getIn(['field_name'], ''));
  if (index !== -1) {
    const [entityName, ...pathToField] = getSettingsPath(entity, true, ['fields', index]);
    return dispatch(updateSetting(entityName, pathToField, field));
  }
  return false;
};

export const removeField = (entity, field) => (dispatch, getState) => {
  const fields = customFieldsEntityFieldsSelector(getState(), {}, entity);
  const index = fields.findIndex(f => f.getIn(['field_name'], '') === field.getIn(['field_name'], ''));
  if (index !== -1) {
    const [entityName, ...pathToField] = getSettingsPath(entity, true, ['fields', index]);
    return dispatch(removeSettingField(entityName, pathToField));
  }
  return false;
};

export const saveAndReloadFields = entity => dispatch => dispatch(saveFields(entity))
  .then(success => (success.status ? true : Promise.reject()))
  .then(() => dispatch(getFields(entity)))
  .catch(() => dispatch(getFields(entity)).then(() => Promise.reject()));

export const setFlag = (key, value) => setPageFlag('customFields', key, value);

export const removeFlag = key => setPageFlag('customFields', key);

export const removeFlags = () => setPageFlag('customFields');

export const validateRequiredValue = (value = '', field = '') =>
  ((value === '') ? `${field} is required` : true);

export const validateFieldTitle = (value = '') => validateRequiredValue(value, 'Title');

export const validateForeignFieldEntity = (value = '') => validateRequiredValue(value, 'Entity');

export const validateForeignFieldField = (value = '') => validateRequiredValue(value, 'Entity Field');

export const validateFieldKey = (value = '', existingFields = List()) => {
  if (!getConfig('fieldKeyRegex', '').test(value)) {
    return 'Key contains illegal characters, field name should contain only alphabets, numbers and underscores (A-Z, a-z, 0-9, ., _)';
  }
  if (value === '') {
    return 'Key is required';
  }
  if (existingFields.includes(value)) {
    return 'Key already exists';
  }
  return true;
};

export const validateCondition = (conditoin) => {
  const emptyField = conditoin.has('field') && conditoin.get('field', '') === '';
  const emptyOP = conditoin.has('op') && conditoin.get('op', '') === '';
  const emptyValue = conditoin.has('value') && (conditoin.get('value', '') === '' || is(conditoin.get('value', List()), List()));
  if (emptyField) {
    return ('Conditions field can not be empty');
  }
  if (emptyOP) {
    return ('Conditions operator can not be empty');
  }
  if (emptyValue) {
    return ('Conditions value can not be empty');
  }
  return true;
};

export const validateConditions = (conditoins) => {
  const errors = Map().withMutations((errorsWithMutations) => {
    conditoins.forEach((cond, idx) => {
      const isValid = validateCondition(cond);
      if (isValid !== true) {
        errorsWithMutations.set(idx, isValid);
      }
    });
  });
  return errors.isEmpty() ? true : errors;
};

export const validateField = (field, usedNames) => (dispatch) => {
  let isValid = true;
  const titleValid = validateFieldTitle(field.get('title', ''));
  if (titleValid !== true) {
    isValid = false;
    dispatch(setFormModalError('title', titleValid));
  }

  const keyValid = validateFieldKey(field.get('field_name', ''), usedNames);
  if (keyValid !== true) {
    isValid = false;
    dispatch(setFormModalError('fieldName', keyValid));
  }
  return isValid;
};

export const validateForeignField = (field, existingFields = List()) => (dispatch) => {
  let isValid = true;
  const curEntity = field.getIn(['foreign', 'entity'], '');
  const curField = field.getIn(['foreign', 'field'], '');
  const curConditions = field.get('conditions', List());
  // Validate for title
  const titleValid = validateFieldTitle(field.get('title', ''));
  if (titleValid !== true) {
    isValid = false;
    dispatch(setFormModalError('title', titleValid));
  }
  // Validate for field
  const foreignEntityValid = validateForeignFieldEntity(curEntity);
  if (foreignEntityValid !== true) {
    isValid = false;
    dispatch(setFormModalError('foreign.entity', foreignEntityValid));
  }
  // Validate for entity
  const foreignFieldValid = validateForeignFieldField(curField);
  if (foreignFieldValid !== true) {
    isValid = false;
    dispatch(setFormModalError('foreign.field', foreignFieldValid));
  }
  // Validate for same entity and field
  if (!existingFields.isEmpty()) {
    const isFieldNameExists = existingFields.reduce((acc, existingField) => {
      const extEntity = existingField.getIn(['foreign', 'entity'], '');
      const extField = existingField.getIn(['foreign', 'field'], '');
      return (curEntity === extEntity && curField === extField) ? existingField : acc;
    }, false);
    if (isFieldNameExists) {
      isValid = false;
      const entityLabel = titleCase(getConfig(['systemItems', getFieldNameType(field.getIn(['foreign', 'entity'], '')), 'itemName'], field.getIn(['foreign', 'entity'], '')));
      const fieldLabel = titleCase(getFieldName(field.getIn(['foreign', 'field'], ''), getFieldNameType(field.getIn(['foreign', 'entity'], ''))));
      const fieldTitle = isFieldNameExists.get('title', isFieldNameExists.get('field_name', ''));
      const meassge = `Foreign field ${fieldLabel} from entity ${entityLabel} already exists with title '${fieldTitle}'`;
      dispatch(setFormModalError('foreign.field', meassge));
    }
  }
  // Validate conditions
  const conditionsValid = curConditions.isEmpty() ? true : validateConditions(curConditions);
  if (conditionsValid !== true) {
    isValid = false;
    conditionsValid.forEach((error, idx) => {
      dispatch(setFormModalError(`conditions.${idx}`, error));
    });
  }
  return isValid;
};

export const createForeignFieldKey = (entity, field) => `foreign.${entity}.${field}`;
