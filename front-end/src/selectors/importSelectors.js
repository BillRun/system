import { createSelector } from 'reselect';
import { compose } from 'redux';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import {
  isLinkerField,
  isUpdaterField,
  setFieldTitle,
  getConfig,
  getFieldName,
} from '@/common/Util';
import {
  itemSelector,
  selectorFieldsByEntity,
} from './entitySelector';
import {
  productFieldsSelector,
  accountFieldsSelector,
  subscriberFieldsSelector,
  formatFieldOptions,
  addDefaultFieldOptions,
  importSelector,
  importersSelector,
} from './settingsSelector';

const selectSubscriberImportFields = (fields, accountfields) => {
  if (fields) {
    const importLinkers = accountfields.filter(isLinkerField);
    if (importLinkers.size > 0) {
      return fields.withMutations((fieldsWithMutations) => {
        importLinkers.forEach((importLinker) => {
          fieldsWithMutations.push(Immutable.Map({
            linker: true,
            field_name: importLinker.get('field_name', 'linker'),
            title: importLinker.get('title', importLinker.get('field_name', 'linker')),
          }));
        });
      });
    }
    return fields.push(Immutable.Map({
      linker: true,
      field_name: 'account_import_id',
      title: 'Customer Import ID',
    }));
  }
  return fields;
};

const selectProductImportFields = (fields) => {
  const hiddenFields = ['to', 'rates', 'tax', 'rounding_rules'];
  const uniqueFields = ['key'];
  const mandatory = ['tariff_category'];
  return Immutable.List().withMutations((fieldsWithMutations) => {
    fields.forEach((field) => {
      // Update field
      const productField = field.withMutations((fieldWithMutations) => {
        const isUniqueField = uniqueFields.includes(fieldWithMutations.get('field_name', ''));
        if (isUniqueField) {
          fieldWithMutations.set('unique', true);
        }
        const isMandatoryField = mandatory.includes(fieldWithMutations.get('field_name', ''));
        if (isMandatoryField) {
          fieldWithMutations.set('mandatory', true);
        }
        if (isUpdaterField(fieldWithMutations)) {
          fieldWithMutations.set('updater', true);
        }
      });
      // push to list
      const editable = productField.get('editable', true);
      const isHiddenField = hiddenFields.includes(productField.get('field_name', ''));
      if (editable && !isHiddenField) {
        fieldsWithMutations.push(productField);
      } else {
        fieldsWithMutations.push(productField.set('show', false));
      }
      if (productField.get('field_name', '') === 'tax') {
        // // type:"vat",
        // const fieldTaxType = Immutable.Map({ field_name: 'tax__type' });
        // fieldsWithMutations.push(setFieldTitle(fieldTaxType, 'product'));

        // taxation: "no" / "global" / "custom" / "default",
        const fieldTaxTaxation = Immutable.Map({ field_name: 'tax__taxation', title: 'Taxation' });
        fieldsWithMutations.push(setFieldTitle(fieldTaxTaxation, 'product'));

        // custom_tax "TAX_BULGARIA" // Tax doc key, relevant for "custom" only
        const fieldTaxCustomTax = Immutable.Map({ field_name: 'tax__custom_tax', title: 'Tax custom rate' });
        fieldsWithMutations.push(setFieldTitle(fieldTaxCustomTax, 'product'));

        // custom_logic: "override" / "fallback" // relevant for "custom" only
        const fieldTaxCustomLogic = Immutable.Map({ field_name: 'tax__custom_logic', title: 'Tax custom logic' });
        fieldsWithMutations.push(setFieldTitle(fieldTaxCustomLogic, 'product'));
      }
      if (productField.get('field_name', '') === 'rates') {
        const fieldPriceFrom = Immutable.Map({ field_name: 'price_from' });
        fieldsWithMutations.push(setFieldTitle(fieldPriceFrom, 'product'));

        const fieldPriceTo = Immutable.Map({ field_name: 'price_to' });
        fieldsWithMutations.push(setFieldTitle(fieldPriceTo, 'product'));

        const fieldPriceInterval = Immutable.Map({ field_name: 'price_interval' });
        fieldsWithMutations.push(setFieldTitle(fieldPriceInterval, 'product'));

        const fieldPriceValue = Immutable.Map({ field_name: 'price_value' });
        fieldsWithMutations.push(setFieldTitle(fieldPriceValue, 'product'));

        const fieldPricePlan = Immutable.Map({ field_name: 'price_plan' });
        fieldsWithMutations.push(setFieldTitle(fieldPricePlan, 'product'));

        const helpPercentage = 'Field will effect only if \'Plan Price Override\' value is different from BASE';
        const fieldPricePercentage = Immutable.Map({ field_name: 'rates.plans.percentage', help: helpPercentage });
        fieldsWithMutations.push(setFieldTitle(fieldPricePercentage, 'import'));

        const fieldPriceService = Immutable.Map({ field_name: 'price_service' });
        fieldsWithMutations.push(setFieldTitle(fieldPriceService, 'service'));

        const helpServicePercentage = 'Field will effect only if \'Service Price Override\' value is different from BASE';
        const fieldServicePricePercentage = Immutable.Map({ field_name: 'rates.services.percentage', help: helpServicePercentage });
        fieldsWithMutations.push(setFieldTitle(fieldServicePricePercentage, 'import'));
      }
      if (productField.get('field_name', '') === 'rounding_rules') {
        const fieldRoundingType = Immutable.Map({ field_name: 'rounding_type', title: 'Final charge rounding type' });
        fieldsWithMutations.push(setFieldTitle(fieldRoundingType, 'product'));
  
        const fieldRoundingDecimals = Immutable.Map({ field_name: 'rounding_decimals', title: 'Final charge rounding decimals' });
        fieldsWithMutations.push(setFieldTitle(fieldRoundingDecimals, 'product'));
      }
    });
    const fieldPricePlan = Immutable.Map({
      field_name: 'usage_type',
      mandatory: true,
    });
    fieldsWithMutations.push(fieldPricePlan);
    const fieldEffectiveDate = Immutable.Map({
      field_name: 'effective_date',
      mandatory: true,
    });
    fieldsWithMutations.push(fieldEffectiveDate);
  });
};

const selectAccountImportFields = (fields) => {
  if (fields) {
    const existImportLinker = fields.findIndex(isLinkerField);
    return (existImportLinker !== -1)
      ? fields
      : fields.push(Immutable.Map({
        unique: true,
        generated: false,
        mandatory: true,
        field_name: 'account_import_id',
        title: 'Customer Import ID (for subscriber import)',
      }));
  }
  return fields;
};

export const accountImportFieldsSelector = createSelector(
  accountFieldsSelector,
  selectAccountImportFields,
);

export const subscriberImportFieldsSelector = createSelector(
  subscriberFieldsSelector,
  accountImportFieldsSelector,
  selectSubscriberImportFields,
);

export const productImportFieldsSelector = createSelector(
  productFieldsSelector,
  selectProductImportFields,
);

export const importMapperSelector = createSelector(
  importSelector,
  // if mapping is empty it will transfet to object
  (importConfig = Immutable.Map()) => importConfig.get('mapping', Immutable.List()).toList()
);

export const importFieldsOptionsSelector = createSelector(
  itemSelector,
  accountImportFieldsSelector,
  subscriberImportFieldsSelector,
  productImportFieldsSelector,
  (item, accountFields, subscriberImportFields, productImportFields) => compose(
    composedFields => (composedFields ? composedFields.toArray() : undefined),
    fieldsByEntity => addDefaultFieldOptions(fieldsByEntity, item),
    fieldsByEntity => formatFieldOptions(fieldsByEntity, item),
    selectorFieldsByEntity,
  )(item, accountFields, subscriberImportFields, productImportFields),
);

const getConfigImportTypes = (state, props) => {
  return getConfig(['import', 'allowed_entities_importype'], Immutable.Map())
}

const mergeImportOptions = (item, configTypes, apiSettingsTypes, itemType = '') =>
  Immutable.List().withMutations((optionsWithMutations) => {
    let entity = itemType;
    if (entity === '' && item && item.get('entity', '') !== '') {
      entity = item.get('entity', '');
    }
    configTypes
      .filter(entities => entities.includes(entity))
      .forEach((entities, type) => {
        if (type === 'predefined_mapping') {
          const exportVersion = getConfig(['env', 'exportVersion'], '');
          optionsWithMutations.push(Immutable.Map({
            value: type,
            label: `${sentenceCase(getFieldName(type, 'import'))} (${exportVersion})`,
          }));
        } else {
          optionsWithMutations.push(type);
        }
      });
      apiSettingsTypes
      .filter(entities => entities.includes(entity))
      .forEach((entities, type) => {
        optionsWithMutations.push(Immutable.Map({
          value: type,
          label: sentenceCase(getFieldName(type, 'import')),
          type: 'plugin',
        }));
      });
  })
  .map((option) => Immutable.Map.isMap(option)
    ? ({ value: option.get('value', ''), label: option.get('label', ''), type: option.get('type', '') })
    : ({ value: option, label: sentenceCase(getFieldName(option, 'import')), type: '' })
  )
  .toArray();

export const importTypesOptionsSelector = createSelector(
  itemSelector,
  getConfigImportTypes,
  importersSelector,
  (state, props, action, itemType) => itemType,
  mergeImportOptions
);
