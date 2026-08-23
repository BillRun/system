import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { InputGroup, DropdownButton, Dropdown } from 'react-bootstrap'
import { InputGroupButton } from '@/common/BootstrapCompat';
import Immutable from 'immutable';
import Field from '@/components/Field';
import {
  formatSelectOptions,
} from '@/common/Util';


class FormatterValue extends Component {

  static propTypes = {
    field: PropTypes.instanceOf(Immutable.Map),
    config: PropTypes.instanceOf(Immutable.Map),
    disabled: PropTypes.bool,
    onChange: PropTypes.func,
    onChangeValueType: PropTypes.func,
  }

  static defaultProps = {
    field: Immutable.Map(),
    config: Immutable.Map(),
    disabled: false,
    onChange: () => {},
    onChangeValueType: () => {},
  }

  
  shouldComponentUpdate(nextProps) {
    const { field, config, disabled } = this.props;
    return (
      !Immutable.is(field, nextProps.field)
      || !Immutable.is(config, nextProps.config)
      || disabled !== nextProps.disabled
    );
  }

  onChangeText = (e) => {
    const { value } = e.target;
    this.props.onChange(value);
  };

  onChangeSelect = (value) => {
    this.props.onChange(value);
  };

  onSelectOptionType = (type) => {
    this.props.onChangeValueType(type);
  }

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { field } = prevProps;
    // by default set first value if no value selected
    if (this.props.config.has('options', '') && field.get('value', '') === '') {
      const value = this.props.config.get('options', Immutable.List()).map(formatSelectOptions).first().value || '';
      prevProps.onChange(value);
    }
    if (this.props.config.has('fixedValue')) {
      prevProps.onChange(this.props.config.get('fixedValue', ''));
    }
  }

  render() {
    const { field, disabled, config } = this.props;
    let disabledInput = disabled || config.isEmpty();
    let value = field.get('value', '');
    if (['json'].includes(config.get('id', ''))) {
      disabledInput = true;
      value = '';
    }

    if (config.has('valueTypes')) {
      const selectedUnit = config
        .get('valueTypes', Immutable.List())
        .find(type => type.get('value', '') === field.get('type', ''), null, Immutable.Map({}))
        .get('label', 'Select unit...');
      const valueOptions = config
        .get('valueTypes', Immutable.List())
        .map(option => (
          <Dropdown.Item
            eventKey={option.get('value', '-')}
            key={option.get('value', '-')}
            onSelect={this.onSelectOptionType}
          >
            {option.get('label', '-')}
          </Dropdown.Item>
        ))
        .toArray();
      return (
        <InputGroup>
          <Field
            fieldType={config.get('type', 'text')}
            value={field.get('value', '')}
            onChange={this.onChangeText}
            disabled={disabledInput}
          />
          <InputGroupButton>
            <DropdownButton
            id="balance-period-unit"
            title={selectedUnit}
            disabled={disabledInput}
            >
            { valueOptions }
            </DropdownButton>
          </InputGroupButton>
        </InputGroup>
      );
    }

    if (config.has('options')) {
      // Not Input value
      if (config.get('options', '') === false) {
        return null;
      }
      // Select
      const valueOptions = config
        .get('options', Immutable.List())
        .map(formatSelectOptions)
        .toArray();
      return (
        <Field
          fieldType="select"
          clearable={false}
          options={valueOptions}
          value={field.get('value', '')}
          onChange={this.onChangeSelect}
          disabled={disabledInput}
          allowCreate={config.get('addOption', false)}
        />
      );
    }

    // String or Number or Default when Op not selected
    return (
      <Field
        value={value}
        onChange={this.onChangeText}
        disabled={disabledInput}
      />
    );
  }

}

export default FormatterValue;
