import React from 'react';
import PropTypes from 'prop-types';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { getConfig } from '@/common/Util';
import {
  adaptDateList,
  adaptFilterDate,
  momentToPickerDate,
  pickerDateToMoment,
  toDateFnsFormat,
} from './datePickerAdapter';

const Date = ({
  editable = true, value, disabled = false, placeholder = '', onChange = () => {}, dateFormat, message = null, minDate,
  filterDate,
  highlightDates,
  excludeDates,
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

  return (
    <DatePicker
      {...otherProps}
      minDate={momentToPickerDate(minDate)}
      filterDate={adaptFilterDate(filterDate)}
      highlightDates={adaptDateList(highlightDates)}
      excludeDates={adaptDateList(excludeDates)}
      className="form-control"
      dateFormat={toDateFnsFormat(format)}
      selected={momentToPickerDate(value) ?? null}
      onChange={date => onChange(pickerDateToMoment(date))}
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
