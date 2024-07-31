import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import { getCycleName } from '@/components/Cycle/CycleUtil';
import { getConfig, inferPropTypeFromUsageType } from '@/common/Util';
import { propertyTypeSelector } from '@/selectors/settingsSelector';
import {
  availablePlaysLabelsSelector,
} from './settingsSelector';


const getSuggestionsRates = state => state.list.get('suggestions_products', null);

const getEventRates = state => state.list.get('event_products', null);

const getCyclesOptions = state => state.list.get('cycles_list', null);

const getUserNamesOptions = state => state.list.get('autocompleteUser', null);

const getAuditEntityTypesOptions = () => getConfig('systemItems');

const getAuditLogs = state => state.list.get('audit');

const selectCyclesOptions = (options) => {
  if (options === null) {
    return undefined;
  }
  return options.map(option => Immutable.Map({
    label: getCycleName(option),
    value: option.get('billrun_key', ''),
  }));
};

const getAccountsOptions = state => state.list.get('available_accounts', null);

const selectAccountsOptions = (options) => {
  if (options === null) {
    return undefined;
  }
  return options.map(option => {
    let name = '';
    name += option.get('firstname', '').trim() !== '' ? option.get('firstname', '').trim() : '';
    name += option.get('lastname', '').trim() !== '' ? ` ${option.get('lastname', '').trim()}` : '';
    return Immutable.Map({
      label: `${name.trim()} [${option.get('aid', '')}]`,
      value: option.get('aid', ''),
      id: option.getIn(['_id', '$id'], ''),
    })
  }).toJS();
}

const getServicesOptions = state => state.list.get('available_services', null);

const getTaxesOptions = state => state.list.get('available_taxRates', null);

const getProductsOptions = state => state.list.get('all_rates', null);

const getOptionsByListName = (state, props, listName = '') => state.list.get(listName, null);

const getEntitiesOptions = (state, props, entities = []) =>
  Immutable.Map().withMutations((optionsWithMutations) => {
    entities.forEach((entity) => {
      const entitiesName = getConfig(['systemItems', entity, 'itemsType'], '');
      optionsWithMutations.set(entity, state.list.get(`available_${entitiesName}`, Immutable.List()));
  });
});

const formatSelectOptions = (options, key) => {
  if (options === null) {
    return undefined;
  }
  return options.map(option => Immutable.Map({
    label: `${option.get('description', '')} (${option.get(key, '')})`,
    value: option.get(key, ''),
  }));
};

const selectUserNamesOptions = (options) => {
  if (options === null) {
    return undefined;
  }
  return options.map(user => user.get('username'));
};

const selectEntityTypesOptions = (options) => {
  if (options === null) {
    return undefined;
  }
  return options
    .filter(entity => entity.get('audited', false))
    .map(entity => ({
      key: entity.get('collection', ''),
      val: sentenceCase(entity.get('itemsName', '')),
    }))
    .toList()
    .push({
      key: 'Login',
      val: 'Login',
    });
};

const getPlansOptions = state => state.list.get('available_plans', null);

const selectPlayTypeOptions = (options) => {
  if (options === null) {
    return undefined;
  }
  return options.map((label, value) => Immutable.Map({ label, value }));
};

const getGroupsOptions = state => state.list.get('available_groups', null);

const selectGroupsOptions = (options) => {
  if (options === null) {
    return undefined;
  }
  return Immutable.Set().withMutations((optionsWithMutations) => {
    options.forEach((option) => {
      option.getIn(['include', 'groups'], Immutable.Map())
        .keySeq()
        .forEach((key) => {
          optionsWithMutations.add(key);
        });
    });
  }).toList();
};

const selectGroupsData = (options) => {
  if (options === null) {
    return undefined;
  }
  return Immutable.Map().withMutations((groupsWithMutations) => {
    options.forEach((option) => {
      option.getIn(['include', 'groups'], Immutable.Map())
        .forEach((groupData, groupName) => {
          groupsWithMutations.set(groupName, groupData);
        });
    });
  });
};

const getBucketsOptions = state => state.list.get('pp_includes', null);

const selectBucketsNames = (options) => {
  if (options === null) {
    return undefined;
  }
  return options.map(option => Immutable.Map({
    label: option.get('name', ''),
    value: option.get('name', ''),
  }));
};

const selectBucketsExternalIds = (options) => {
  if (options === null) {
    return undefined;
  }
  return options.map(option => Immutable.Map({
    label: option.get('external_id', ''),
    value: option.get('external_id', ''),
  }));
};

export const cyclesOptionsSelector = createSelector(
  getCyclesOptions,
  selectCyclesOptions,
);

export const entitiesOptionsSelector = createSelector(
  getEntitiesOptions,
  options => options,
);

export const eventRatesSelector = createSelector(
  getEventRates,
  rates => (rates === null ? undefined : rates),
);

export const suggestionsRatesSelector = createSelector(
  getSuggestionsRates,
  rates => (rates === null ? undefined : rates),
);

export const productsOptionsSelector = createSelector(
  getProductsOptions,
  () => 'key',
  formatSelectOptions,
);

export const listByNameSelector = createSelector(
  getOptionsByListName,
  () => 'key',
  formatSelectOptions,
);

export const accountsOptionsSelector = createSelector(
  getAccountsOptions,
  selectAccountsOptions,
);

export const servicesOptionsSelector = createSelector(
  getServicesOptions,
  () => 'name',
  formatSelectOptions,
);

export const taxesOptionsSelector = createSelector(
  getTaxesOptions,
  () => 'key',
  formatSelectOptions,
);

export const plansOptionsSelector = createSelector(
  getPlansOptions,
  () => 'name',
  formatSelectOptions,
);

export const getPlayTypeOptions = createSelector(
  availablePlaysLabelsSelector,
  selectPlayTypeOptions,
);

export const groupsOptionsSelector = createSelector(
  getGroupsOptions,
  selectGroupsOptions,
);

export const bucketsNamesSelector = createSelector(
  getBucketsOptions,
  selectBucketsNames,
);

export const bucketsSelectOptionsSelector = createSelector(
  getBucketsOptions,
  (options = null) => {
    if (options === null) {
      return undefined;
    }
    return options
      .map(option => ({
        label: `${option.get('name', '')} (${option.get('external_id', '')})`,
        value: option.get('external_id', ''),
        charging_by: option.get('charging_by', ''),
        charging_by_usaget: option.get('charging_by_usaget', ''),
        charging_by_usaget_unit: option.get('charging_by_usaget_unit', ''),
      }))
      .toArray();
  }
);

export const bucketsExternalIdsSelector = createSelector(
  getBucketsOptions,
  selectBucketsExternalIds,
);

export const auditlogSelector = createSelector(
  getAuditLogs,
  log => log
);

export const userNamesSelector = createSelector(
  getUserNamesOptions,
  selectUserNamesOptions,
);

export const auditEntityTypesSelector = createSelector(
  getAuditEntityTypesOptions,
  selectEntityTypesOptions,
);

export const groupsDataSelector = createSelector(
  getGroupsOptions,
  selectGroupsData,
);

export const calcNameSelector = createSelector(
  () => getConfig('queue_calculators', []),
  (calculators) => {
    const values = [false, ...calculators];
    return calculators
      .map((calculator, i) => Immutable.Map({
        label: sentenceCase(calculator),
        value: values[i],
      }));
  },
);

/**
 * get a list of services and their included groups
 * @param  {[List]} options [all services in the DB]
 * @return {[List]}         [List of the service names and their included groups]
 */
const selectServicesData = (options) => {
  if (options === null) {
    return undefined;
  }
  return Immutable.Map().withMutations((groupsWithMutations) => {
    options.forEach((option) => {
      groupsWithMutations.set(option.get('name', ''), option.getIn(['include', 'groups'], Immutable.Map()).keySeq().toArray());
    });
  });
};

/**
 * returns only property types that are being used in the config
 * @param  {[List]} groupsOptions [List of all the services and their groups]
 * @param  {[List]} propertyTypes [List of all property Types]
 * @return {[Set]}                [Set of property Types that are being used by services]
 */
const selectUsedPropertyTypes = (groupsOptions, propertyTypes) => {
  if (groupsOptions) {
    return Immutable.Set().withMutations((setWithMutations) => {
      groupsOptions.forEach((group) => {
        const groupsInService = group.getIn(['include', 'groups']);
        groupsInService.forEach((groupInService) => {
          setWithMutations.union(inferPropTypeFromUsageType(propertyTypes, groupInService.get('usage_types', Immutable.Map())));
        });
      });
    });
  }
  return Immutable.Set();
};

/**
 * returns all services and their groups
 */
export const servicesDataSelector = createSelector(
  getGroupsOptions,
  selectServicesData,
);

/**
 * returns all property types that are being used in the config
 */
export const propertyTypesSelector = createSelector(
  getGroupsOptions,
  propertyTypeSelector,
  selectUsedPropertyTypes,
);

