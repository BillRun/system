import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { startProgressIndicator } from './progressIndicatorActions';
import { saveEntity, gotEntitySource } from './entityActions';
import { fetchServiceByIdQuery } from '../common/ApiQueries';
import {
  getPlanConvertedIncludes,
  getPlanConvertedRates,
  convertServiceBalancePeriodToObject,
  convertServiceBalancePeriodToString,
  getConfig,
  convertToOldRecurrence,
  convertToNewRecurrence,
} from '@/common/Util';
import {
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';

export const GOT_SERVICE = 'GOT_SERVICE';
export const UPDATE_SERVICE = 'UPDATE_SERVICE';
export const DELETE_SERVICE_FIELD = 'DELETE_SERVICE_FIELD';
export const SAVE_SERVICE = 'SAVE_SERVICE';
export const CLEAR_SERVICE = 'CLEAR_SERVICE';
export const CLONE_RESET_SERVICE = 'CLONE_RESET_SERVICE';
export const ADD_GROUP_SERVICE = 'ADD_GROUP_SERVICE';
export const REMOVE_GROUP_SERVICE = 'REMOVE_GROUP_SERVICE';
export const SERVICE_PRODUCTS_REMOVE = 'SERVICE_PRODUCTS_REMOVE';
export const SERVICE_PRODUCTS_RATE_UPDATE_TO = 'SERVICE_PRODUCTS_RATE_UPDATE_TO';
export const SERVICE_PRODUCTS_RATE_UPDATE = 'SERVICE_PRODUCTS_RATE_UPDATE';
export const SERVICE_PRODUCTS_RATE_REMOVE = 'SERVICE_PRODUCTS_RATE_REMOVE';
export const SERVICE_PRODUCTS_RATE_ADD = 'SERVICE_PRODUCTS_RATE_ADD';
export const SERVICE_PRODUCTS_RATE_INIT = 'SERVICE_PRODUCTS_RATE_INIT';


const gotItem = item => ({
  type: GOT_SERVICE,
  item,
});

export const clearService = () => ({
  type: CLEAR_SERVICE,
});

export const updateService = (path, value) => ({
  type: UPDATE_SERVICE,
  path,
  value,
});

export const deleteServiceField = path => ({
  type: DELETE_SERVICE_FIELD,
  path,
});

export const addGroup = (groupName, usages, unit, value, shared, pooled, quantityAffected, products) => ({
  type: ADD_GROUP_SERVICE,
  groupName,
  usages,
  unit,
  value,
  shared,
  pooled,
  quantityAffected,
  products,
});

export const removeGroup = groupName => ({
  type: REMOVE_GROUP_SERVICE,
  groupName,
});

export const setCloneService = () => ({
  type: CLONE_RESET_SERVICE,
  uniquefields: ['name'],
});

const convertService = (getState, service, convertToBaseUnit, toSend) => {
  const state = getState();
  const usageTypesData = usageTypesDataSelector(state);
  const propertyTypes = propertyTypeSelector(state);
  const serviceIncludes = getPlanConvertedIncludes(propertyTypes, usageTypesData, service, convertToBaseUnit); // eslint-disable-line max-len
  const serviceWithNewRecurrence = convertToNewRecurrence(service);
  return serviceWithNewRecurrence.withMutations((itemWithMutations) => {
    if (!serviceIncludes.isEmpty()) {
      itemWithMutations.set('include', serviceIncludes);
    }
    if (toSend) { // convert item before send to server
      if (itemWithMutations.getIn(['balance_period', 'type'], '') === 'custom_period') {
        itemWithMutations.setIn(['price', 0, 'to'], 1);
        itemWithMutations.set('quantitative', false);
        itemWithMutations.set('prorated', false);
      }
      const balancePeriod = convertServiceBalancePeriodToString(itemWithMutations);
      if (['', 'default'].includes(balancePeriod)) {
        itemWithMutations.delete('balance_period');
      } else {
        itemWithMutations.set('balance_period', balancePeriod);
      }
    } else { // convert item received from server
      const balancePeriod = convertServiceBalancePeriodToObject(itemWithMutations);
      itemWithMutations.set('balance_period', balancePeriod);
    }
    // convert product price override by usage-type
    const rates = getPlanConvertedRates(propertyTypes, usageTypesData, service, toSend);
    if (!rates.isEmpty()) {
      itemWithMutations.set('rates', rates);
    }
  });
};

export const saveService = (service, action) => (dispatch, getState) => {
  let convertedService = convertService(getState, service, true, true);
  if (action === 'create' || (action === 'update' && convertedService.getIn(['recurrence', 'converted'], false))) {
    convertedService = convertToOldRecurrence(convertedService);
  } 
  return dispatch(saveEntity('services', convertedService, action));
};

export const getService = (id, setSource = false) => (dispatch, getState) => {
  dispatch(startProgressIndicator());
  const query = fetchServiceByIdQuery(id);
  return apiBillRun(query)
    .then((response) => {
      const item = response.data[0].data.details[0];
      // for back capability
      if (typeof item.price === 'undefined' || !Array.isArray(item.price)) {
        item.price = [{
          from: 0,
          to: getConfig('serviceCycleUnlimitedValue', 'UNLIMITED'),
          price: typeof item.price === 'undefined' ? '' : item.price,
        }];
      }
      item.originalValue = item.from;
      const service = Immutable.fromJS(item);
      const convertedService = convertService(getState, service, false, false).toJS();
      dispatch(gotItem(convertedService));
      if (setSource) {
        dispatch(gotEntitySource('service', convertedService));
      }
      return dispatch(apiBillRunSuccessHandler(response));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving Entity')));
};
