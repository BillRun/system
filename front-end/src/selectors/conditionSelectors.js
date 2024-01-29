import { createSelector } from 'reselect';
import Immutable from 'immutable';
import {
  createRateListNameByArgs,
} from '@/common/Util';
import {
  usageTypeSelector,
  fileTypeSelector,
  eventCodeSelector,
} from './settingsSelector';
import {
  listByNameSelector,
  productsOptionsSelector,
  cyclesOptionsSelector,
  plansOptionsSelector,
  servicesOptionsSelector,
  groupsOptionsSelector,
  calcNameSelector,
  bucketsNamesSelector,
  bucketsExternalIdsSelector,
  getPlayTypeOptions,
  taxesOptionsSelector,
} from './listSelectors';


const getOptionCallback = (state, props) => {
  const callback = props.config.getIn(['inputConfig', 'callback']);
  switch (callback) {
    case 'getCyclesOptions': return cyclesOptionsSelector(state, props);
    case 'getProductsOptions': {
      const callbackArgument = props.config.getIn(['inputConfig', 'callbackArgument'], Immutable.Map());
      if (!callbackArgument.isEmpty()) {
        const listName = createRateListNameByArgs(callbackArgument);
        return listByNameSelector(state, props, listName);
      }
      return productsOptionsSelector(state, props);
    }
    case 'getPlansOptions': return plansOptionsSelector(state, props);
    case 'getServicesOptions': return servicesOptionsSelector(state, props);
    case 'getGroupsOptions': return groupsOptionsSelector(state, props);
    case 'getUsageTypesOptions': return usageTypeSelector(state, props);
    case 'getBucketsOptions': return bucketsNamesSelector(state, props);
    case 'getBucketsExternalIdsOptions': return bucketsExternalIdsSelector(state, props);
    case 'getFileTypeOptions': return fileTypeSelector(state, props);
    case 'getPlayTypeOptions': return getPlayTypeOptions(state, props);
    case 'getCalcNameOptions': return calcNameSelector(state, props);
    case 'getEventCodeOptions': return eventCodeSelector(state, props);
    case 'getTaxesOptions': return taxesOptionsSelector(state, props);
    default: return undefined;
  }
};

export const selectOptionSelector = createSelector(
  getOptionCallback,
  options => options,
);
