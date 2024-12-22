import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import {
  getCycleQuery,
  getEntitesQuery,
  getRebalanceAccountQuery,
  getProductsByKeysQuery,
  getCreditChargeQuery,
 } from '@/common/ApiQueries';
import { startProgressIndicator, finishProgressIndicator } from './progressIndicatorActions';
import { showSuccess } from './alertsActions';
import { saveEntity } from './entityActions';
import {
  getList,
  pushToList,
} from './listActions';
import moment from 'moment';

export const getSuggestionRates = eventRatesKeys => dispatch =>
  dispatch(pushToList('suggestions_products', getProductsByKeysQuery(eventRatesKeys.toArray(), { key: 1, rates: 1 })));

export const getCyclesDetails = (billrunKeys) => (dispatch) => {
  const queries = billrunKeys.map(billrunKey => getCycleQuery(billrunKey)).toArray();
  return apiBillRun(queries)
    .then(success => {
      const results = Immutable.fromJS(success)
        .get('data', Immutable.List())
        .filter(result => result.getIn(['data','status'], '') === 1)
        .map(result => Immutable.Map({
          key: result.getIn(['data','details', 0, 'billrun_key'], ''),
          status: result.getIn(['data','details', 0, 'cycle_status'], ''),
        }))
        .reduce((acc, item) => {
          return acc.set(item.get('key'), item.get('status'))
        }, Immutable.Map())
      return results;
    })
    .catch(error => {
      dispatch(apiBillRunErrorHandler(error));
      return Immutable.Map()
    });
}

export const rebalanceSuggestion = (suggestion) => (dispatch) => {
  dispatch(startProgressIndicator());
  const aid = suggestion.get('aid', '');
  let billrunKey = suggestion.get('billrun_key', null);
  if (!billrunKey) {
    billrunKey = suggestion.get('estimated_billrun', null);
  }
  const rate = suggestion.get('key', '');
  const query = getRebalanceAccountQuery(aid, billrunKey, rate);
  return apiBillRun(query)
    .then(success => {
      const newSuggestion = suggestion.set('status', 'accept');
      dispatch(saveEntity('suggestions', newSuggestion, 'update'));
      return dispatch(apiBillRunSuccessHandler(success, 'Rebalance request sent'));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error rebalancing customer')));
}

const buildQueryParams = (suggestion, type, aprice_field) => {
  const mult = (type === 'charge' ? 1 : (type === 'refund' ? -1 : 0));
  let params = [
    { aid : suggestion.get('aid', '')},
    { sid : suggestion.get('sid', '')},
    { aprice : mult * suggestion.get(aprice_field, '')},
    { usagev : 1},
    { credit_time: moment(suggestion.get('to', suggestion.get('max_urt_line', ''))).subtract(1, 'second').toISOString() },
    { label: `"${suggestion.get('invoice_label', '') ? suggestion.get('invoice_label', '') : suggestion.get('description', '')}" - correction`}
  ];

  //In the future more retroactive changes will be supported (for now only rate).   
  if(suggestion.get('recalculation_type', '') === 'rates'){
    params = [...params,
      { rate: suggestion.get('key', '') },
    ];
  }
  return params;
}

export const creditSuggestion = (suggestion) => (dispatch) => {
  dispatch(startProgressIndicator());
  const creditSuggestionParams = [
    { suggestion_stamp : suggestion.get('stamp', '')},
  ];
  const creditSuggestionQuery = getCreditChargeQuery(creditSuggestionParams);
  return apiBillRun(creditSuggestionQuery)
    .then(success => {
      const newSuggestion = suggestion.set('status', 'accept');    
      dispatch(saveEntity('suggestions', newSuggestion, 'update'));
      return dispatch(apiBillRunSuccessHandler(success, 'Credit request sent')); 
    }).catch(error => dispatch(apiBillRunErrorHandler(error, 'Error credit customer')));
}

export const rejectSuggestions = (suggestions) => (dispatch) => 
  suggestions.map((suggestion) => 
    dispatch(rejectSuggestion(suggestion))
  );

export const rejectSuggestion = (suggestion) => (dispatch) => {
  dispatch(startProgressIndicator());
  const newSuggestion = suggestion.set('status', 'reject');
  return dispatch(saveEntity('suggestions', newSuggestion, 'update'))
  .then((success) => {
    if (success.status === 1) {
      dispatch(finishProgressIndicator());
      dispatch(showSuccess(`Suggestion "${suggestion.get('description', '')}" successfully rejected`));
      return dispatch(getSuggestionsByAid(suggestion.get('aid', '')));
    }
    throw new Error(`Error reject suggestion "${suggestion.get('description', '')}"`);
  })
  .catch(error => dispatch(apiBillRunErrorHandler(error)));
}

export const getSuggestionsByAid = (aid) => (dispatch) => {
  const project = {
    // description: 1,
    // aid: 1,
    // sid: 1,
    // amount: 1,
    // status: 1,
    // firstname: 1,
    // lastname: 1,
    // billrun_key: 1,
    // key: 1,
  };
  const query = {
    aid,
  };
  const sort = {
    billrun_key: -1,
  }; 
  return dispatch(getList(`suggestions_${aid}`, getEntitesQuery('suggestions', project, query, sort)));
}
