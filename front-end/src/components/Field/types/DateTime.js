import React from 'react';
import PropTypes from 'prop-types';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { getConfig } from '@/common/Util';

const toDateFnsFormat = format => format.replace(/YYYY/g, 'yyyy').replace(/DD/g, 'dd');

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
    maxDate,
    ...otherProps
  } = props;
  const resolvedDateFormat = dateFormat || getConfig('dateFormat', 'DD/MM/YYYY');
  const resolvedTimeFormat = timeFormat || getConfig('timeFormat', 'HH:mm');
  const dateTimeFormat = `${resolvedDateFormat} ${resolvedTimeFormat}`;
  if (!editable) {
    const displayValue = (moment.isMoment(value) && value.isValid())
      ? value.format(dateTimeFormat)
      : value;
    return (
      <div className="non-editable-field">{ displayValue }</div>
    );
  }
  const onDateTimeChangeRaw = (newDate) => {
    const date = moment( (newDate).target.value, dateTimeFormat );
    onDateTimeChange( date );

  }
  const onDateTimeChange = (newDate) => {
    const utcDate = newDate ? moment(newDate).utc() : '';
    onChange(utcDate);
  }
  const placeholderText = (disabled && !value) ? '' : placeholder;
  const selected = (moment.isMoment(value) && value.isValid()) ? value.local().toDate() : null;
  const minDateValue = moment.isMoment(minDate) ? minDate.toDate() : undefined;
  const maxDateValue = moment.isMoment(maxDate) ? maxDate.toDate() : undefined;
  return (
    <DatePicker
      {...otherProps}
      minDate={minDateValue}
      maxDate={maxDateValue}
      calendarClassName="date-picker-with-time"
      className="form-control DatePickerTime"
      showTimeSelect
      timeIntervals={timeIntervals}
      dateFormat={toDateFnsFormat(dateTimeFormat)}
      timeFormat={resolvedTimeFormat}
      selected={selected}
      onChange={onDateTimeChange}
      onChangeRaw={onDateTimeChangeRaw}
      disabled={disabled}
      placeholderText={placeholderText}
    >
      {message}
    </DatePicker>
  );
};

DateTime.propTypes = {
  value: PropTypes.oneOfType([
    PropTypes.instanceOf(moment),
    PropTypes.oneOf([null]),
  ]),
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  placeholder: PropTypes.string,
  dateFormat: PropTypes.string,
  timeFormat: PropTypes.string,
  timeIntervals: PropTypes.number,
  minDate: PropTypes.oneOfType([
    PropTypes.instanceOf(moment),
    PropTypes.oneOf([null]),
  ]),
  maxDate: PropTypes.oneOfType([
    PropTypes.instanceOf(moment),
    PropTypes.oneOf([null]),
  ]),
  message: PropTypes.node,
  onChange: PropTypes.func,
};

export default DateTime;
