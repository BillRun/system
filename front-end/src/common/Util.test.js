import Immutable from 'immutable';
import {
  getFieldEntityKey,
  toImmutableList,
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
