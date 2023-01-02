import { connect } from 'react-redux';
import { Map, List } from 'immutable';
import { titleCase } from 'change-case';
import CustomFieldForeignForm from './CustomFieldForeignForm';
import {
  validateFieldTitle,
  validateForeignFieldEntity,
  validateForeignFieldField,
  validateConditions,
  createForeignFieldKey,
} from '../../actions/customFieldsActions';
import {
  customFieldsEntityFieldsSelector,
  customFieldsConditionsOperatorsSelectOptionsSelector,
  foreignEntityNameSelector,
} from '../../selectors/customFieldsSelectors';
import {
  getConfig,
  getFieldEntityKey,
} from '../../common/Util';


const defaultEmptyCondition = Map({ field: '', op: '', value: '' });


const mapStateToProps = (state, props) => {
  const { item = Map(), errors = Map(), entity = '' } = props;
  const foreignFieldsConditionsOperators = customFieldsConditionsOperatorsSelectOptionsSelector(state, props);
  const selectedEntity = getFieldEntityKey(item.getIn(['foreign', 'entity'], ''));
  const fieldsConfig = customFieldsEntityFieldsSelector(state, props) || Map();
  const foreignFields = fieldsConfig.get(selectedEntity, List())
    .map(foreignFieldConfig => Map({
      value: foreignFieldConfig.get('field_name', ''),
      label: foreignFieldConfig.get('title', ''),
    }))
    .toArray();
  const foreignEntities = getConfig(['customFields', 'foreignFields', 'entities', entity], List())
    .map(foreignEntity => Map({
      value: getConfig(['customFields', 'entities', foreignEntity], foreignEntity),
      label: foreignEntityNameSelector(foreignEntity),
    }))
    .toList()
    .toArray();
  const translateTypes = getConfig(['customFields', 'foreignFields', 'translate'], Map())
    .map((translateTypeConfig, translateType) => Map({
      value: translateType,
      label: translateTypeConfig.get('title', titleCase(translateType)),
    }))
    .toList()
    .toArray();
  const selectedTranslate = Map.isMap(item) ? item.getIn(['foreign', 'translate', 'type'], '') : '';
  const translateTypeFormats = getConfig(['customFields', 'foreignFields', 'translate', selectedTranslate, 'options'], List())
    .map(option => Map({
      value: option,
      label: option,
    }))
    .toArray();
  const isErrorConditions = errors.reduce((acc, message, field) => (
    (field.split('.')[0] === 'conditions')
    ? acc.set(field.split('.')[1], message)
    : acc
  ), Map());
  return ({
    foreignFields,
    foreignEntities,
    translateTypes,
    translateTypeFormats,
    foreignFieldsConditionsOperators,
    isErrorTitle: errors.get('title', false),
    isErrorForeigEntity: errors.get('foreign.entity', false),
    isErrorForeignField: errors.get('foreign.field', false),
    isErrorForeignTranslateType: errors.get('foreign.translate.type', false),
    isErrorConditions,
  });
};


const mapDispatchToProps = (dispatch, {
  item = Map(), updateField, removeField, setError,
}) => ({

  onChangeTitle: (path, value) => {
    const pathString = path.join('.');
    const isValid = validateFieldTitle(value);
    if (isValid !== true) {
      setError(pathString, isValid);
    } else {
      setError(pathString);
    }
    updateField(path, value);
  },

  onChangeEntity: (path, entity) => {
    const pathString = path.join('.');
    const isValid = validateForeignFieldEntity(entity);
    if (isValid !== true) {
      setError(pathString, isValid);
    } else {
      setError(pathString);
    }
    // reset dependent fields
    updateField(['foreign'], Map({ entity }));
    updateField(['conditions'], List());
    // update field_name
    updateField('field_name', createForeignFieldKey(entity, ''));
  },

  onChangeField: (path, value) => {
    const pathString = path.join('.');
    const isValid = validateForeignFieldField(value);
    if (isValid !== true) {
      setError(pathString, isValid);
    } else {
      setError(pathString);
    }
    updateField(path, value);
    updateField('field_name', createForeignFieldKey(item.getIn(['foreign', 'entity'], ''), value));
  },

  onChangeTranslateType: (path, type) => {
    // always update whole translate object to remove non relevant params
    const updatPath = ['foreign', 'translate'];
    if (type !== '') {
      updateField(updatPath, Map({ type }));
    } else {
      removeField(updatPath);
    }
  },

  onChangeTranslateFormat: (path, value) => {
    if (value !== '') {
      updateField(path, value);
    } else {
      removeField(path);
    }
  },

  onAddCondition: () => {
    const curConditions = item.get('conditions', List());
    const conditionsValid = curConditions.isEmpty() ? true : validateConditions(curConditions);
    if (conditionsValid !== true) {
      conditionsValid.forEach((error, idx) => {
        setError(`conditions.${idx}`, error);
      });
    } else {
      updateField('conditions', curConditions.push(defaultEmptyCondition));
    }
  },

  onUpdateCondition: (path, value) => {
    setError(`conditions.${path[0]}`);
    updateField(['conditions', ...path], value);
  },

  onRemoveCondition: (index) => {
    setError(`conditions.${index}`);
    updateField('conditions', item.get('conditions', List()).delete(index));
  },

});


export default connect(mapStateToProps, mapDispatchToProps)(CustomFieldForeignForm);
