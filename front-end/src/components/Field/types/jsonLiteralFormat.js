import JSON5 from 'json5';

const stripTrailingCommas = (text) => text.replace(/,(\s*[\]}])/g, '$1');

const quoteDollarKeys = (text) => text.replace(
  /(^|\n)([ \t]*)(\$[A-Za-z_][\w$]*)\s*:/g,
  (_, lead, indent, key) => `${lead}${indent}'${key}':`,
);

export const serializeLiteral = (value) => {
  if (value == null) return '';
  const text = JSON5.stringify(value, null, 2);
  return quoteDollarKeys(stripTrailingCommas(text));
};

export const parseLiteral = (text) => {
  const trimmed = (text || '').trim();
  if (!trimmed) return null;

  try {
    const result = JSON5.parse(trimmed);
    if (result !== null && typeof result === 'object') {
      return result;
    }
  } catch (err) {
    throw new SyntaxError(err && err.message ? err.message : 'Invalid JSON');
  }

  throw new SyntaxError('Invalid JSON');
};

export const toCanonicalJson = (value) => {
  if (value == null) return null;
  try {
    return JSON.stringify(value);
  } catch (e) {
    return null;
  }
};

export const textToCanonical = (text) => {
  const trimmed = (text || '').trim();
  if (!trimmed) return null;
  try {
    return JSON.stringify(parseLiteral(trimmed));
  } catch (e) {
    return null;
  }
};
