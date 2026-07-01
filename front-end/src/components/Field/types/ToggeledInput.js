import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { InputGroup } from 'react-bootstrap';
import Field from '../';


export default class ToggeledInput extends Component {

  static defaultProps = {
    label: 'Enable',
    disabledValue: null,
    disabledDisplayValue: '',
    disabled: false,
    editable: true,
    suffix: null,
    inputProps: {},
    compare: (a, b) => a === b,
  };

  static propTypes = {
    value: PropTypes.any,
    disabledValue: PropTypes.any,
    disabledDisplayValue: PropTypes.any,
    label: PropTypes.node,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    inputProps: PropTypes.object,
    suffix: PropTypes.node,
    onChange: PropTypes.func.isRequired,
    compare: PropTypes.func.isRequired,
  }


  constructor(props) {
    super(props);
    const off = props.compare(props.value, props.disabledValue);
    const value = off ? props.disabledValue : props.value;
    this.state = {
      value,
      off,
    };
  }

  
  onChangedState = (e) => {
    const { disabledValue } = this.props;
    const { checked } = e.target;
    const newValue = checked ? this.state.value : disabledValue;
    this.props.onChange(newValue);
    this.setState({ off: !checked });
  }

  onValueChanged = (e) => {
    const value = this.getValue(e);
    this.setState({ value });
    this.props.onChange(value);
  }

  getValue = (e) => {
    const { inputProps: { fieldType = 'text' } } = this.props;
    switch (fieldType) {
      case 'date':
        return e;
      case 'datetime':
        return e;
      default:
        return e.target.value;
    }
  }

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const isSameValue = prevProps.compare(this.props.value, prevProps.value);
    if (!isSameValue) {
      const off = this.props.compare(this.props.value, this.props.disabledValue);
      const value = off ? this.props.disabledValue : this.props.value;
      this.setState({
        value,
        off,
      });
    }
  }

  render() {
    const { value, off } = this.state;
    const { label, disabled, editable, suffix, inputProps, disabledDisplayValue } = this.props;

    if (!editable) {
      return (
        <div className="non-editable-field">
          { off ? disabledDisplayValue : value }
        </div>
      );
    }

    return (
      <InputGroup>
        <InputGroup.Text>
          <label className="mb0">
            <input
              style={{ verticalAlign: 'bottom' }}
              checked={!off}
              onChange={this.onChangedState}
              type="checkbox"
              disabled={disabled}
            /> {label}
          </label>
        </InputGroup.Text>
        <Field
          disabled={off || disabled}
          onChange={this.onValueChanged}
          value={off ? disabledDisplayValue : (value ?? '')}
          {...inputProps}
        />
        { (suffix != null) && <InputGroup.Text>{suffix}</InputGroup.Text> }
      </InputGroup>
    );
  }

}
