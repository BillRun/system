import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { InputGroup, FormControl } from 'react-bootstrap';


export default class UnlimitedInput extends Component {

  static defaultProps = {
    unlimitedLabel: 'Unlimited',
    unlimitedValue: 'UNLIMITED',
    disabled: false,
    editable: true,
    suffix: null,
  };

  static propTypes = {
    value: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]).isRequired,
    unlimitedValue: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]),
    unlimitedLabel: PropTypes.string,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    suffix: PropTypes.node,
    onChange: PropTypes.func.isRequired,
  }

  state = {
    value: this.props.value === this.props.unlimitedValue ? '' : this.props.value,
    unlimited: this.props.value == this.props.unlimitedValue, // eslint-disable-line eqeqeq
  }

  onUnlimitedChanged = (e) => {
    const { unlimitedValue } = this.props;
    const unlimited = e.target.checked;
    const newValue = unlimited ? unlimitedValue : this.state.value;
    this.setState({ unlimited });
    this.props.onChange(newValue);
  }

  onValueChanged = (e) => {
    const { value } = e.target;
    this.setState({ value });
    this.props.onChange(value);
  }

  render() {
    const { value, unlimited } = this.state;
    const { unlimitedLabel, disabled, editable, suffix } = this.props;

    if (!editable) {
      return (
        <div className="non-editable-field">
          { unlimited ? unlimitedLabel : unlimitedLabel }
        </div>
      );
    }

    return (
      <InputGroup>
        <InputGroup.Addon>
          <input
            checked={unlimited}
            onChange={this.onUnlimitedChanged}
            type="checkbox"
            disabled={disabled}
          /><small style={{ verticalAlign: 'bottom' }}> {unlimitedLabel}</small>
        </InputGroup.Addon>
        <FormControl
          disabled={unlimited || disabled}
          onChange={this.onValueChanged}
          type="text"
          value={unlimited ? '' : value}
        />
        { (suffix !== null) && <InputGroup.Addon>{suffix}</InputGroup.Addon> }
      </InputGroup>
    );
  }

}
