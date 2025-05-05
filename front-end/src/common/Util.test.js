import Immutable from 'immutable';
import {
  getFieldEntityKey,
  toImmutableList,
  isValueOn,
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
