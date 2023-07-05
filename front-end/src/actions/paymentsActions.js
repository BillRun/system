import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { getOfflinePaymentQuery } from '../common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';

export const payOffline = (method, aid, amount, payerName, chequeNo) => (dispatch) => { // eslint-disable-line import/prefer-default-export
  dispatch(startProgressIndicator());
  const query = getOfflinePaymentQuery(method, aid, amount, payerName, chequeNo);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Payment sent successfully!')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error paying')));
};
