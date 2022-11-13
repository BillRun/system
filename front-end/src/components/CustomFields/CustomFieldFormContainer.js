import { connect } from 'react-redux';
import { Map, List } from 'immutable';
import CustomFieldForm from './CustomFieldForm';
import {
  availablePlaysSettingsSelector,
} from '../../selectors/settingsSelector';
import {
  validateFieldTitle,
  validateFieldKey,
} from '../../actions/customFieldsActions';
import {
  isFieldEditable,
} from '../../selectors/customFieldsSelectors';
import {
  getConfig,
  parseConfigSelectOptions,
  inConfigOptionBlackList,
  inConfigOptionWhiteList,
  isEditableFiledProperty,
} from '../../common/Util';


const mapStateToProps = (state, props) => {
  const { item, mode, entity } = props;
  const fieldType = item.get('type', 'text');
  const customFieldsConfig = getConfig(['customFields', 'fields'], List());
  const fieldTypesOptions = customFieldsConfig
    .filter(option => (
      !inConfigOptionBlackList(option, entity, 'excludeEntity')
      && inConfigOptionWhiteList(option, entity, 'includeEntity')
    ))
    .map(parseConfigSelectOptions)
    .toArray();
  const fieldTypeConfig = customFieldsConfig.find(config => config.get('id', '') === fieldType, null, Map());
  const editable = isFieldEditable(item, customFieldsConfig);
  const availablePlays = availablePlaysSettingsSelector(state, props) || List();
  const playsOptions = availablePlays.map(play => ({
    value: play.get('name', ''),
    label: play.get('label', play.get('name', '')),
  })).toArray();
  const showPlays = ['subscription'].includes(entity) && availablePlays.size > 1;
  return ({
    availablePlays: availablePlaysSettingsSelector(state, props),
    fieldType,
    fieldTypeLabel: fieldTypeConfig.get('title', fieldType),
    fieldTypesOptions,
    playsOptions,
    showPlays,
    checkboxStyle: { marginTop: 10, paddingLeft: 26 },
    helpTextStyle: { color: '#626262', verticalAlign: 'text-top' },
    plays: item.get('plays', []).join(','),
    disableTitle: inConfigOptionBlackList(fieldTypeConfig, 'title') || !isEditableFiledProperty(item, editable, 'title'),
    disableFieldName: mode !== 'create',
    disableUnique: inConfigOptionBlackList(fieldTypeConfig, 'unique') || !isEditableFiledProperty(item, editable, 'unique'),
    disableMandatory: inConfigOptionBlackList(fieldTypeConfig, 'mandatory') || !isEditableFiledProperty(item, editable, 'mandatory') || item.get('unique', false),
    disableFieldType: inConfigOptionBlackList(fieldTypeConfig, 'type') || !isEditableFiledProperty(item, editable, 'type'),
    disabledEditable: inConfigOptionBlackList(fieldTypeConfig, 'editable') || !isEditableFiledProperty(item, editable, 'editable'),
    disabledDisplay: inConfigOptionBlackList(fieldTypeConfig, 'display') || !isEditableFiledProperty(item, editable, 'display'),
    disabledShowInList: inConfigOptionBlackList(fieldTypeConfig, 'show_in_list') || !isEditableFiledProperty(item, editable, 'show_in_list'),
    disableSearchable: inConfigOptionBlackList(fieldTypeConfig, 'searchable') || !isEditableFiledProperty(item, editable, 'searchable'),
    disableMultiple: inConfigOptionBlackList(fieldTypeConfig, 'multiple') || !isEditableFiledProperty(item, editable, 'multiple'),
    disableSelectList: inConfigOptionBlackList(fieldTypeConfig, 'select_list') || !isEditableFiledProperty(item, editable, 'select_list'),
    disableSelectOptions: !item.get('select_list', false) || !isEditableFiledProperty(item, editable, 'select_options'),
    disableHelp: inConfigOptionBlackList(fieldTypeConfig, 'help') || !isEditableFiledProperty(item, editable, 'help'),
    disableDescription: inConfigOptionBlackList(fieldTypeConfig, 'description') || !isEditableFiledProperty(item, editable, 'description'),
    disableDefaultValue: inConfigOptionBlackList(fieldTypeConfig, 'default_value') || !isEditableFiledProperty(item, editable, 'default_value'),
    isErrorTitle: (props.errors) && props.errors.get('title', false),
    isErrorFieldName: (props.errors) && props.errors.get('fieldName', false),
  });
};

const mapDispatchToProps = (dispatch, {
  item = Map(), existingFields = List(), updateField, removeField, setError,
}) => ({

  onChangeTitle: (path, value) => {
    const isValidTitle = validateFieldTitle(value);
    if (isValidTitle !== true) {
      setError('title', isValidTitle);
    } else {
      setError('title');
    }
    updateField('title', value);
  },

  onChangeFieldName: (path, value) => {
    const isValidKey = validateFieldKey(value, existingFields);
    if (isValidKey !== true) {
      setError('fieldName', isValidKey);
    } else {
      setError('fieldName');
    }
    updateField('field_name', value);
  },

  onChangeEntityField: (path, value) => {
    // if change default value, remove property in defaultvalue removed
    if (['description', 'help'].includes(path[0]) && value === '') {
      removeField(path);
    } else if (path[0] === 'default_value') {
      if (item.get('type', 'text') === 'ranges' && value.isEmpty()) {
        removeField('default_value');
      } else if (value === '') {
        removeField('default_value');
      } else {
        updateField(path, value);
      }
    } else {
      updateField(path, value);
    }
  },

  onChangeOptions: (e) => {
    const { id, value } = e.target;
    if (id === 'unique') {
      if (value === true) {
        updateField('unique', true);
        updateField('mandatory', true);
        removeField('default_value');
      } else {
        removeField('unique');
      }
    } else if (id === 'select_list') {
      if (value === true) {
        updateField('select_list', true);
        updateField('select_options', item.get('select_options', ''));
      } else {
        removeField('select_list');
        removeField('select_options');
      }
    } else if (id === 'select_options') {
        updateField('select_options', value);
    } else {
      if (value === true) {
        updateField(id, true);
      } else {
        removeField(id);
      }
    }
  },

  onChangePlay: (plays) => {
    updateField('plays', List(plays.split(',')));
  },

  onChangeType: (type) => {
    const oldFieldType = item.get('type', 'text');
    const fieldTypeConfig = getConfig(['customFields', 'fields'], List())
      .find(config => config.get('id', '') === type, null, Map());
    const oldFieldTypeConfig = getConfig(['customFields', 'fields'], List())
      .find(config => config.get('id', '') === oldFieldType, null, Map());
    // update type
    if (type === 'text') {
      removeField('type');
    } else {
      updateField('type', type);
    }
    // reset default value
    if (fieldTypeConfig.get('type', '') !== oldFieldTypeConfig.get('type', '')) {
      if (fieldTypeConfig.has('defaultValue')) {
        updateField('default_value', fieldTypeConfig.get('defaultValue'));
      } else {
        removeField('default_value');
      }
    }
    // reset properties by type
    item.forEach((value, property) => {
      if (inConfigOptionBlackList(fieldTypeConfig, property)) {
        removeField(property);
      }
    })
  },
});

const mergeProps = (stateProps, dispatchProps, {
    entity, errors, existingFields, mode, removeField, setError, setItem, updateField, ...otherOwnProps
  }) => ({
    ...otherOwnProps,
    ...stateProps,
    ...dispatchProps,
  });

export default connect(
  mapStateToProps,
  mapDispatchToProps,
  mergeProps,
)(CustomFieldForm);
