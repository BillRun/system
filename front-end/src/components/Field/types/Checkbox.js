import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import uuid from 'uuid';


class Checkbox extends PureComponent {

  static propTypes = {
    id: PropTypes.string,
    label: PropTypes.node,
    value: PropTypes.oneOf([true, false, '']),
    editable: PropTypes.bool,
    disabled: PropTypes.bool,
    onChange: PropTypes.func,
  };

  static defaultProps = {
    id: undefined,
    label: null,
    value: false,
    editable: true,
    checked: false,
    disabled: false,
    onChange: () => {},
  };

  constructor(props) {
    super(props);
    this.state = {
      id: props.id || uuid.v4(),
    };
  }

  onChange = (e) => {
    const { id } = this.state;
    const { checked } = e.target;
    this.props.onChange({ target: { id, value: checked } });
  };

  render() {
    const { id } = this.state;
    const { value, editable, disabled, label } = this.props;
    if (!editable) {
      return (<span>{ value ? 'Yes' : 'No' }</span>);
    }

    if (label !== null) {
      return (
        <label htmlFor={id}>
          <input
            style={{ verticalAlign: 'top' }}
            type="checkbox"
            id={id}
            checked={value}
            disabled={disabled}
            onChange={this.onChange}
          />
          &nbsp;&nbsp;{ label }
        </label>
      );
    }

    return (
      <input
        type="checkbox"
        id={id}
        checked={value}
        disabled={disabled}
        onChange={this.onChange}
      />
    );
  }
}

export default Checkbox;
