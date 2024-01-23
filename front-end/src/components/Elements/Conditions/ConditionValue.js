import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import isNumber from 'is-number';
import { InputGroup, DropdownButton, MenuItem } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  formatSelectOptions,
  getConfig,
  toImmutableList,
  parseConfigSelectOptions,
} from '@/common/Util';
import {
  selectOptionSelector,
} from '@/selectors/conditionSelectors';
import { optionsLoaders } from '@/actions/conditionActions';


class ConditionValue extends Component {

  static propTypes = {
    field: PropTypes.instanceOf(Immutable.Map),
    config: PropTypes.instanceOf(Immutable.Map),
    operator: PropTypes.instanceOf(Immutable.Map),
    dynamicSelectOptions: PropTypes.instanceOf(Immutable.List),
    customValueOptions: PropTypes.instanceOf(Immutable.List),
    conditionsSize:  PropTypes.number,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    onChange: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    field: Immutable.Map(),
    config: Immutable.Map(),
    operator: Immutable.Map(),
    dynamicSelectOptions: Immutable.List(),
    customValueOptions: Immutable.List(),
    conditionsSize: 0,
    disabled: false,
    editable: true,
    onChange: () => {},
  }

  componentDidMount() {
    const { config, dynamicSelectOptions } = this.props;
    this.initFieldOptions(config, dynamicSelectOptions);
  }

  shouldComponentUpdate(nextProps) {
    const { field, config, conditionsSize, operator, dynamicSelectOptions, disabled, editable } = this.props;
    return (
      !Immutable.is(field, nextProps.field)
      || !Immutable.is(config, nextProps.config)
      || !Immutable.is(dynamicSelectOptions, nextProps.dynamicSelectOptions)
      || !Immutable.is(operator, nextProps.operator)
      || conditionsSize !== nextProps.conditionsSize
      || disabled !== nextProps.disabled
      || editable !== nextProps.editable
    );
  }

  componentDidUpdate(prevProps) {
    const { config, conditionsSize, dynamicSelectOptions, operator, customValueOptions } = this.props;
    if (!Immutable.is(prevProps.config, config)) {
      this.initFieldOptions(config, dynamicSelectOptions);
    }
    const newOptions = Immutable.fromJS(this.getOptionsValues({
      config, operator, dynamicSelectOptions, customValueOptions
    }));
    const oldOptions = Immutable.fromJS(this.getOptionsValues({
      config: prevProps.config,
      operator: prevProps.operator,
      dynamicSelectOptions: prevProps.dynamicSelectOptions,
      customValueOptions: prevProps.customValueOptions
    }));
    const isSelectOptionsChanged = !oldOptions.isEmpty()
      && conditionsSize >= prevProps.conditionsSize
      // options was removed 
      && !oldOptions.every(element => newOptions.includes(element));

    const isTypeChanged = conditionsSize >= prevProps.conditionsSize
      && prevProps.config.get('type', '') !== ''
      && prevProps.config.get('type', '') !== config.get('type', '');

    // If type of value changed or select options, reset the value
    if (isTypeChanged || isSelectOptionsChanged) {
      this.props.onChange('');
    }
  }

  initFieldOptions = (config, dynamicSelectOptions) => {
    if (config.hasIn(['inputConfig', 'callback']) && dynamicSelectOptions.isEmpty()) {
      const callback = config.getIn(['inputConfig', 'callback']);
      const args = config.getIn(['inputConfig', 'callbackArgument'], Immutable.Map());
      if (optionsLoaders.hasOwnProperty(callback)) {
        return this.props.dispatch(optionsLoaders[callback](args));
      }
      const callbackArgs = { callback, args };
      return optionsLoaders['unknownCallback'](callbackArgs);
    }
  }

  onChangeText = (e) => {
    const { value } = e.target;
    this.props.onChange(value);
  }

  onChangeSelect = (value) => {
    const { field } = this.props;
    const multi = ['nin', 'in', '$nin', '$in'].includes(field.get('op', ''));
    if (value && value.length > 0) {
      const newValue = multi ? toImmutableList(value.split(',')) : value;
      this.props.onChange(newValue);
    } else {
      const emptyValue = multi ? Immutable.List() : '';
      this.props.onChange(emptyValue);
    }
  }

  onChangeBoolean = (value) => {
    const trueValues = [1, '1', 'true', true, 'yes', 'on'];
    const bool = value === '' ? '' : trueValues.includes(value);
    this.props.onChange(bool);
  }

  onChangeNumber = (e) => {
    const { value } = e.target;
    const number = Number(value);
    if (!isNaN(number)) {
      this.props.onChange(number);
    } else {
      this.props.onChange(value);
    }
  }

  onChangePercentage = (e) => {
    const { value } = e.target;
    const number = isNumber(value) ? parseFloat(value) / 100 : value;
    if (!isNaN(number)) {
      this.props.onChange(number);
    } else {
      this.props.onChange(value);
    }
  }

  onChangeMultiValues = (values) => {
    const { config, operator } = this.props;
    if (Array.isArray(values)) {
      if ([config.get('type', ''), operator.get('type', '')].includes('number')) {
        this.props.onChange(Immutable.List(values).map(val => (
          isNumber(val) ? parseFloat(val) : val
        )));
      } else {
        this.props.onChange(Immutable.List(values));
      }
    } else {
      this.props.onChange(Immutable.List());
    }
  }

  onChangeDate = (date) => {
    if (moment.isMoment(date) && date.isValid()) {
      const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD');
      this.props.onChange(date.format(apiDateTimeFormat));
    } else {
      this.props.onChange(null);
    }
  }

  onChangeDateTime = (date) => {
    if (moment.isMoment(date) && date.isValid()) {
      const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
      this.props.onChange(date.format(apiDateTimeFormat));
    } else {
      this.props.onChange(null);
    }
  }

  onChangeDateOption = (value) => {
    if (value === 'date') {
      this.props.onChange(null);
    } else {
      this.props.onChange(value);
    }
  }

  getOptionsValues = ({config, operator, dynamicSelectOptions, customValueOptions}) => {
    return Immutable.List()
      .withMutations((optionsWithMutations) => {
        if (dynamicSelectOptions) {
          optionsWithMutations.concat(dynamicSelectOptions);
        }
        if (config) {
          optionsWithMutations.concat(config.getIn(['inputConfig', 'options'], Immutable.List()));
        }
        if (operator) {
            optionsWithMutations.concat(operator.get('options', Immutable.List()));
        }
        if (customValueOptions) {
          customValueOptions.forEach((selectOption) => {
            optionsWithMutations.push(Immutable.fromJS(parseConfigSelectOptions(selectOption)));
          });
        }
      })
      .map(formatSelectOptions)
      .toArray();
  }

  formatValueTagDateTime = value => moment(value).format(getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]'));

  formatValueTagPercentage = value => isNumber(value) ? `${parseFloat((parseFloat(value) * 100).toFixed(3))}%` : value;

  formatValueTagDate = value => moment(value).format(getConfig('dateFormat', 'DD/MM/YYYY'));

  renderCustomInputDate = ({ addTag, disabled }) => {
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD');
    const onChange = (date) => {
      addTag(date.format(apiDateTimeFormat));
    };
    return (
      <span className="custom-field-input">
        <Field
          fieldType="date"
          value={null}
          onChange={onChange}
          disabled={disabled}
        />
      </span>
    );
  }

  renderCustomInputDateTime = ({ addTag, disabled }) => {
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
    const onChange = (date) => {
      addTag(date.format(apiDateTimeFormat));
    };
    return (
      <span className="custom-field-input">
        <Field
          fieldType="datetime"
          value={null}
          onChange={onChange}
          disabled={disabled}
        />
      </span>
    );
  }

  renderCustomInputNumber =({ addTag, onChange, value, ...other }) => (
    <Field fieldType="number" onChange={onChange} value={value} {...other} />
  );

  renderCustomInputPercentage =({ addTag, onChange, value, ...other }) => (
    <Field fieldType="percentage" onChange={onChange} value={value} {...other} />
  );

  renderInputBoolean = () => {
    const { field, disabled, editable, operator } = this.props;
    const trueValues = [1, '1', 'true', true, 'yes', 'on'];
    let value = field.get('value', '');
    if (value !== '') {
      value = trueValues.includes(field.get('value', '')) ? 1 : 0;
    }
    const booleanOptions = [
      { value: 1, label: operator.get('trueLabel', 'Yes')},
      { value: 0, label: operator.get('falseLabel', 'No')},
    ];
    return (
      <Field
        fieldType="select"
        clearable={false}
        options={booleanOptions}
        value={value}
        onChange={this.onChangeBoolean}
        disabled={disabled}
        editable={editable}
      />
    );
  }

  renderInputSelect = () => {
    const { field, disabled, editable, config, operator, dynamicSelectOptions, customValueOptions } = this.props;
    const options = this.getOptionsValues({config, operator, dynamicSelectOptions, customValueOptions});
    const multi = ['nin', 'in', '$nin', '$in'].includes(field.get('op', ''));
    return (
      <Field
        fieldType="select"
        clearable={false}
        multi={multi}
        options={options}
        value={toImmutableList(field.get('value', [])).join(',')}
        onChange={this.onChangeSelect}
        disabled={disabled}
        editable={editable}
      />
    );
  }

  renderInputNumber = () => {
    const { field, disabled, editable } = this.props;
    if (['nin', 'in', '$nin', '$in'].includes(field.get('op', ''))) {
      const value = toImmutableList(field.get('value', [])).toArray();
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
          editable={editable}
          renderInput={this.renderCustomInputNumber}
        />
      );
    }
    return (
      <Field
        fieldType="number"
        value={field.get('value', '')}
        onChange={this.onChangeNumber}
        disabled={disabled}
        editable={editable}
      />
    );
  }

  renderInputPercentage = () => {
    const { field, disabled, editable } = this.props;
    if (['nin', 'in', '$nin', '$in'].includes(field.get('op', ''))) {
      const value = toImmutableList(field.get('value', [])).toArray();
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
          editable={editable}
          renderInput={this.renderCustomInputPercentage}
          getTagDisplayValue={this.formatValueTagPercentage}
        />
      );
    }
    return (
      <Field
        fieldType="percentage"
        value={field.get('value', '')}
        onChange={this.onChangeNumber}
        disabled={disabled}
        editable={editable}
      />
    );
  }

  renderInputDate = () => {
    const { field, disabled, editable, config, operator, dynamicSelectOptions, customValueOptions } = this.props;
    if (['nin', 'in', '$nin', '$in'].includes(field.get('op', ''))) {
      const value = toImmutableList(field.get('value', [])).toArray();
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
          editable={editable}
          renderInput={this.renderCustomInputDate}
          getTagDisplayValue={this.formatValueTagDate}
        />
      );
    }
    const value = field.get('value', null);
    const options = this.getOptionsValues({config, operator, dynamicSelectOptions, customValueOptions});
    if (options.length > 0) {
      const selectedOptionIdx = options
        .findIndex(option => option.value === value)
      const actionTitle = selectedOptionIdx === -1 ? 'Date' : options[selectedOptionIdx].label;
      return (
        <InputGroup>
          <DropdownButton
            disabled={disabled}
            onSelect={this.onChangeDateOption}
            componentClass={InputGroup.Button}
            id="date-select-options"
            title={actionTitle}
            className="full-width"
          >
            <MenuItem key="date" eventKey="date">Select Date:</MenuItem>
            <MenuItem divider />
            { options.map(option => (
                <MenuItem key={option.value} eventKey={option.value}>{option.label}</MenuItem>
            )) }
          </DropdownButton>
          {selectedOptionIdx === -1 && (
            <Field
              fieldType="date"
              value={moment(value)}
              onChange={this.onChangeDateTime}
              disabled={disabled}
              editable={editable}
              />
          )}
        </InputGroup>
      );
    }
    return (
      <Field
        fieldType="date"
        value={moment(value)}
        onChange={this.onChangeDateTime}
        disabled={disabled}
        editable={editable}
      />
    );
  }

  renderInputDateTime = () => {
    const { field, disabled, editable } = this.props;
    if (['nin', 'in', '$nin', '$in'].includes(field.get('op', ''))) {
      const value = toImmutableList(field.get('value', [])).toArray();
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
          editable={editable}
          renderInput={this.renderCustomInputDateTime}
          getTagDisplayValue={this.formatValueTagDateTime}
        />
      );
    }
    const value = moment(field.get('value', null)).utc();
    return (
      <Field
        fieldType="datetime"
        value={value}
        onChange={this.onChangeDateTime}
        disabled={disabled}
        editable={editable}
      />
    );
  }

  renderInputString = () => {
    const { field, disabled, editable } = this.props;
    if (['nin', 'in', '$nin', '$in'].includes(field.get('op', ''))) {
      const value = toImmutableList(field.get('value', [])).toArray();
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
          editable={editable}
        />
      );
    }
    return (
      <Field
        value={field.get('value', '')}
        onChange={this.onChangeText}
        disabled={disabled}
      />
    );
  }

  renderInput = () => {
    const { field, config, operator } = this.props;
    //  Boolean + operator 'EXIST'
    if ([config.get('type', ''), operator.get('type', '')].includes('boolean')) {
      return this.renderInputBoolean();
    }

    // String-select
    if ([config.get('type', 'string'), operator.get('type', '')].includes('string')
      && (config.getIn(['inputConfig', 'inputType']) === 'select' || operator.has('options'))
      && ['eq', 'ne', 'nin', 'in', '$eq', '$ne', '$nin', '$in', 'is', '$is'].includes(field.get('op', ''))
    ) {
      return this.renderInputSelect();
    }

    // 'Number'
    if ([config.get('type', ''), operator.get('type', '')].includes('number')) {
      return this.renderInputNumber();
    }

    // 'percentage'
    if ([config.get('type', ''), operator.get('type', '')].includes('percentage')) {
      return this.renderInputPercentage();
    }

    // 'Date'
    if ([config.get('type', ''), operator.get('type', '')].includes('date')) {
      return this.renderInputDate();
    }

    // 'DateTime'
    if ([config.get('type', ''), operator.get('type', '')].includes('datetime')) {
      return this.renderInputDateTime();
    }

    // 'String'
    return this.renderInputString();
  }

  render() {
    const { operator } = this.props;
    const input = this.renderInput();
    if (operator.has('prefix')) {
      return (
        <InputGroup>
          <InputGroup.Addon>{operator.get('prefix', '')}</InputGroup.Addon>
          {input}
        </InputGroup>
      );
    }
    if (operator.has('suffix')) {
      return (
        <InputGroup>
          {input}
          <InputGroup.Addon>{operator.get('suffix', '')}</InputGroup.Addon>
        </InputGroup>
      );
    }
    return (input);
  }

}

const mapStateToProps = (state, props) => ({
  dynamicSelectOptions: selectOptionSelector(state, props),
});

export default connect(mapStateToProps)(ConditionValue);
