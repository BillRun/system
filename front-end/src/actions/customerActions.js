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
  getEntityById,
  gotEntity,
  clearEntity,
  setCloneEntity,
  updateEntityField,
  deleteEntityField,
  fetchEntity,
} from './entityActions';
import { startProgressIndicator } from './progressIndicatorActions';


export const getCustomer = id => getEntityById('customer', 'accounts', id);

// export const getCustomerByAid = (aid) => getEntityByAid('customer', 'accounts', aid);
export const getCustomerByAid = (aid) => (dispatch) => {
  // const query = getEntitesQuery('accounts', {}, `aid=${aid}`);
  const query = getEntitesQuery('accounts', {}, {aid:aid});
  return dispatch(fetchEntity('customer', query));
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

export const saveCustomer = (customer, action) => saveEntity('accounts', customer, action);

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
