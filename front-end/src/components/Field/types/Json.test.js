jest.mock(
  'react-simple-code-editor',
  () => {
    const React = require('react');
    const MockEditor = ({
      value, onValueChange, onBlur, style, textareaId, textareaProps = {},
    }) => {
      const { onBlur: textareaOnBlur, ...restTextareaProps } = textareaProps;
      const handleBlur = (e) => {
        if (textareaOnBlur) textareaOnBlur(e);
        if (onBlur) onBlur(e);
      };
      return React.createElement(
        'div',
        { style },
        React.createElement('textarea', {
          id: textareaId,
          value,
          onChange: (e) => onValueChange(e.target.value),
          onBlur: handleBlur,
          ref: (el) => {
            if (!el || el.dataset.jsonMockBlur) return;
            el.dataset.jsonMockBlur = '1';
            el.addEventListener('blur', handleBlur);
          },
          ...restTextareaProps,
        }),
      );
    };
    MockEditor.displayName = 'MockEditor';
    return { __esModule: true, default: MockEditor };
  },
  { virtual: true },
);

jest.mock(
  'prismjs/components/prism-core',
  () => ({ highlight: (code) => code, languages: { json: {}, json5: {} } }),
  { virtual: true },
);
jest.mock('prismjs/components/prism-json', () => ({}), { virtual: true });
jest.mock('prismjs/components/prism-json5', () => ({}), { virtual: true });
jest.mock('prismjs/themes/prism.css', () => ({}), { virtual: true });
jest.mock('./JsonField.scss', () => ({}), { virtual: true });

import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import Immutable from 'immutable';
import Json from './Json';
import { serializeLiteral } from './jsonLiteralFormat';

let container;
let root;

beforeEach(() => {
  container = document.createElement('div');
  document.body.appendChild(container);
  root = createRoot(container);
});

afterEach(() => {
  act(() => {
    root.unmount();
  });
  document.body.removeChild(container);
  container = null;
  root = null;
});

function render(element) {
  act(() => {
    root.render(element);
  });
}

function getTextarea() {
  return container.querySelector('textarea');
}

function getPre() {
  return container.querySelector('pre');
}

function changeTextarea(textarea, newValue) {
  act(() => {
    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
      window.HTMLTextAreaElement.prototype,
      'value',
    ).set;
    nativeInputValueSetter.call(textarea, newValue);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  });
}

describe('Json field', () => {
  it('renders textarea when editable and pre when read-only or disabled', () => {
    render(<Json value={{ a: 1 }} editable={true} disabled={false} onChange={() => {}} />);
    expect(getTextarea()).not.toBeNull();

    render(<Json value={{ a: 1 }} editable={false} disabled={false} onChange={() => {}} />);
    expect(getPre()).not.toBeNull();
    expect(getTextarea()).toBeNull();

    render(<Json value={{ a: 1 }} editable={true} disabled={true} onChange={() => {}} />);
    expect(getPre()).not.toBeNull();
    expect(getTextarea()).toBeNull();
  });

  it('initialises display from value (object literal, Immutable)', () => {
    const plain = { key: 'val' };
    render(<Json value={plain} editable={true} disabled={false} onChange={() => {}} />);
    expect(getTextarea().value).toBe(serializeLiteral(plain));

    const imm = Immutable.Map({ key: 'val' });
    render(<Json value={imm} editable={true} disabled={false} onChange={() => {}} />);
    expect(getTextarea().value).toBe(serializeLiteral(imm.toJS()));
  });

  it('onChange: valid JSON → object, invalid → false, empty → []', () => {
    const onChange = jest.fn();
    render(<Json value={null} editable={true} disabled={false} onChange={onChange} />);

    changeTextarea(getTextarea(), '{x:1}');
    expect(onChange).toHaveBeenCalledWith({ x: 1 });

    changeTextarea(getTextarea(), '{bad}');
    expect(onChange).toHaveBeenCalledWith(false);

    changeTextarea(getTextarea(), '');
    expect(onChange).toHaveBeenCalledWith([]);
  });

  it('shows error hint and --error class when JSON is invalid', () => {
    render(<Json value={null} editable={true} disabled={false} onChange={() => {}} />);
    changeTextarea(getTextarea(), '{bad}');
    expect(container.querySelector('.json-field-error-hint')).not.toBeNull();
    expect(container.querySelector('.json-field-editor--error')).not.toBeNull();
  });

  it('does not reset textarea when parent echoes the same parsed value', () => {
    const onChange = jest.fn();
    render(<Json value={null} editable={true} disabled={false} onChange={onChange} />);

    const userInput = '{"x":42}';
    changeTextarea(getTextarea(), userInput);
    render(<Json value={{ x: 42 }} editable={true} disabled={false} onChange={onChange} />);
    expect(getTextarea().value).toBe(userInput);
  });

  it('updates textarea when value prop changes to a different object', () => {
    render(<Json value={{ a: 1 }} editable={true} disabled={false} onChange={() => {}} />);
    render(<Json value={{ b: 2 }} editable={true} disabled={false} onChange={() => {}} />);
    expect(getTextarea().value).toBe(serializeLiteral({ b: 2 }));
  });

  it('pretty-prints on blur without calling onChange again', () => {
    const onChange = jest.fn();
    render(<Json value={null} editable={true} disabled={false} onChange={onChange} />);
    const textarea = getTextarea();
    changeTextarea(textarea, '{"b":2,"a":1}');
    onChange.mockClear();
    act(() => { textarea.dispatchEvent(new Event('blur', { bubbles: true })); });
    expect(textarea.value).toBe(serializeLiteral({ b: 2, a: 1 }));
    expect(onChange).not.toHaveBeenCalled();
  });
});
