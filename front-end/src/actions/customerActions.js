import Immutable from 'immutable';
import uuid from 'uuid';

import {
  apiBillRun,
  apiBillRunErrorHandler,
  apiBillRunSuccessHandler,
} from '@/common/Api';
import {
  getEntityByIdQuery,
  getRebalanceAccountQuery,
  getCollectionDebtQuery,
  getEntitesQuery,
} from '@/common/ApiQueries';
import {
  saveEntity,
  gotEntity,
  gotEntitySource,
  clearEntity,
  setCloneEntity,
  updateEntityField,
  deleteEntityField,
  getEntity,
  apiTimeOutMessage,
} from './entityActions';
import { startProgressIndicator } from './progressIndicatorActions';


// export const getCustomer = id => getEntityById('customer', 'accounts', id);
export const getCustomer = id => (dispatch, getState) => {
  dispatch(startProgressIndicator());
  const query = getEntityByIdQuery('accounts', id);

  return apiBillRun(query, { timeOutMessage: apiTimeOutMessage })
    .then((response) => {
      const item = response.data[0].data.details[0];
      item.originalValue = item.from;
      const customer = convertCustomer(getState, Immutable.fromJS(item), false).toJS();
      dispatch(gotEntity('customer', customer));
      dispatch(gotEntitySource('customer', customer));
      return dispatch(apiBillRunSuccessHandler(response));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving Entity')));
};

export const getCustomerByAid = (aid) => {
  const query = getEntitesQuery('accounts', {}, {aid});
  return getEntity('customer', query);
};

export const getSubscription = id => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = getEntityByIdQuery('subscribers', id);
  return apiBillRun(query)
    .then((response) => {
      const item = response.data[0].data.details[0];
      item.originalValue = item.from;
      dispatch(gotEntity('subscription', item));
      return dispatch(apiBillRunSuccessHandler(response));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving subscriber')));
};

export const saveCustomer = (customer, action) => (dispatch, getState) => dispatch(saveEntity(
  'accounts', convertCustomer(getState, customer, true), action
));

const convertCustomer = (getState, customer, toSend) => {
  if (toSend) {
    return customer.update('services', Immutable.List(),
      services => (services ? services.map(service => service.delete('ui_flags')) : Immutable.List()),
    );
  }
  const state = getState();
  const allServices = state.list.get('available_services', Immutable.List()) || Immutable.List();
  return customer.update('services', Immutable.List(), (services) => {
    if (services.isEmpty()) {
      return Immutable.List();
    }
    return services.map((service) => {
      const isBalancePeriod = allServices.find(
        allService => allService.get('name', '') === service.get('name', ''),
        null,
        Immutable.Map(),
      ).get('balance_period', 'default') !== 'default';
      const uiFlags = Immutable.Map({
        balance_period: isBalancePeriod,
        serviceId: uuid.v4(),
      });
      return service.set('ui_flags', uiFlags);
    });
  });
}

export const setCloneSubscription = () => setCloneEntity('subscription', 'subscription');

export const saveSubscription = (subscription, action) => dispatch =>
  dispatch(saveEntity('subscribers', subscription, action))
    .then(response => Object.assign(response, { subscription, action }));

export const updateCustomerField = (path, value) => updateEntityField('customer', path, value);

export const removeCustomerField = path => deleteEntityField('customer', path);

export const clearCustomer = () => clearEntity('customer');

export const rebalanceAccount = (aid, billrunKeys) => (dispatch) => {
  dispatch(startProgressIndicator());
  const queries = [];
  for (const billrunKey of billrunKeys.split(',')) {
    queries.push(getRebalanceAccountQuery(aid, billrunKey));
  }

  return apiBillRun(queries)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Customer rebalance request sent')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error rebalancing customer')));
};

export const getCollectionDebt = aid => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = getCollectionDebtQuery(aid);
  return apiBillRun(query)
    .then(response => dispatch(apiBillRunSuccessHandler(response)))
    .catch(error => dispatch(apiBillRunErrorHandler(error)));
};
