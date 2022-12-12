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

  componentWillReceiveProps(nextProps) {
    const isSameValue = this.props.compare(nextProps.value, this.props.value);
    if (!isSameValue) {
      const off = nextProps.compare(nextProps.value, nextProps.disabledValue);
      const value = off ? nextProps.disabledValue : nextProps.value;
      this.setState({
        value,
        off,
      });
    }
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
      default:
        return e.target.value;
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
        <InputGroup.Addon>
          <label className="mb0">
            <input
              style={{ verticalAlign: 'bottom' }}
              checked={!off}
              onChange={this.onChangedState}
              type="checkbox"
              disabled={disabled}
            /> {label}
          </label>
        </InputGroup.Addon>
        <Field
          disabled={off || disabled}
          onChange={this.onValueChanged}
          value={off ? disabledDisplayValue : value}
          {...inputProps}
        />
        { (suffix !== null) && <InputGroup.Addon>{suffix}</InputGroup.Addon> }
      </InputGroup>
    );
  }

}
