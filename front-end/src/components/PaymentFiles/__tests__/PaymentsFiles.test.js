// Upload forwards the raw `payment_gateway` + an explicit `source` built via
// `buildPaymentFileSource` (BE-equivalent ucwords+strip), so it matches `log.source`
// and the list query. Must NOT use `pascalCase` (breaks `ABC`/`AB_…`).

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
    // react-router 3.x hoists statics, so WrappedComponent of the inner HOC propagates.
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
jest.mock('@/components/PaymentFiles/UploadPaymentFileForm', () => () => null, { virtual: false });

// Selectors are only used by `mapStateToProps`, which we don't exercise (we unwrap
// the connected component and invoke a class method directly). Stubbing the module
// avoids pulling in the full selector graph and its reselect/immutable dependencies.
jest.mock('@/selectors/paymentFilesSelectors', () => ({
  paymentGatewayOptionsSelector: () => [],
  paymentFileTypeOptionsOptionsSelector: () => ({}),
  isRunningPaymentFilesSelector: () => 0,
  selectedPaymentGatewaySelector: () => '',
  selectedFileTypeSelector: () => '',
}));
jest.mock('@/selectors/reportSelectors', () => ({
  reportBillsFieldsSelector: () => [],
}));

jest.mock('@/actions/paymentFilesActions', () => ({
  setFileType: jest.fn(() => ({ type: 'SET_FILE_TYPE' })),
  setPaymentGateway: jest.fn(() => ({ type: 'SET_PAYMENT_GATEWAY' })),
  getRunningPaymentFiles: jest.fn(() => ({ type: 'GET_RUNNING' })),
  cleanRunningPaymentFiles: jest.fn(() => ({ type: 'CLEAN_RUNNING' })),
  cleanPaymentFilesTable: jest.fn(() => ({ type: 'CLEAN_TABLE' })),
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
import PaymentsFilesConnected from '@/components/PaymentFiles/PaymentsFiles';

// default export: withRouter(connect(...)). WrappedComponent is the raw class.
const PaymentsFiles = PaymentsFilesConnected.WrappedComponent;

const buildInstance = (paymentGateway, fileType = 'manual') => {
  const dispatch = jest.fn((arg) => (typeof arg === 'function' ? arg(dispatch, () => ({})) : arg));
  const instance = new PaymentsFiles({ dispatch, paymentGateway, fileType });
  return { instance, dispatch };
};

describe('PaymentsFiles.onUploadTransactionsFileClickOK — upload contract', () => {
  beforeEach(() => {
    sendTransactionsReceiveFile.mockClear();
    sendTransactionsReceiveFile.mockImplementation(() => () => Promise.resolve());
  });

  it('forwards the raw payment_gateway (snake_case preserved) and computes source as ucwords+strip+"Payments"', async () => {
    const { instance } = buildInstance('manual_files');
    const fakeFile = { name: 'payments-2026-04-19.csv' };

    await instance.onUploadTransactionsFileClickOK(Map({ file: fakeFile }));

    expect(sendTransactionsReceiveFile).toHaveBeenCalledTimes(1);
    expect(sendTransactionsReceiveFile).toHaveBeenCalledWith(
      'manual_files',
      'manual',
      fakeFile,
      'payments',
      'ManualFilesPayments',
    );
  });

  it('computes source correctly for other snake_case gateways (credit_card → CreditCardPayments)', async () => {
    const { instance } = buildInstance('credit_card', 'response');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'cc.csv' } }));

    const call = sendTransactionsReceiveFile.mock.calls[0];
    expect(call[0]).toBe('credit_card');
    expect(call[1]).toBe('response');
    expect(call[3]).toBe('payments');
    expect(call[4]).toBe('CreditCardPayments');
  });

  // Regression: "ABC" gateway — BE stores "ABCPayments", `pascalCase` gave "Abc".
  it('preserves all-caps gateway keys (ABC → ABCPayments), not pascalCase Abc', async () => {
    const { instance } = buildInstance('ABC');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'abc.csv' } }));

    const call = sendTransactionsReceiveFile.mock.calls[0];
    expect(call[0]).toBe('ABC');
    expect(call[4]).toBe('ABCPayments');
  });

  it('preserves embedded caps in snake keys (AB_data_files → ABDataFilesPayments)', async () => {
    const { instance } = buildInstance('AB_data_files');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'ab.csv' } }));

    expect(sendTransactionsReceiveFile.mock.calls[0][4]).toBe('ABDataFilesPayments');
  });

  it('leaves already-PascalCase gateways unchanged and builds source as <Gateway>Payments', async () => {
    const { instance } = buildInstance('FooBar');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));

    const call = sendTransactionsReceiveFile.mock.calls[0];
    expect(call[0]).toBe('FooBar');
    expect(call[4]).toBe('FooBarPayments');
  });

  it('always passes the "payments" discriminator (screen contract)', async () => {
    const { instance } = buildInstance('anything_at_all');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));
    expect(sendTransactionsReceiveFile.mock.calls[0][3]).toBe('payments');
  });

  it('forwards the file object stored under the form map "file" key', async () => {
    const { instance } = buildInstance('manual_files');
    const fakeFile = { name: 'bound.csv' };
    await instance.onUploadTransactionsFileClickOK(Map({ file: fakeFile }));
    expect(sendTransactionsReceiveFile.mock.calls[0][2]).toBe(fakeFile);
  });
});
