import React from 'react';
import PropTypes from 'prop-types';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { getConfig } from '@/common/Util';

const DateTime = (props) => {
  const {
    editable,
    value,
    disabled,
    placeholder,
    onChange,
    dateFormat,
    timeFormat,
    message,
    timeIntervals,
    minDate,
    ...otherProps
  } = props;
  const dateTimeFormat = `${dateFormat} ${timeFormat}`;
  if (!editable) {
    const displayValue = (moment.isMoment(value) && value.isValid())
      ? value.format(dateTimeFormat)
      : value;
    return (
      <div className="non-editable-field">{ displayValue }</div>
    );
  }
  const onDateTimeChange = (newDate) => {
    const utcDate = moment.isMoment(newDate) && newDate.isValid() ? newDate.utc() : '';
    onChange(utcDate);
  }
  const placeholderText = (disabled && !value) ? '' : placeholder;
  const selected = (moment.isMoment(value) && value.isValid()) ? value : null;
  const minDateValue = moment.isMoment(minDate) ? minDate : undefined;
  return (
    <DatePicker
      {...otherProps}
      minDate={minDateValue}
      calendarClassName="date-picker-with-time"
      className="form-control DatePickerTime"
      showTimeSelect
      timeIntervals={timeIntervals}
      dateFormat={dateTimeFormat}
      timeFormat={timeFormat}
      selected={selected}
      onChange={onDateTimeChange}
      disabled={disabled}
      placeholderText={placeholderText}
    >
      {message}
    </DatePicker>
  );
};

DateTime.defaultProps = {
  required: false,
  disabled: false,
  editable: true,
  placeholder: '',
  message: null,
  dateFormat: getConfig('dateFormat', 'DD/MM/YYYY'),
  timeFormat: getConfig('timeFormat', 'HH:mm'),
  timeIntervals: 15,
  onChange: () => {},
};

DateTime.propTypes = {
  value: PropTypes.instanceOf(moment),
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  placeholder: PropTypes.string,
  dateFormat: PropTypes.string,
  timeFormat: PropTypes.string,
  timeIntervals: PropTypes.number,
  message: PropTypes.node,
  onChange: PropTypes.func,
};

export default DateTime;
