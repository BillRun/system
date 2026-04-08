import React, { Component } from 'react';
import PropTypes from 'prop-types';

/**
 * Simple JSON editor replacing react-json-editor-ajrm (abandoned, used findDOMNode).
 * Uses a controlled <textarea> with JSON validation and pretty-print formatting.
 */
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
      text: this.serialize(props.value),
      error: false,
    };
  }

  serialize = (value) => {
    if (value === null || typeof value === 'undefined') return '';
    try {
      return JSON.stringify(value, null, 2);
    } catch (e) {
      return '';
    }
  };

  
  onTextChange = (e) => {
    const { onChange } = this.props;
    const text = e.target.value;
    this.setState({ text });
    if (text.trim() === '') {
      this.setState({ error: false });
      onChange(null);
      return;
    }
    try {
      const parsed = JSON.parse(text);
      this.setState({ error: false });
      onChange(parsed);
    } catch (err) {
      this.setState({ error: true });
      onChange(false);
    }
  };

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    // Only sync from props when not currently in error state
    if (!this.state.error) {
      const nextText = this.serialize(this.props.value);
      const currentText = this.serialize(prevProps.value);
      if (nextText !== currentText) {
        this.setState({ text: nextText });
      }
    }
  }

  render() {
    const { editable, disabled } = this.props;
    const { text, error } = this.state;

    const borderColor = error ? '#dc3545' : '#ced4da';
    const style = {
      width: '100%',
      minHeight: 80,
      fontFamily: 'monospace',
      fontSize: 12,
      padding: '4px 6px',
      border: `1px solid ${borderColor}`,
      borderRadius: 4,
      resize: 'vertical',
      backgroundColor: (!editable || disabled) ? '#f8f9fa' : '#fff',
      outline: 'none',
    };

    if (!editable || disabled) {
      return (
        <textarea
          readOnly
          value={text}
          style={style}
          rows={Math.max(3, (text.match(/\n/g) || []).length + 1)}
        />
      );
    }

    return (
      <div>
        <textarea
          value={text}
          onChange={this.onTextChange}
          style={style}
          rows={Math.max(3, (text.match(/\n/g) || []).length + 1)}
          spellCheck={false}
        />
        {error && (
          <div style={{ color: '#dc3545', fontSize: 11, marginTop: 2 }}>
            Invalid JSON
          </div>
        )}
      </div>
    );
  }
}

export default Json;
