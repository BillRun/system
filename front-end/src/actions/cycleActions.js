import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import {
  getRunCycleQuery,
  getResetCycleQuery,
  getConfirmCycleInvoiceQuery,
  getConfirmCycleAllQuery,
  getChargeAllCycleQuery,
  getConfirmationOperationAllQuery,
  getConfirmationOperationInvoiceQuery,
} from '../common/ApiQueries';
import { startProgressIndicator, finishProgressIndicator } from './progressIndicatorActions';

export const runBillingCycle = (billrunKey, rerun = false, generatePdf = true) => (dispatch) => { // eslint-disable-line import/prefer-default-export
  dispatch(startProgressIndicator());
  const query = getRunCycleQuery(billrunKey, rerun, generatePdf);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Cycle started successfully!')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error running cycle')));
};

export const runResetCycle = billrunKey => (dispatch) => { // eslint-disable-line import/prefer-default-export
  dispatch(startProgressIndicator());
  const query = getResetCycleQuery(billrunKey);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Cycle reseted successfully!')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error resetting cycle')));
};

export const confirmCycleInvoice = (billrunKey, invoiceId) => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = getConfirmCycleInvoiceQuery(billrunKey, invoiceId);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Confirming invoice...')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error confirming invoice')));
};

export const confirmCycle = billrunKey => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = getConfirmCycleAllQuery(billrunKey);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Confirming cycle...')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error confirming cycle')));
};

export const chargeAllCycle = () => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = getChargeAllCycleQuery();
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Charged all success!')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error charging all')));
};

export const getConfirmationAllStatus = () => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = getConfirmationOperationAllQuery();
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Cannot get cycle confirmation status')));
};

export const getConfirmationInvoicesStatus = invoiceIds => (dispatch) => {
  dispatch(startProgressIndicator());
  const queries = [];
  invoiceIds.forEach(invoiceId => queries.push(getConfirmationOperationInvoiceQuery(invoiceId)));
  return apiBillRun(queries)
    .then((success) => {
      dispatch(finishProgressIndicator());
      return ({ status: 1, data: success.data });
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Cannot get invoice confirmation status')));
};
