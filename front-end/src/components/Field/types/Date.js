import React from 'react';
import PropTypes from 'prop-types';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { getConfig } from '@/common/Util';

const Date = ({
  editable, value, disabled, placeholder, onChange, dateFormat, message, minDate,
  ...otherProps
}) => {
  const format = dateFormat || getConfig('dateFormat', 'DD/MM/YYYY');
  if (!editable) {
    const displayValue = (moment.isMoment(value) && value.isValid()) ? value.format(format) : value;
    return (
      <div className="non-editable-field">{ displayValue }</div>
    );
  }
  const placeholderText = (disabled && !value) ? '' : placeholder;
  const selected = (moment.isMoment(value) && value.isValid()) ? value : null;
  const minDateValue = moment.isMoment(minDate) ? minDate : undefined;

  return (
    <DatePicker
      {...otherProps}
      minDate={minDateValue}
      className="form-control"
      dateFormat={format}
      selected={selected}
      onChange={onChange}
      disabled={disabled}
      placeholderText={placeholderText}
    >
      {message}
    </DatePicker>
  );
};

Date.defaultProps = {
  required: false,
  disabled: false,
  editable: true,
  placeholder: '',
  message: null,
  onChange: () => {},
};

Date.propTypes = {
  value: PropTypes.instanceOf(moment),
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  placeholder: PropTypes.string,
  dateFormat: PropTypes.string,
  message: PropTypes.node,
  onChange: PropTypes.func,
};

export default Date;
