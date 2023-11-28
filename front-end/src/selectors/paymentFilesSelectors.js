import { createSelector } from 'reselect';
import { List, Map } from 'immutable';
import { formatSelectOptions } from '@/common/Util';

import { titleCase } from 'change-case';

import { paymentGatewaysSelector } from '@/selectors/settingsSelector'


const getRunningPaymentFiles = state => state.list.get('payment_running_files_list');

const getRunningRequestPaymentFiles = state => state.list.get('request_payment_running_files_list');

const getRunningResponsePaymentFiles = state => state.list.get('response_payment_running_files_list');

const getSelectedPaymentGateway = (state, props, source) => state.paymentsFiles.getIn([source, 'paymentGateway']);

const getSelectedFileType = (state, props, source) => state.paymentsFiles.getIn([source, 'fileType']);

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

export const isRunningRequestPaymentFilesSelector = createSelector(
  getRunningRequestPaymentFiles,
  (files = List()) => files.size,
);

export const isRunningResponsePaymentFilesSelector = createSelector(
  getRunningResponsePaymentFiles,
  (files = List()) => files.size,
);

export const paymentRequestFilesSelector = createSelector(
  paymentGatewaysSelector,
  paymentGateways => paymentGateways
    .filter(paymentGateway => paymentGateway.has('transactions_request')
      && !paymentGateway.get('transactions_request', List).isEmpty()
      && paymentGateway.get('custom', false)
    )
);

export const paymentRequestGatewayOptionsSelector = createSelector(
  paymentRequestFilesSelector,
  paymentFiles => paymentFiles.map(paymentFile => formatSelectOptions(Map({
    value: paymentFile.get('name', ''),
    label: paymentFile.get('title', titleCase(paymentFile.get('name', ''))),
  })))
  .toList()
  .toArray()
);

export const paymentResponseFilesSelector = createSelector(
  paymentGatewaysSelector,
  paymentGateways => paymentGateways
  .filter(paymentGateway => paymentGateway.has('transactions_response')
    && !paymentGateway.get('transactions_response', List).isEmpty()
    && paymentGateway.get('custom', false)
    )
  );
  
export const paymentResponseGatewayOptionsSelector = createSelector(
  paymentResponseFilesSelector,
  paymentFiles => paymentFiles.map(paymentFile => formatSelectOptions(Map({
    value: paymentFile.get('name', ''),
    label: paymentFile.get('title', titleCase(paymentFile.get('name', ''))),
  })))
  .toList()
  .toArray()
);

export const responseFileTypeOptionsOptionsSelector = createSelector(
  paymentResponseFilesSelector,
  paymentFiles => paymentFiles.reduce((accPaymentFiles, paymentFile) => 
    accPaymentFiles.set(paymentFile.get('name', ''), paymentFile
      .get('transactions_response', List())
      .filter(transactionRequest => transactionRequest.has('file_type'))
      .map(transactionRequest => formatSelectOptions(Map({
        value: transactionRequest.get('file_type', ''),
        label: transactionRequest.get('title', titleCase(transactionRequest.get('file_type', '')))
      })))
      .toArray()
    )
  , Map())
)


export const paymentFilesSelector = createSelector(
  paymentGatewaysSelector,
  paymentGateways => paymentGateways
    .filter(paymentGateway => paymentGateway.has('payments')
      && !paymentGateway.get('payments', List).isEmpty()
      && paymentGateway.get('custom', false)
    )
);

export const paymentGatewayOptionsSelector = createSelector(
  paymentFilesSelector,
  paymentFiles => paymentFiles.map(paymentFile => formatSelectOptions(Map({
    value: paymentFile.get('name', ''),
    label: paymentFile.get('title', 
      (paymentFile.get('name', '') === paymentFile.get('name', '').toUpperCase())
      ? paymentFile.get('name', '')
      : titleCase(paymentFile.get('name', ''))
    ),
  })))
  .toList()
  .toArray()
);

export const paymentFileTypeOptionsOptionsSelector = createSelector(
  paymentFilesSelector,
  paymentFiles => paymentFiles.reduce((accPaymentFiles, paymentFile) => 
    accPaymentFiles.set(paymentFile.get('name', ''), paymentFile
      .get('payments', List())
      .filter(transactionRequest => transactionRequest.has('file_type'))
      .map(transactionRequest => formatSelectOptions(Map({
        value: transactionRequest.get('file_type', ''),
        label: transactionRequest.get('title', titleCase(transactionRequest.get('file_type', '')))
      })))
      .toArray()
    )
  , Map())
)

export const paymentRequestFileTypeOptionsOptionsSelector = createSelector(
  paymentRequestFilesSelector,
  paymentFiles => paymentFiles.reduce((accPaymentFiles, paymentFile) => 
    accPaymentFiles.set(paymentFile.get('name', ''), paymentFile
      .get('transactions_request', List())
      .filter(transactionRequest => transactionRequest.has('file_type'))
      .map(transactionRequest => formatSelectOptions(Map({
        value: transactionRequest.get('file_type', ''),
        label: transactionRequest.get('title', titleCase(transactionRequest.get('file_type', '')))
      })))
      .toArray()
    )
  , Map())
)
