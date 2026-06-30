import JSON5 from 'json5';
import { serializeLiteral, parseLiteral } from './jsonLiteralFormat';

describe('jsonLiteralFormat', () => {
  it('serializes with unquoted keys and single-quoted strings', () => {
    const value = { status: 1, name: 'P1' };
    expect(serializeLiteral(value)).not.toMatch(/"status"/);
    expect(serializeLiteral(value)).not.toMatch(/,\s*[\]}]/);
  });

  it('quotes keys that start with $', () => {
    const text = serializeLiteral({ _id: { $id: '698c' } });
    expect(text).toContain("'$id': '698c'");
    expect(text).not.toMatch(/\$id\s*:/);
  });

  it('omits trailing commas before closing braces and brackets', () => {
    const value = { details: [{ revision_info: { is_last: true } }] };
    expect(serializeLiteral(value)).not.toMatch(/,\s*[\]}]/);
  });

  it('parses strict JSON and object literal', () => {
    expect(parseLiteral('{"a":1}')).toEqual({ a: 1 });
    expect(parseLiteral('{a:1}')).toEqual({ a: 1 });
  });
});
