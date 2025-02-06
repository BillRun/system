import { getConfig } from './Util';

export const validateFloat = (value, positive = false) => (
  positive ? /^[+]?\d+(\.\d+)?$/.test(value) : /^[+-]?\d+(\.\d+)?$/.test(value)
);

export const validateInteger = (value, positive = false) => (
  positive ? /^[+]?\d+$/.test(value) : /^[+-]?\d+$/.test(value)
);

export const validateKey = value => (
  getConfig('keyUppercaseRegex', /./).test(value)
);

export const validateUnlimitedValue = value => (
  value === 'UNLIMITED' || validateInteger(value, true)
);

export const validatePriceValue = value => (
  validateFloat(value, true)
);
