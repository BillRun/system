import { getItemDateValue, getConfig } from '@/common/Util';

export const getDateToDisplay = (cycle, field) => getItemDateValue(cycle, field).format(getConfig('dateFormat', 'DD/MM/YYYY'));

export const getCycleName = cycle => `Cycle of ${getDateToDisplay(cycle, 'start_date')} - ${getDateToDisplay(cycle, 'end_date')}`;
