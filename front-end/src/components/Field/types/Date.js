import React from 'react';
import PropTypes from 'prop-types';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { getConfig } from '@/common/Util';

const toDateFnsFormat = format => format.replace(/YYYY/g, 'yyyy').replace(/DD/g, 'dd');

const Date = ({
  editable = true, value, disabled = false, placeholder = '', onChange = () => {}, dateFormat, message = null, minDate,
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
  const selected = (moment.isMoment(value) && value.isValid()) ? value.toDate() : null;
  const minDateValue = moment.isMoment(minDate) ? minDate.toDate() : undefined;
  const onDateChange = date => onChange(date ? moment(date) : null);

  return (
    <DatePicker
      {...otherProps}
      minDate={minDateValue}
      className="form-control"
      dateFormat={toDateFnsFormat(format)}
      selected={selected}
      onChange={onDateChange}
      disabled={disabled}
      placeholderText={placeholderText}
    >
      {message}
    </DatePicker>
  );
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
