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
jest.mock('@/components/PaymentFiles/UploadPaymentFileForm', () => () => null);

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

describe('ResponsePaymentFiles.onUploadTransactionsFileClickOK', () => {
  beforeEach(() => {
    sendTransactionsReceiveFile.mockClear();
    sendTransactionsReceiveFile.mockImplementation(() => () => Promise.resolve());
  });

  it('passes a PascalCase payment_gateway when the stored value is snake_case', async () => {
    const { instance } = buildInstance('manual_files');
    const fakeFile = { name: 'response-2026-04-19.csv' };

    await instance.onUploadTransactionsFileClickOK(Map({ file: fakeFile }));

    expect(sendTransactionsReceiveFile).toHaveBeenCalledTimes(1);
    expect(sendTransactionsReceiveFile).toHaveBeenCalledWith(
      'ManualFiles',
      'response',
      fakeFile,
      'transactions_response',
    );
  });

  it('leaves already-PascalCase keys unchanged (SaltLi → SaltLi)', async () => {
    const { instance } = buildInstance('SaltLi');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));

    expect(sendTransactionsReceiveFile.mock.calls[0][0]).toBe('SaltLi');
  });

  it('normalizes generic snake_case keys (custom_pg → CustomPg)', async () => {
    const { instance } = buildInstance('custom_pg');
    await instance.onUploadTransactionsFileClickOK(Map({ file: { name: 'x.csv' } }));

    expect(sendTransactionsReceiveFile.mock.calls[0][0]).toBe('CustomPg');
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
