import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { startProgressIndicator } from './progressIndicatorActions';
import {
  fetchReportByIdQuery,
  getReportQuery,
  getCyclesQuery,
  getPlansKeysQuery,
  getServicesKeysWithInfoQuery,
  getProductsKeysQuery,
  getAllGroupsQuery,
  getPrepaidIncludesQuery,
  getEntitesQuery,
 } from '../common/ApiQueries';
import {
  actions as entityActions,
  saveEntity,
  deleteEntity,
  clearEntity,
  updateEntityField,
  deleteEntityField,
  gotEntity,
  gotEntitySource,
} from './entityActions';
import {
  getList as getEntityList,
  clearItems,
  setListPage,
  setListSize,
} from './entityListActions';
import {
  getList,
  gotList,
  addToList,
} from './listActions';
import { getSettings } from './settingsActions';
import {
  getConfig,
  createRateListNameByArgs,
} from '@/common/Util';


export const reportTypes = {
  SIMPLE: 0,
  GROPED: 1,
};

export const setCloneReport = () => ({
  type: entityActions.CLONE_RESET_ENTITY,
  collection: 'reports',
  uniquefields: ['key', 'user', 'creation_time'],
});

export const clearReport = () => clearEntity('reports');

export const saveReport = (item, action) => saveEntity('reports', item, action);

export const deleteReport = item => deleteEntity('reports', item);

export const updateReport = (path, value) => updateEntityField('reports', path, value);

export const deleteReportValue = path => deleteEntityField('reports', path);

export const getReport = id => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = fetchReportByIdQuery(id);
  return apiBillRun(query)
    .then((response) => {
      const item = response.data[0].data.details[0];
      const outputFormats = getConfig(['reports', 'outputFormats'], Immutable.List());
      if (item.formats) {
        const convertedFormats = item.formats.map((format) => {
          if (outputFormats.find(outputFormat => outputFormat.get('id') === format.op, null, Immutable.Map()).has('valueTypes')) {
            return ({
              field: format.field,
              op: format.op,
              value: format.value.substr(0, format.value.indexOf(' ')),
              type: format.value.substr(format.value.indexOf(' ') + 1),
            });
          }
          return format;
        });
        item.formats = convertedFormats;
      }
      dispatch(gotEntity('reports', item));
      dispatch(gotEntitySource('reports', item));
      return dispatch(apiBillRunSuccessHandler(response));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving report')));
};


export const getReportData = data => getEntityList('reportData', getReportQuery(data));

export const clearReportData = () => clearItems('reportData');

export const setReportDataListPage = num => setListPage('reportData', num);

export const setReportDataListSize = num => setListSize('reportData', num);

export const getCyclesOptions = () => getList('cycles_list', getCyclesQuery());

export const getPlansOptions = () => getList('available_plans', getPlansKeysQuery());

export const getServicesOptions = () => getList('available_services', getServicesKeysWithInfoQuery());

export const getProductsOptions = (query = Immutable.Map()) => {
  if (query.isEmpty()) {
    return getList('all_rates', getProductsKeysQuery());
  }
  const listName = createRateListNameByArgs(query);
  return getList(listName, getProductsKeysQuery({ key: 1, description: 1 }, query));
};

export const getUsageTypesOptions = () => dispatch => dispatch(getSettings('usage_types'));

export const getBucketsOptions = () => getList('pp_includes', getPrepaidIncludesQuery());

export const getFileTypesOptions = () => dispatch => dispatch(getSettings('file_types'));

export const getEventCodeOptions = () => dispatch => dispatch(getSettings('events'));

export const getPlayTypeOptions = () => dispatch => dispatch(getSettings('plays'));

export const getTaxesOptions = () =>
  getList('available_taxRates', getEntitesQuery('taxes', { key: 1, description: 1 }));

export const getGroupsOptions = () => dispatch => apiBillRun(getAllGroupsQuery())
  .then((success) => {
    try {
      const collection = 'available_groups';
      dispatch(gotList(collection, success.data[0].data.details));
      dispatch(addToList(collection, success.data[1].data.details));
      return dispatch(apiBillRunSuccessHandler(success));
    } catch (e) {
      throw new Error('Error retrieving list');
    }
  })
  .catch(error => dispatch(apiBillRunErrorHandler(error, 'Network error - please refresh and try again')));
