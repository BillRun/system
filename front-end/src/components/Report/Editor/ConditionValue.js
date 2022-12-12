import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  formatSelectOptions,
  getConfig,
} from '@/common/Util';
import {
  selectOptionSelector,
} from '@/selectors/conditionSelectors';
import {
  getCyclesOptions,
  getProductsOptions,
  getPlansOptions,
  getServicesOptions,
  getGroupsOptions,
  getUsageTypesOptions,
  getBucketsOptions,
  getFileTypesOptions,
  getEventCodeOptions,
  getPlayTypeOptions,
  getTaxesOptions,
} from '@/actions/reportsActions';


class ConditionValue extends Component {

  static propTypes = {
    field: PropTypes.instanceOf(Immutable.Map),
    config: PropTypes.instanceOf(Immutable.Map),
    operator: PropTypes.instanceOf(Immutable.Map),
    selectOptions: PropTypes.instanceOf(Immutable.List),
    disabled: PropTypes.bool,
    onChange: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    field: Immutable.Map(),
    config: Immutable.Map(),
    operator: Immutable.Map(),
    selectOptions: Immutable.List(),
    disabled: false,
    onChange: () => {},
  }

  componentDidMount() {
    const { config, selectOptions } = this.props;
    this.initFieldOptions(config, selectOptions);
  }

  componentWillReceiveProps(nextProps) {
    const { config } = this.props;
    if (!Immutable.is(config, nextProps.config)) {
      this.initFieldOptions(nextProps.config, nextProps.selectOptions);
    }
  }

  shouldComponentUpdate(nextProps) {
    const { field, config, operator, selectOptions, disabled } = this.props;
    return (
      !Immutable.is(field, nextProps.field)
      || !Immutable.is(config, nextProps.config)
      || !Immutable.is(selectOptions, nextProps.selectOptions)
      || !Immutable.is(operator, nextProps.operator)
      || disabled !== nextProps.disabled
    );
  }

  initFieldOptions = (config, selectOptions) => {
    if (config.hasIn(['inputConfig', 'callback']) && selectOptions.isEmpty()) {
      const callback = config.getIn(['inputConfig', 'callback']);
      switch (callback) {
        case 'getCyclesOptions': this.props.dispatch(getCyclesOptions());
          break;
        case 'getPlansOptions': this.props.dispatch(getPlansOptions());
          break;
        case 'getProductsOptions': this.props.dispatch(getProductsOptions(
          config.getIn(['inputConfig', 'callbackArgument'], Immutable.Map())),
        );
          break;
        case 'getServicesOptions': this.props.dispatch(getServicesOptions());
          break;
        case 'getGroupsOptions': this.props.dispatch(getGroupsOptions());
          break;
        case 'getUsageTypesOptions': this.props.dispatch(getUsageTypesOptions());
          break;
        case 'getBucketsOptions':
        case 'getBucketsExternalIdsOptions':
          this.props.dispatch(getBucketsOptions());
          break;
        case 'getFileTypeOptions': this.props.dispatch(getFileTypesOptions());
          break;
        case 'getPlayTypeOptions': this.props.dispatch(getPlayTypeOptions());
          break;
        case 'getEventCodeOptions': this.props.dispatch(getEventCodeOptions());
          break;
        case 'getTaxesOptions': this.props.dispatch(getTaxesOptions());
          break;
        default: console.log('unsupported select options callback');
          break;
      }
    }
  }

  onChangeText = (e) => {
    const { value } = e.target;
    this.props.onChange(value);
  };

  onChangeSelect = (value) => {
    this.props.onChange(value);
  };

  onChangeBoolean = (value) => {
    const trueValues = [1, '1', 'true', true, 'yes', 'on'];
    const bool = value === '' ? '' : trueValues.includes(value);
    this.props.onChange(bool);
  };

  onChangeNumber = (e) => {
    const { value } = e.target;
    const number = Number(value);
    if (!isNaN(number)) {
      this.props.onChange(number);
    } else {
      this.props.onChange(value);
    }
  };

  onChangeMultiValues = (e) => {
    if (Array.isArray(e)) {
      this.props.onChange(e.join(','));
    } else {
      this.props.onChange('');
    }
  };

  onChangeDate = (date) => {
    if (moment.isMoment(date) && date.isValid()) {
      const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD');
      this.props.onChange(date.format(apiDateTimeFormat));
    } else {
      this.props.onChange(null);
    }
  };

  onChangeDateTime = (date) => {
    if (moment.isMoment(date) && date.isValid()) {
      const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
      this.props.onChange(date.format(apiDateTimeFormat));
    } else {
      this.props.onChange(null);
    }
  };

  getOptionsValues = (defaultOptions = Immutable.List()) => this.props.operator
    .get('options', defaultOptions)
    .map(formatSelectOptions)
    .toArray();

  formatValueTagDateTime = value => moment(value).format(getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]'));

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
    <input type="number" onChange={onChange} value={value} {...other} />
  );

  renderInputBoolean = () => {
    const { field, disabled } = this.props;
    let value = '';
    if (field.get('value', false) === true) {
      value = 'yes';
    } else if (field.get('value', true) === false) {
      value = 'no';
    }
    const booleanOptions = this.getOptionsValues(Immutable.List(['yes', 'no']));
    return (
      <Field
        fieldType="select"
        clearable={false}
        options={booleanOptions}
        value={value}
        onChange={this.onChangeBoolean}
        disabled={disabled}
      />
    );
  }

  renderInputSelect = () => {
    const { field, disabled, config, selectOptions, operator } = this.props;
    const options = Immutable.List()
      .withMutations((optionsWithMutations) => {
        if (config.hasIn(['inputConfig', 'callback'])) {
          selectOptions.forEach((selectOption) => {
            optionsWithMutations.push(selectOption);
          });
        }
        if (config.hasIn(['inputConfig', 'options'])) {
          config.getIn(['inputConfig', 'options'], Immutable.List()).forEach((selectOption) => {
            optionsWithMutations.push(selectOption);
          });
        }
        if (operator.has('options')) {
          operator.get('options', Immutable.List()).forEach((selectOption) => {
            optionsWithMutations.push(selectOption);
          });
        }
      })
      .map(formatSelectOptions)
      .toArray();

    const multi = ['nin', 'in'].includes(field.get('op', ''));
    return (
      <Field
        fieldType="select"
        clearable={false}
        multi={multi}
        options={options}
        value={field.get('value', '')}
        onChange={this.onChangeSelect}
        disabled={disabled}
      />
    );
  }

  renderInputNumber = () => {
    const { field, disabled } = this.props;
    if (['nin', 'in'].includes(field.get('op', ''))) {
      const value = field.get('value', '').split(',').filter(val => val !== '');
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
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
      />
    );
  }

  renderInputDate = () => {
    const { field, disabled } = this.props;
    if (['nin', 'in'].includes(field.get('op', ''))) {
      const value = field.get('value', '').split(',').filter(val => val !== '');
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
          renderInput={this.renderCustomInputDate}
          getTagDisplayValue={this.formatValueTagDate}
        />
      );
    }
    const value = moment(field.get('value', null));
    return (
      <Field
        fieldType="date"
        value={value}
        onChange={this.onChangeDateTime}
        disabled={disabled}
      />
    );
  }

  renderInputDateTime = () => {
    const { field, disabled } = this.props;
    if (['nin', 'in'].includes(field.get('op', ''))) {
      const value = field.get('value', '').split(',').filter(val => val !== '');
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
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
      />
    );
  }

  renderInputString = () => {
    const { field, disabled } = this.props;
    if (['nin', 'in'].includes(field.get('op', ''))) {
      const value = field.get('value', '').split(',').filter(val => val !== '');
      return (
        <Field
          fieldType="tags"
          value={value}
          onChange={this.onChangeMultiValues}
          disabled={disabled}
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
      && ['eq', 'ne', 'nin', 'in'].includes(field.get('op', ''))
    ) {
      return this.renderInputSelect();
    }

    // 'Number'
    if ([config.get('type', ''), operator.get('type', '')].includes('number')) {
      return this.renderInputNumber();
    }

    // 'Date'
    if ([config.get('type', ''), operator.get('type', '')].includes('date') || [config.get('type', ''), operator.get('type', '')].includes('daterange')) {
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
  selectOptions: selectOptionSelector(state, props),
});

export default connect(mapStateToProps)(ConditionValue);
