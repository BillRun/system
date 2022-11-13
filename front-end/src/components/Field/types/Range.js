import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import moment from 'moment';
import { InputGroup } from 'react-bootstrap';
import Field from '../';
import { getConfig } from '@/common/Util';

class Range extends PureComponent {

  static propTypes = {
    value: PropTypes.instanceOf(Immutable.Map),
    inputProps: PropTypes.object,
    editable: PropTypes.bool,
    compact: PropTypes.bool,
    placeholder: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.shape({
        from: PropTypes.string,
        to: PropTypes.string,
      }),
    ]),
    onChange: PropTypes.func,
  };

  static defaultProps = {
    value: Immutable.Map({ from: '', to: '' }),
    placeholder: { from: '', to: '' },
    inputProps: {},
    editable: true,
    compact: false,
    onChange: () => {},
  };

  onChangeFrom = (e) => {
    const { value } = this.props;
    const from = this.getValue(e);
    this.props.onChange(value.set('from', from));
  }

  onChangeTo = (e) => {
    const { value } = this.props;
    const to = this.getValue(e);
    this.props.onChange(value.set('to', to));
  }

  getValue = (e) => {
    const { inputProps: { fieldType = 'text' } } = this.props;
    switch (fieldType) {
      case 'date':
        const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
        return (moment.isMoment(e) && e.isValid()) ? e.format(apiDateTimeFormat) : '';
      case 'select':
        return e;
      default:
        return e.target.value;
    }
  }

  setValue = (value) => {
    const { inputProps: { fieldType = 'text' } } = this.props;
    switch (fieldType) {
      case 'date':
        return (value === '') ? null : moment(value);
      default:
        return value;
    }
  }

  replaceValues = (properties, valueFrom, valueTo) => {
    const values = Object.values(properties);
    if (values.includes('@valueFrom@') || values.includes('@valueTo@')) {
      return Immutable.fromJS(properties).map((value, key) => {
        if (value === '@valueFrom@') {
          return valueFrom;
        }
        if (value === '@valueTo@') {
          return valueTo;
        }
        return value;
      }).toJS()
    }
    return properties;
  }

  render() {
    const {
      onChange,
      value,
      placeholder,
      editable,
      compact,
      inputFromProps = {},
      inputToProps = {},
      inputProps: { fieldType = 'text' },
      ...otherProps
    } = this.props;
    const valueFrom = this.setValue(Immutable.Map.isMap(value) ? value.get('from', '') : '');
    const valueTo = this.setValue(Immutable.Map.isMap(value) ? value.get('to', '') : '');
    const placeholderFrom = typeof placeholder['from'] !== 'undefined' ? placeholder.from : '';
    const placeholderTo = typeof placeholder['to'] !== 'undefined' ? placeholder.to : '';
    const inputFromPropsReplaced = this.replaceValues(inputFromProps, valueFrom, valueTo);
    const inputToPropsReplaced = this.replaceValues(inputToProps, valueFrom, valueTo);

    if (!editable) {
      return (
        <span className="non-editable-field">
          <Field
            {...otherProps}
            {...inputFromPropsReplaced}
            fieldType={fieldType}
            value={valueFrom}
            placeholder={placeholderFrom}
            editable={false}
            className="inline"
          />
          &nbsp;-&nbsp;
          <Field
            {...otherProps}
            {...inputToPropsReplaced}
            fieldType={fieldType}
            value={valueTo}
            editable={false}
            className="inline"
          />
        </span>
      );
    }
    return (
      <InputGroup style={{ width: '100%' }}>
        {!compact && (
          <InputGroup.Addon><small>From</small></InputGroup.Addon>
        )}
        <Field
          {...otherProps}
          {...inputFromPropsReplaced}
          fieldType={fieldType}
          value={valueFrom}
          onChange={this.onChangeFrom}
          placeholder={placeholderFrom}
        />
        {!compact && (
          <InputGroup.Addon><small>To</small></InputGroup.Addon>
        )}
        {compact && (
          <InputGroup.Addon><small>-</small></InputGroup.Addon>
        )}
        <Field
          {...otherProps}
          {...inputToPropsReplaced}
          fieldType={fieldType}
          value={valueTo}
          onChange={this.onChangeTo}
          placeholder={placeholderTo}
        />
      </InputGroup>
    );
  }

}

export default Range;
