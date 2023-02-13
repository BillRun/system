import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import {
  getFieldName,
  getFieldNameType,
  getConfig,
  sortFieldOption,
  toImmutableList,
} from '@/common/Util';
import {
  subscriberFieldsWithPlaySelector,
  accountFieldsSelector,
  isPlaysEnabledSelector,
} from './settingsSelector';


const getDiscountsConditionsConfigFields = (state, props, type) => getConfig(['discount', 'conditions', 'fields', type], Immutable.List());

const getType = (state, props, type) => type;

const formatFields = (fields) => {
  if (!fields) {
    return Immutable.List();
  }
  return fields.map(field => Immutable.Map({
    id: field.get('field_name', ''),
    title: field.get('title', ''),
    type: field.get('type', 'string'),
    inputConfig: field.get('select_list', false) ? Immutable.Map({
      inputType: 'select',
      options: toImmutableList(field.get('select_options', '').split(',').filter(val => val !== "")).filter(val => val !== ""),
    }) : null,
  }));
};

const mergeBillRunAndConfigFields = (
  billrunConfigFields,
  discountsConfigFields,
  type,
  isPlayEnabled = false,
) => {
  const entityFields = formatFields(billrunConfigFields);
  const defaultField = Immutable.Map({});
  return Immutable.List().withMutations((fieldsWithMutations) => {
    // Push all fields from Billrun config
    entityFields.forEach((entityField) => {
      fieldsWithMutations.push(entityField);
    });
    // Push discount config fields or override if exist
    discountsConfigFields.forEach((predefinedFiled) => {
        const index = fieldsWithMutations.findIndex(field => field.get('id', '') === predefinedFiled.get('id', ''));
        if (index === -1) {
          fieldsWithMutations.push(defaultField.merge(predefinedFiled));
        } else {
          fieldsWithMutations.update(index, Immutable.Map(), field => field.merge(predefinedFiled));
        }
    });
    // Set title if not exist
    fieldsWithMutations.forEach((field, index) => {
      //if (predefinedFiled.get('show', true)) {
      if (!field.has('title')) {
        const title = getFieldName(field.get('id', ''), getFieldNameType(type), sentenceCase(field.get('id', '')));
        fieldsWithMutations.setIn([index, 'title'], title);
      }
    });
  })
  // filter play
  .filter(field => (
    field.get('id') !== 'play' || (field.get('id') === 'play' && isPlayEnabled)
  ))
  // filter hidden fields
  .filter(field => field.get('show', true))
  .sort(sortFieldOption);
};

export const discountSubscriberFieldsSelector = createSelector(
  subscriberFieldsWithPlaySelector,
  getDiscountsConditionsConfigFields,
  getType,
  isPlaysEnabledSelector,
  mergeBillRunAndConfigFields,
);

export const discountAccountFieldsSelector = createSelector(
  accountFieldsSelector,
  getDiscountsConditionsConfigFields,
  getType,
  isPlaysEnabledSelector,
  mergeBillRunAndConfigFields,
);

export const discountSubscriberServicesFieldsSelector = createSelector(
  () => Immutable.List(),
  getDiscountsConditionsConfigFields,
  getType,
  () => true,
  mergeBillRunAndConfigFields,
);
