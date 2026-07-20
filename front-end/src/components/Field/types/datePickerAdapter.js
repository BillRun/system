import moment from 'moment';

/** Map moment format tokens to date-fns tokens used by react-datepicker v9. */
export const toDateFnsFormat = format =>
  format.replace(/YYYY/g, 'yyyy').replace(/DD/g, 'dd');

/** App state uses moment; react-datepicker v9 uses native Date. */
export const momentToPickerDate = value =>
  (moment.isMoment(value) && value.isValid() ? value.toDate() : undefined);

export const pickerDateToMoment = date => (date ? moment(date) : null);

/**
 * v9 filterDate receives native Date; legacy filters were written for moment.
 * Wrap once here so call sites stay unchanged.
 */
export const adaptFilterDate = filterDate => {
  if (!filterDate) {
    return undefined;
  }
  return date => filterDate(moment.isMoment(date) ? date : moment(date));
};

/**
 * v9 highlightDates / excludeDates expect Date or { date: Date }.
 * Call sites still pass moment (BS3-era contract).
 */
export const adaptDateList = dates => {
  if (!dates) {
    return undefined;
  }
  if (!Array.isArray(dates)) {
    return dates;
  }
  return dates.map((entry) => {
    if (moment.isMoment(entry)) {
      return entry.toDate();
    }
    if (entry && typeof entry === 'object' && entry.date) {
      return {
        ...entry,
        date: moment.isMoment(entry.date) ? entry.date.toDate() : entry.date,
      };
    }
    return entry;
  });
};
