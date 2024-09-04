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


export const generateOneTimeInvoice = (aid, lines, invoiceType, sendMail = false) => (dispatch) => {
  const query = generateOneTimeInvoiceQuery(aid, lines, invoiceType, sendMail);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Immediate invoice successfully generated')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error generating the invoice')))
}

export const generateOneTimeInvoiceExpected = (aid, lines, sendMail = false) => (dispatch) => {
  const query = generateOneTimeInvoiceExpectedQuery(aid, lines, sendMail);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, false)))
}

export const clearImmediateInvoice = () => clearEntity('immediate-invoice');

export const getImmediateInvoiceCustomer = (id) => dispatch => {
  const query = getEntityByIdQuery('accounts', id);
  return apiBillRun(query)
    .then((response) => {
      const customer = response?.data[0]?.data?.details[0] || {};
      return dispatch(updateImmediateInvoiceCustomer(Immutable.fromJS(customer)));
    })
    .catch(error => dispatch(updateImmediateInvoiceCustomer(Immutable.Map())));
};

export const updateImmediateInvoiceCustomer = value => updateEntityField('immediate-invoice', 'customer', value);

export const updateImmediateInvoiceLines = value => updateEntityField('immediate-invoice', 'lines', value);

export const updateImmediateInvoiceId = value => updateEntityField('immediate-invoice', 'id', value);