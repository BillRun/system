// FE contract: upload dispatches `sendTransactionsReceiveFile` with the raw
// `payment_gateway` (so the BE keeps the canonical snake_case key) PLUS an explicit
// `source` parameter computed on the FE as `pascalCase(payment_gateway) + 'TransactionsResponse'`.

import { Map } from 'immutable';

// Stub redux HOCs — we exercise the raw class via `.WrappedComponent`, so `connect`
// and `withRouter` just need to be pass-through no-ops that preserve the inner class
// on `.WrappedComponent` (which is what the component under test reads).
jest.mock('react-redux', () => ({
  connect: () => (Component) => {
    const Wrapped = (props) => null; // no render path used in these tests
    Wrapped.WrappedComponent = Component;
    return Wrapped;
  },
}));
jest.mock('react-router', () => ({
  withRouter: (Component) => {
    const Wrapped = (props) => null;
    Wrapped.WrappedComponent = Component.WrappedComponent || Component;
    return Wrapped;
  },
}));

// Stub UI imports (ESM deps) — we only call a method on the class, no render.
jest.mock('@/components/Elements', () => ({
  WithTooltip: () => null,
  CreateButton: () => null,
}));
jest.mock('@/components/EntityList', () => () => null);
jest.mock('@/components/Field', () => () => null);
jest.mock('@/components/PaymentFiles/PaymentFileDetails', () => () => null);
jest.mock('@/components/PaymentFiles/UploadPaymentFileForm', () => () => null);

// Selectors are only used by `mapStateToProps`, which we don't exercise (we unwrap
// the connected component and invoke a class method directly). Stubbing the module
// avoids pulling in the full selector graph and its reselect/immutable dependencies.
jest.mock('@/selectors/paymentFilesSelectors', () => ({
  paymentResponseGatewayOptionsSelector: () => [],
  responseFileTypeOptionsOptionsSelector: () => ({}),
  isRunningResponsePaymentFilesSelector: () => 0,
  selectedPaymentGatewaySelector: () => '',
  selectedFileTypeSelector: () => '',
}));
jest.mock('@/selectors/reportSelectors', () => ({
  reportBillsFieldsSelector: () => [],
}));

jest.mock('@/actions/paymentFilesActions', () => ({
  setFileType: jest.fn(() => ({ type: 'SET_FILE_TYPE' })),
  setPaymentGateway: jest.fn(() => ({ type: 'SET_PAYMENT_GATEWAY' })),
  getRunningResponsePaymentFiles: jest.fn(() => ({ type: 'GET_RUNNING_RESP' })),
  cleanResponsePaymentFilesTable: jest.fn(() => ({ type: 'CLEAN_RESP_TABLE' })),
  cleanRunningResponsePaymentFiles: jest.fn(() => ({ type: 'CLEAN_RUNNING_RESP' })),
  sendTransactionsReceiveFile: jest.fn(() => () => Promise.resolve()),
}));
jest.mock('@/actions/settingsActions', () => ({
  getSettings: jest.fn(() => ({ type: 'GET_SETTINGS' })),
}));
jest.mock('@/actions/guiStateActions/pageActions', () => ({
  showFormModal: jest.fn(() => ({ type: 'SHOW_MODAL' })),
  setPageTitle: jest.fn(() => ({ type: 'SET_TITLE' })),
}));
jest.mock('@/actions/entityActions', () => ({
  gotEntity: jest.fn(() => ({ type: 'GOT_ENTITY' })),
}));

// eslint-disable-next-line import/first
import { sendTransactionsReceiveFile } from '@/actions/paymentFilesActions';
// eslint-disable-next-line import/first
import ResponsePaymentFilesConnected from '@/components/PaymentFiles/ResponsePaymentFiles';

// default export: withRouter(connect(...)). WrappedComponent is the raw class.
const ResponsePaymentFiles = ResponsePaymentFilesConnected.WrappedComponent;

const buildInstance = (paymentGateway, fileType = 'response') => {
  const dispatch = jest.fn((arg) => (typeof arg === 'function' ? arg(dispatch, () => ({})) : arg));
  const instance = new ResponsePaymentFiles({ dispatch, paymentGateway, fileType });
  return { instance, dispatch };
};

describe('ResponsePaymentFiles.onUploadTransactionsFileClickOK — upload contract', () => {
  beforeEach(() => {
    sendTransactionsReceiveFile.mockClear();
    sendTransactionsReceiveFile.mockImplementation(() => () => Promise.resolve());
  });

  it('forwards the raw payment_gateway and computes source as PascalCase+"TransactionsResponse"', async () => {
    const { instance } = buildInstance('manual_files');
    const fakeFile = { name: 'response-2026-04-19.csv' };

    await instance.onUploadTransactionsFileClickOK(Map({ file: fakeFile }));

    expect(sendTransactionsReceiveFile).toHaveBeenCalledTimes(1);
    expect(sendTransactionsReceiveFile).toHaveBeenCalledWith(
      'manual_files',
      'response',
      fakeFile,
      'transactions_response',
      'ManualFilesTransactionsResponse',
    );
  });

  it('leaves already-PascalCase gateways unchanged and builds source as <Gateway>TransactionsResponse', async () => {
    const { instance } = buildInstance('SaltLi');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));

    const call = sendTransactionsReceiveFile.mock.calls[0];
    expect(call[0]).toBe('SaltLi');
    expect(call[4]).toBe('SaltLiTransactionsResponse');
  });

  it('computes source correctly for generic snake_case gateways (custom_pg → CustomPgTransactionsResponse)', async () => {
    const { instance } = buildInstance('custom_pg');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));

    const call = sendTransactionsReceiveFile.mock.calls[0];
    expect(call[0]).toBe('custom_pg');
    expect(call[4]).toBe('CustomPgTransactionsResponse');
  });

  it('always sends "transactions_response" as the 4th argument (screen discriminator)', async () => {
    const { instance } = buildInstance('anything_at_all');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));
    expect(sendTransactionsReceiveFile.mock.calls[0][3]).toBe('transactions_response');
  });

  it('forwards the file from the form Map under the "file" key', async () => {
    const { instance } = buildInstance('manual_files');
    const fakeFile = { name: 'bound-to-form.csv' };
    await instance.onUploadTransactionsFileClickOK(Map({ file: fakeFile }));
    expect(sendTransactionsReceiveFile.mock.calls[0][2]).toBe(fakeFile);
  });
});
