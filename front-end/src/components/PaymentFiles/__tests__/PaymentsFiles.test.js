// FE contract: upload dispatches `sendTransactionsReceiveFile` with `pascalCase(payment_gateway)`.

import { Map } from 'immutable';

// Stub UI imports (ESM deps) — we only call a method on the class, no render.
jest.mock('@/components/Elements', () => ({
  WithTooltip: () => null,
  CreateButton: () => null,
}));
jest.mock('@/components/EntityList', () => () => null);
jest.mock('@/components/Field', () => () => null);
jest.mock('@/components/PaymentFiles/PaymentFileDetails', () => () => null);
jest.mock('@/components/PaymentFiles/UploadPaymentFileForm', () => () => null, { virtual: false });

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

describe('PaymentsFiles.onUploadTransactionsFileClickOK — payment_gateway normalization', () => {
  beforeEach(() => {
    sendTransactionsReceiveFile.mockClear();
    sendTransactionsReceiveFile.mockImplementation(() => () => Promise.resolve());
  });

  it('normalizes snake_case gateway "manual_files" to "ManualFiles" before upload', async () => {
    const { instance } = buildInstance('manual_files');
    const fakeFile = { name: 'payments-2026-04-19.csv' };

    await instance.onUploadTransactionsFileClickOK(Map({ file: fakeFile }));

    expect(sendTransactionsReceiveFile).toHaveBeenCalledTimes(1);
    expect(sendTransactionsReceiveFile).toHaveBeenCalledWith(
      'ManualFiles',
      'manual',
      fakeFile,
      'payments',
    );
  });

  it('normalizes other snake_case keys as well (credit_card → CreditCard)', async () => {
    const { instance } = buildInstance('credit_card', 'response');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'cc.csv' } }));

    expect(sendTransactionsReceiveFile.mock.calls[0][0]).toBe('CreditCard');
    expect(sendTransactionsReceiveFile.mock.calls[0][1]).toBe('response');
    expect(sendTransactionsReceiveFile.mock.calls[0][3]).toBe('payments');
  });

  it('is idempotent for keys already in PascalCase (SaltLi stays SaltLi)', async () => {
    const { instance } = buildInstance('SaltLi');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));

    expect(sendTransactionsReceiveFile.mock.calls[0][0]).toBe('SaltLi');
  });

  it('always passes the "payments" discriminator (screen contract)', async () => {
    const { instance } = buildInstance('anything_at_all');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));
    expect(sendTransactionsReceiveFile.mock.calls[0][3]).toBe('payments');
  });
});
