import { createSelector } from 'reselect';
import { List, Map } from 'immutable';
import { formatSelectOptions } from '@/common/Util';

import { sentenceCase } from 'change-case';

import { paymentGatewaysSelector } from '@/selectors/settingsSelector'


const getRunningPaymentFiles = state => state.list.get('payment_running_files_list');

const getSelectedPaymentGateway = state => state.paymentsFiles.get('paymentGateway');

const getSelectedFileType = state => state.paymentsFiles.get('fileType');

export const selectedPaymentGatewaySelector = createSelector(
  getSelectedPaymentGateway,
  selectedPaymentGateway => selectedPaymentGateway,
);

export const selectedFileTypeSelector = createSelector(
  getSelectedFileType,
  selectedFileType => selectedFileType,
);

export const isRunningPaymentFilesSelector = createSelector(
  getRunningPaymentFiles,
  (files = List()) => files.size,
);

export const paymentFilesSelector = createSelector(
  paymentGatewaysSelector,
  paymentGateways => paymentGateways
    .filter(paymentGateway => paymentGateway.has('transactions_request')
      && !paymentGateway.get('transactions_request', List).isEmpty()
      && paymentGateway.get('custom', false)
    )
);

export const paymentGatewayOptionsSelector = createSelector(
  paymentFilesSelector,
  paymentFiles => paymentFiles.map(paymentFile => formatSelectOptions(Map({
    value: paymentFile.get('name', ''),
    label: paymentFile.get('title', sentenceCase(paymentFile.get('name', ''))),
  })))
  .toList()
  .toArray()
);

export const fileTypeOptionsOptionsSelector = createSelector(
  paymentFilesSelector,
  paymentFiles => paymentFiles.reduce((accPaymentFiles, paymentFile) => 
    accPaymentFiles.set(paymentFile.get('name', ''), paymentFile
      .get('transactions_request', List())
      .filter(transactionRequest => transactionRequest.has('file_type'))
      .map(transactionRequest => formatSelectOptions(Map({
        value: transactionRequest.get('file_type', ''),
        label: transactionRequest.get('title', sentenceCase(transactionRequest.get('file_type', '')))
      })))
      .toArray()
    )
  , Map())
)
