import { createSelector } from 'reselect';
// import { titleCase, sentenceCase, upperCaseFirst } from 'change-case';
import Immutable from 'immutable';
import {
  getConfig,
  parseConfigSelectOptions,
  sortFieldOption,
  setFieldTitle,
  onlyLineForeignFields,
  foreignFieldWithoutDates,
} from '@/common/Util';
import {
  linesFieldsSelector,
  inputProssesorUsageTypesOptionsSelector,
  usageTypesDataSelector,
} from './settingsSelector';
import {
  getPropsItem,
} from './entitySelector';
import {
  eventRatesSelector,
} from './listSelectors';


/* Helpers */

const formatEventConditionsFilter = (evetntConfigFields = Immutable.List(), billrunFields = Immutable.List()) =>
  Immutable.List().withMutations((optionsWithMutations) => {
    // Set fields from billrun settings
    billrunFields.forEach((billrunField) => {
      billrunField.withMutations((billrunFieldWithMutations) => {
        billrunFieldWithMutations.set('title', setFieldTitle(billrunField).get('title', ''));
        billrunFieldWithMutations.set('type', 'string');
        billrunFieldWithMutations.set('id', billrunField.get('field_name'));
        optionsWithMutations.push(billrunFieldWithMutations);
      });
    });
    // set fields from event config
    evetntConfigFields.forEach((evetntConfigField) => {
      optionsWithMutations.push(setFieldTitle(evetntConfigField, null, 'id'));
    });
  });

/* Getters */
const getEventConfig = () => getConfig('events', Immutable.Map());

export const eventConditionsOperatorsSelector = createSelector(
  getEventConfig,
  (state, props) => props.eventType,
  (config = Immutable.Map(), eventType) => config.getIn(['operators', eventType, 'conditions'], Immutable.List()),
);
export const eventConditionsOperatorsSelectOptionsSelector = createSelector(
  eventConditionsOperatorsSelector,
  (operators = Immutable.Map()) => operators
    .map(parseConfigSelectOptions)
    .toArray(),
);

export const eventConditionsConfigFieldsSelector = createSelector(
  getEventConfig,
  (config = Immutable.Map()) => config.get('conditionsFields', Immutable.List()),
);
export const foreignLinesFieldsSelector = createSelector(
  linesFieldsSelector,
  (lineFields = Immutable.List()) => lineFields
    .filter(onlyLineForeignFields)
    .filter(foreignFieldWithoutDates),
);
export const eventConditionsFilterOptionsSelector = createSelector(
  eventConditionsConfigFieldsSelector,
  foreignLinesFieldsSelector,
  formatEventConditionsFilter,
);
export const eventConditionsFieldsSelectOptionsSelector = createSelector(
  eventConditionsFilterOptionsSelector,
  (state, props) => props.condition.get('field'),
  (state, props) => props.usedFields,
  (conditionsFilter = Immutable.List(), conditionIndexField, usedFields) => conditionsFilter
    .filter(fieldOption => (fieldOption.get('id', '') === conditionIndexField
      ? true // parsing current selected option for index
      : !usedFields.includes(fieldOption.get('id', ''))
    ))
    .sort(sortFieldOption)
    .map(parseConfigSelectOptions)
    .toArray(),
);

export const eventThresholdOperatorsSelector = createSelector(
  getEventConfig,
  (state, props) => props.eventType,
  (config = Immutable.Map(), eventType) => config.getIn(['operators', eventType, 'threshold'], Immutable.List()),
);
export const eventThresholdOperatorsSelectOptionsSelector = createSelector(
  eventThresholdOperatorsSelector,
  (operators = Immutable.Map()) => operators
      .map(parseConfigSelectOptions)
      .toArray(),
);

export const eventTresholdFieldsSelector = createSelector(
  getEventConfig,
  (config = Immutable.Map()) => config.get('thresholdFields', Immutable.List())
    .map(field => setFieldTitle(field, null, 'id')),
);

export const effectOnEventUsagetFieldsSelector = createSelector(
  eventConditionsFilterOptionsSelector,
  (fields = Immutable.Map()) => fields
    .filter(field => field.get('effectOnUsaget', false))
    .map(field => field.get('id', '')),
);

export const getItemEventUsageType = createSelector(
  getPropsItem,
  (item = Immutable.Map()) => item.getIn(['ui_flags', 'eventUsageType'], Immutable.Map()),
);

export const eventUsagetFieldUsateTypesSelector = createSelector(
  getItemEventUsageType,
  (eventUsageType = Immutable.Map()) => eventUsageType.get('usaget', Immutable.List()),
);

export const eventArateKeyFieldRatesSelector = createSelector(
  getItemEventUsageType,
  (eventUsageType = Immutable.Map()) => eventUsageType.get('arate_key', Immutable.List()),
);

export const eventTypeFieldUsateTypesSelector = createSelector(
  getItemEventUsageType,
  inputProssesorUsageTypesOptionsSelector,
  (types = Immutable.List(), inputProssesorUsageTypes = Immutable.List()) => Immutable.Set()
    .withMutations((allTypeWithMutations) => {
      inputProssesorUsageTypes
        .filter((ip, name) => types.get('type', Immutable.List()).includes(name))
        .forEach((ipTypes) => {
          allTypeWithMutations.union(ipTypes);
        });
    }),
);

export const eventRatesUsateTypesSelector = createSelector(
  eventRatesSelector,
  (rates = Immutable.List()) => Immutable.Map()
    .withMutations((ratesUsageswithMutations) => {
      rates.forEach((rate) => {
        const usage = rate.get('rates', Immutable.Map()).keySeq().first();
        ratesUsageswithMutations.set(rate.get('key', ''), usage);
      });
    }),
);

export const eventArateKeyFieldUsateTypesSelector = createSelector(
  eventArateKeyFieldRatesSelector,
  eventRatesUsateTypesSelector,
  (item = Immutable.Map(), rates = Immutable.Map()) => item
    .reduce((acc, name) => acc.push(rates.get(name, '')), Immutable.List()),
);

export const eventPropertyTypesSelector = createSelector(
  usageTypesDataSelector,
  eventTypeFieldUsateTypesSelector,
  eventUsagetFieldUsateTypesSelector,
  eventArateKeyFieldUsateTypesSelector,
  (
    usageTypesData = Immutable.List(),
    type = Immutable.List(),
    usaget = Immutable.List(),
    arateKey = Immutable.List()) => {
    const intersect = Immutable.List().withMutations((intersectwithMutations) => {
      [type, usaget, arateKey].forEach((list) => {
        if (!list.isEmpty()) {
          const propertyTypes = list.map(uaset => usageTypesData.find(
              usageTypes => usageTypes.get('usage_type', '') === uaset,
              null, Immutable.Map(),
          ).get('property_type', ''));
          intersectwithMutations.push(propertyTypes.filter(propertyType => propertyType !== ''));
        }
      });
    });
    if (intersect.isEmpty()) {
      return Immutable.Set();
    }
    if (intersect.size === 1) {
      return Immutable.Set(intersect.first());
    }
    return Immutable.Set(intersect.reduce((a, b) => a.filter(c => b.includes(c))));
  },
);

export const eventThresholdFieldsSelectOptionsSelector = createSelector(
  eventTresholdFieldsSelector,
  eventPropertyTypesSelector,
  (fields = Immutable.Map(), usateTypes = Immutable.Set()) => fields
    .filter((field) => {
      const fieldUsageList = field.get('allowedWithUsage', Immutable.List());
      if (fieldUsageList.isEmpty()) { // ["multiple", "empty", "single"]
        return true;
      }
      if (usateTypes.isEmpty()) {
        return fieldUsageList.includes('empty');
      }
      if (usateTypes.size === 1) {
        return fieldUsageList.includes('single');
      }
      return fieldUsageList.includes('multiple');
    })
    .sort(sortFieldOption)
    .map(parseConfigSelectOptions)
    .toArray(),
);
