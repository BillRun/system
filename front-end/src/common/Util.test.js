import Immutable from 'immutable';
import {
  getFieldEntityKey,
  toImmutableList,
  isValueOn,
  ucwordsStrip,
  buildPaymentFileSource,
} from './Util';

it('getFieldEntityKey', () => {
  expect(getFieldEntityKey('account')).toEqual('customer');
  expect(getFieldEntityKey('accounts')).toEqual('customer');
  expect(getFieldEntityKey('customer')).toEqual('customer');
  expect(getFieldEntityKey('line')).toEqual('usage');
  expect(getFieldEntityKey('lines')).toEqual('usage');
  expect(getFieldEntityKey('usage')).toEqual('usage');
  expect(getFieldEntityKey('non-exists')).toEqual('non-exists');
});


it('toImmutableList', () => {
  expect(toImmutableList('')).toEqual(Immutable.List());
  expect(toImmutableList(undefined)).toEqual(Immutable.List());
  expect(toImmutableList(null)).toEqual(Immutable.List());
  expect(toImmutableList([])).toEqual(Immutable.List());
  expect(toImmutableList(Immutable.Map())).toEqual(Immutable.List());
  expect(toImmutableList(Immutable.List())).toEqual(Immutable.List());

  expect(toImmutableList(Immutable.List(['x']))).toEqual(Immutable.List(['x']));
  expect(toImmutableList(['x'])).toEqual(Immutable.List(['x']));
  expect(toImmutableList('x')).toEqual(Immutable.List(['x']));
  expect(toImmutableList({x:'x'})).toEqual(Immutable.List([{x:'x'}]));
  expect(toImmutableList(Immutable.Map({x:'x'}))).toEqual(Immutable.List(['x']));
});


it('isValueOn', () => {
  expect(isValueOn('')).toEqual(false);
  expect(isValueOn(undefined)).toEqual(false);
  expect(isValueOn(null)).toEqual(false);
  expect(isValueOn([])).toEqual(false);
  expect(isValueOn({})).toEqual(false);
  expect(isValueOn(true)).toEqual(true);
  expect(isValueOn('true')).toEqual(true);
  expect(isValueOn('TRUE')).toEqual(true);
  expect(isValueOn(false)).toEqual(false);
  expect(isValueOn('false')).toEqual(false);
  expect(isValueOn('FALSE')).toEqual(false);
  expect(isValueOn()).toEqual(false);
  expect(isValueOn(0)).toEqual(false);
  expect(isValueOn(1)).toEqual(true);
  expect(isValueOn('0')).toEqual(false);
  expect(isValueOn('1')).toEqual(true);
  expect(isValueOn('on')).toEqual(true);
  expect(isValueOn('off')).toEqual(false);
  expect(isValueOn('ON')).toEqual(true);
  expect(isValueOn('OFF')).toEqual(false);
  expect(isValueOn('yes')).toEqual(true);
  expect(isValueOn('no')).toEqual(false);
  expect(isValueOn('YES')).toEqual(true);
  expect(isValueOn('NO')).toEqual(false);
  expect(isValueOn('Y')).toEqual(true);
  expect(isValueOn('N')).toEqual(false);
});

// Mirrors BE `str_replace('_', '', ucwords($str, '_'))`.
it('ucwordsStrip', () => {
  expect(ucwordsStrip('manual_files')).toEqual('ManualFiles');
  expect(ucwordsStrip('credit_card')).toEqual('CreditCard');
  expect(ucwordsStrip('payments')).toEqual('Payments');
  expect(ucwordsStrip('transactions_response')).toEqual('TransactionsResponse');
  // already-capitalised input is preserved (NOT lower-cased like pascalCase would)
  expect(ucwordsStrip('ABC')).toEqual('ABC');
  expect(ucwordsStrip('AB_data_files')).toEqual('ABDataFiles');
  expect(ucwordsStrip('FooBar')).toEqual('FooBar');
  // edge cases
  expect(ucwordsStrip('')).toEqual('');
  expect(ucwordsStrip()).toEqual('');
  expect(ucwordsStrip('a__b')).toEqual('AB'); // empty middle segment dropped, no crash
});

// `log.source` = ucwordsStrip(payment_gateway) + ucwordsStrip(payments_file_type).
it('buildPaymentFileSource', () => {
  expect(buildPaymentFileSource('manual_files', 'payments')).toEqual('ManualFilesPayments');
  expect(buildPaymentFileSource('ABC', 'payments')).toEqual('ABCPayments');
  expect(buildPaymentFileSource('AB_data_files', 'payments')).toEqual('ABDataFilesPayments');
  expect(buildPaymentFileSource('manual_files', 'transactions_response')).toEqual('ManualFilesTransactionsResponse');
  expect(buildPaymentFileSource('ABC', 'transactions_response')).toEqual('ABCTransactionsResponse');
});
