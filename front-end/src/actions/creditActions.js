import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { getCreditChargeQuery } from '../common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';

export const creditCharge = params => (dispatch) => { // eslint-disable-line import/prefer-default-export
  dispatch(startProgressIndicator());
  const query = getCreditChargeQuery(params);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Success crediting')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error crediting')));
};
