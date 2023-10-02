import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import { InputGroup, Button } from 'react-bootstrap';
import classNames from 'classnames';
import Field from '@/components/Field';


class TextWithButton extends PureComponent {

  static propTypes = {
    inputProps: PropTypes.object,
    actionType: PropTypes.oneOf(['add', 'remove']),
    actionLabel: PropTypes.string,
    value: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.bool,
      PropTypes.number,
    ]),
    clearAfterAction: PropTypes.bool,
    onChange: PropTypes.func,
    onAction: PropTypes.func.isRequired,
  };

  static defaultProps = {
    fieldType: undefined,
    inputProps: {},
    actionLabel: '',
    actionType: 'add',
    value: '',
    clearAfterAction: false,
    onChange: null,
  };

  state = {
    value: this.props.value,
  };

  onChangeValue = (e) => {
    const { value } = e.target;
    this.setState({ value });
    if (this.props.onChange) {
      this.props.onChange(value);
    }
  }

  onAction = () => {
    const { clearAfterAction } = this.props;
    const { value } = this.state;
    this.props.onAction(value);
    if (clearAfterAction) {
      this.onChangeValue({ target: { value: '' } });
    }
  }

  getActionLabel = () => {
    const { actionLabel } = this.props;
    if (actionLabel.length > 0) {
      return ` ${actionLabel}`;
    }
    return null;
  }

  getActionStyle = () => {
    const { actionType } = this.props;
    switch (actionType) {
      case 'add':
        return 'primary';
      case 'remove':
      default:
        return undefined;
    }
  }

  isButtonDisabled = () => {
    const { actionType } = this.props;
    const { value } = this.state;
    return actionType === 'add' && value.length < 1;
  }

  render() {
    const { inputProps, actionType } = this.props;
    const { value } = this.state;

    const iconClass = classNames('fa fa-fw', {
      'danger-red': actionType === 'remove',
      'fa-trash-o': actionType === 'remove',
      'fa-plus': actionType === 'add',
    });

    return (
      <InputGroup>
        <Field {...inputProps} onChange={this.onChangeValue} value={value} />
        <InputGroup.Button>
          <Button
            onClick={this.onAction}
            bsStyle={this.getActionStyle()}
            disabled={this.isButtonDisabled()}
          >
            <i className={iconClass} />
            { this.getActionLabel()}
          </Button>
        </InputGroup.Button>
      </InputGroup>
    );
  }
}

export default TextWithButton;
