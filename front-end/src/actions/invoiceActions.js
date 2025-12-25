import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import {
  generateOneTimeInvoiceQuery,
  generateOneTimeInvoiceExpectedQuery,
} from '@/common/ApiQueries';
import {
  clearEntity,
  updateEntityField,
} from './entityActions';
import { getEntityByIdQuery} from '@/common/ApiQueries';


export const generateOneTimeInvoice = (aid, lines, invoiceType, sendMail = false, note = '', invoiceUnixtime = '') => (dispatch) => {
  const query = generateOneTimeInvoiceQuery(aid, lines, invoiceType, sendMail, note, invoiceUnixtime);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Immediate invoice successfully generated')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error generating the invoice')))
}

export const generateOneTimeInvoiceExpected = (aid, lines, note = '', invoiceUnixtime = '') => (dispatch) => {
  const query = generateOneTimeInvoiceExpectedQuery(aid, lines, note, invoiceUnixtime);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, false)))
}

export const clearImmediateInvoice = () => clearEntity('charge-invoice');

export const getImmediateInvoiceCustomer = (id) => dispatch => {
  const query = getEntityByIdQuery('accounts', id);
  return apiBillRun(query)
    .then((response) => {
      const customer = response?.data[0]?.data?.details[0] || {};
      return dispatch(updateImmediateInvoiceCustomer(Immutable.fromJS(customer)));
    })
    .catch(error => dispatch(updateImmediateInvoiceCustomer(Immutable.Map())));
};

export const updateImmediateInvoiceCustomer = value => updateEntityField('charge-invoice', 'customer', value);

export const updateImmediateInvoiceLines = value => updateEntityField('charge-invoice', 'lines', value);

export const updateImmediateInvoiceId = value => updateEntityField('charge-invoice', 'id', value);

export const getRefundInvoiceCustomer = (id) => dispatch => {
  const query = getEntityByIdQuery('accounts', id);
  return apiBillRun(query)
    .then((response) => {
      const customer = response?.data[0]?.data?.details[0] || {};
      return dispatch(updateRefundInvoiceCustomer(Immutable.fromJS(customer)));
    })
    .catch(error => dispatch(updateRefundInvoiceCustomer(Immutable.Map())));
};

export const updateRefundInvoiceCustomer = customer => updateEntityField('refund-invoice', 'customer', customer);

export const clearRefundInvoice = () => clearEntity('refund-invoice');

export const updateRefundInvoiceLines = line => updateEntityField('refund-invoice', 'lines', Immutable.List([line]));

export const updateRefundInvoiceReason = text => updateEntityField('refund-invoice', 'note', text);

export const updateRefundInvoiceUnixtime = unixtime => updateEntityField('refund-invoice', 'invoice_unixtime', unixtime);
