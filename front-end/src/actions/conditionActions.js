import Immutable from 'immutable';
import {
  apiBillRun,
  apiBillRunErrorHandler,
  apiBillRunSuccessHandler,
} from '../common/Api';
import {
  getPlansKeysQuery,
  getTaxRatesQuery,
  getServicesKeysWithInfoQuery,
  getCyclesQuery,
  getPrepaidIncludesQuery,
  getProductsKeysQuery,
  getAllGroupsQuery,
} from '../common/ApiQueries';
import {
  getSettings,
} from './settingsActions';
import {
  getList,
  gotList,
  addToList,
} from './listActions';
import {
  createRateListNameByArgs,
} from '@/common/Util';


export const optionsLoaders = {
  getPlansOptions: () => getList('available_plans', getPlansKeysQuery()),
  getCyclesOptions: () => getList('cycles_list', getCyclesQuery()),
  getServicesOptions: () => getList('available_services', getServicesKeysWithInfoQuery()),
  getUsageTypesOptions: () => getSettings('usage_types'),
  getBucketsOptions: () => getList('pp_includes', getPrepaidIncludesQuery()),
  getBucketsExternalIdsOptions: () => getList('pp_includes', getPrepaidIncludesQuery()),
  getFileTypeOptions: () => getSettings('file_types'),
  getPlayTypeOptions: () => getSettings('plays'),
  getEventCodeOptions: () => getSettings('events'),
  getTaxesOptions: () => getList('available_taxRates', getTaxRatesQuery),
  getGroupsOptions: () => dispatch => apiBillRun(getAllGroupsQuery())
    .then((success) => {
      try {
        const collection = 'available_groups';
        dispatch(gotList(collection, success.data[0].data.details));
        dispatch(addToList(collection, success.data[1].data.details));
        return dispatch(apiBillRunSuccessHandler(success));
      } catch (e) {
        throw new Error("Error retrieving 'Groups' options");
      }
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error))),
  getProductsOptions: (query = Immutable.Map()) => (dispatch) => {
    if (query.isEmpty()) {
      return dispatch(getList('all_rates', getProductsKeysQuery()));
    }
    const listName = createRateListNameByArgs(query);
    return dispatch(getList(listName, getProductsKeysQuery({ key: 1, description: 1 }, query)));
  },
  // Callback for all unsupported operation loaders
  unknownCallback: (callbackArgs) => {
    console.log('unsupported select options callback, data: ', callbackArgs)
  }
}
