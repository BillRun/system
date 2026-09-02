import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { isImmutable } from 'immutable';
import Editor from 'react-simple-code-editor';
import { highlight, languages } from 'prismjs/components/prism-core';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-json5';
import {
  serializeLiteral,
  parseLiteral,
  toCanonicalJson,
  textToCanonical,
} from './jsonLiteralFormat';
import './JsonField.scss';

const wrapperStyle = {
  height: 'auto',
  padding: '6px 4px',
};

const editorStyle = {
  minHeight: '110px',
};

const toPlain = (value) => {
  if (value == null) return null;
  if (isImmutable(value)) return value.toJS();
  return value;
};

const serialize = (value) => {
  const plain = toPlain(value);
  if (plain == null) return '';
  try {
    return serializeLiteral(plain);
  } catch (e) {
    return '';
  }
};

const getLineNumbers = (text) => {
  const count = Math.max(1, (text || '').split('\n').length);
  return Array.from({ length: count }, (_, i) => i + 1);
};

const highlightLiteral = (code) => highlight(code || '', languages.json5, 'json5');

class Json extends Component {

  static propTypes = {
    id: PropTypes.string,
    value: PropTypes.oneOfType([
      PropTypes.object,
      PropTypes.array,
    ]),
    required: PropTypes.bool,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    tooltip: PropTypes.string,
    onChange: PropTypes.func,
  };

  static defaultProps = {
    value: null,
    id: undefined,
    required: false,
    disabled: false,
    editable: true,
    tooltip: '',
    onChange: () => {},
  };

  constructor(props) {
    super(props);
    this.state = {
      text: serialize(props.value),
      hasError: false,
    };
  }

  componentDidUpdate(prevProps) {
    const { value } = this.props;

    if (prevProps.value === value) return;

    const newCanonical = toCanonicalJson(toPlain(value));
    const currentCanonical = textToCanonical(this.state.text);

    if (newCanonical === currentCanonical) return;

    this.setState({ text: serialize(value), hasError: false });
  }

  onBlurEditor = () => {
    const { text } = this.state;
    if (!text.trim()) return;
    try {
      const parsed = parseLiteral(text);
      this.setState({ text: serializeLiteral(parsed) });
    } catch (e) {
    }
  };

  onChangeText = (text) => {
    const { onChange } = this.props;

    if (text.trim() === '') {
      this.setState({ text, hasError: false });
      onChange([]);
      return;
    }

    try {
      const parsed = parseLiteral(text);
      this.setState({ text, hasError: false });
      onChange(parsed);
    } catch (_err) {
      this.setState({ text, hasError: true });
      onChange(false);
    }
  };

  render() {
    const { value, editable, disabled, id } = this.props;
    const { text, hasError } = this.state;
    const isReadOnly = !editable || disabled;
    const lineNumbers = getLineNumbers(text);

    return (
      <div className="form-control json-field-wrapper" style={wrapperStyle}>
        {isReadOnly ? (
          <pre className="json-field-view">
            {serialize(value)}
          </pre>
        ) : (
          <div className={`json-field-editor${hasError ? ' json-field-editor--error' : ''}`}>
            <div className="json-field-editor__body">
              <div className="json-field-editor__gutter" aria-hidden="true">
                {lineNumbers.map((n) => (
                  <div key={n} className="json-field-editor__line-number">{n}</div>
                ))}
              </div>
              <div className="json-field-editor__content">
                <Editor
                  textareaId={id}
                  value={text}
                  onValueChange={this.onChangeText}
                  highlight={highlightLiteral}
                  padding={5}
                  style={editorStyle}
                  textareaProps={{ onBlur: this.onBlurEditor }}
                />
              </div>
            </div>
            {hasError && (
              <div className="json-field-error-hint">Invalid JSON</div>
            )}
          </div>
        )}
      </div>
    );
  }
}

export default Json;
