import { List, Map } from 'immutable';
import { validateMandatoryField } from '@/actions/entityActions';
import { setFormModalError } from './guiStateActions/pageActions';
import { getList, clearList } from '@/actions/listActions';
import { clearItems, setListPage, clearNextPage } from '@/actions/entityListActions';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import {
  runningPaymentFilesListQuery,
  sendGenerateNewFileQuery,
  sendTransactionsReceiveFileQuery,
} from '@/common/ApiQueries';

export const actions = {
  SET_FILE_TYPE: 'SET_FILE_TYPE',
  SET_PAYMENT_GATEWAY: 'SET_PAYMENT_GATEWAY',
  CLEAR: 'CLEAR',
};

export const setFileType = (value, source) => ({
  type: actions.SET_FILE_TYPE,
  value,
  source,
});

export const setPaymentGateway = (value, source) => ({
  type: actions.SET_PAYMENT_GATEWAY,
  value,
  source,
});

export const clear = () => ({
  type: actions.CLEAR,
});

export const getRunningPaymentFiles = (paymentGateway, fileType, source) => (dispatch) => 
  dispatch(getList('payment_running_files_list', runningPaymentFilesListQuery(paymentGateway, fileType, source)));

export const cleanRunningPaymentFiles = () => (dispatch) => 
  dispatch(clearList('payment_running_files_list'));

export const cleanPaymentFilesTable = () => (dispatch) => {
  dispatch(clearItems('payments_files'));
  dispatch(setListPage('payments_files', 0));
  dispatch(clearNextPage('payments_files'));
}

export const validateGeneratePaymentFile = (paymentFile) => (dispatch) => {
  let isValid = true;
	const values = paymentFile.get('values', Map());
  const data = paymentFile.get('fields', List());
  data.forEach(field => {
    if (field.get('display', false) && field.get('editable', false)) {
      const path = field.get('field_name', '');
      const path_array = path.split('.').filter(part => part !== '');
      if (values.hasIn(path_array)) {
        const value = values.getIn(path_array)
        const hasError = validateMandatoryField(value, field)
        if (hasError !== true) {
          isValid = false;
          dispatch(setFormModalError(path, hasError));
        }
      }
    }
  });
  return isValid;
}

export const sendGenerateNewFile = (paymentGateway, fileType, data) => (dispatch) => {
  const query = sendGenerateNewFileQuery(paymentGateway, fileType, data);
  const successMessage = 'File creation initiated';
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, successMessage)))
    .catch(error => {
      dispatch(apiBillRunErrorHandler(error, 'Error'));
      return Promise.reject();
    });
}

export const sendTransactionsReceiveFile = (paymentGateway, fileType, file) => (dispatch) => {
  const query = sendTransactionsReceiveFileQuery(paymentGateway, fileType, file);
  const successMessage = 'Transaction response file was successfully uploaded';
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, successMessage)))
    .catch(error => {
      dispatch(apiBillRunErrorHandler(error, 'Error'));
      return Promise.reject();
    });
}
