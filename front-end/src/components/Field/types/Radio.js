import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import uuid from 'uuid';


class Radio extends PureComponent {

  static propTypes = {
    name: PropTypes.string,
    id: PropTypes.string,
    label: PropTypes.node,
    value: PropTypes.string,
    editable: PropTypes.bool,
    checked: PropTypes.bool,
    disabled: PropTypes.bool,
    labelStyle: PropTypes.object,
    onChange: PropTypes.func,
  };

  static defaultProps = {
    id: undefined,
    name: undefined,
    label: null,
    value: '',
    editable: true,
    checked: false,
    disabled: false,
    labelStyle: { paddingTop: 10 },
    onChange: () => {},
  };

  constructor(props) {
    super(props);
    this.state = {
      id: props.id || uuid.v4(),
    };
  }

  renderInput = () => {
    const { id } = this.state;
    const { name, value, disabled, checked, onChange } = this.props;
    return (
      <input
        type="radio"
        style={{ verticalAlign: 'top' }}
        name={name}
        id={id}
        value={value}
        checked={checked}
        disabled={disabled}
        onChange={onChange}
      />
    );
  }

  render() {
    const { id } = this.state;
    const { value, editable, label, disabled, labelStyle } = this.props;

    if (!editable && label !== null) {
      return (
        <div className="non-editable-field">
          {label}
        </div>
      );
    }

    if (!editable) {
      return (
        <div className="non-editable-field">
          {value}
        </div>
      );
    }

    const inputField = this.renderInput();
    if (label !== null) {
      return (
        <label htmlFor={id} style={labelStyle} className={disabled ? 'disabled' : ''}>
          {inputField}
          &nbsp;
          {label}
        </label>
      );
    }

    return inputField;
  }
}


export default Radio;
