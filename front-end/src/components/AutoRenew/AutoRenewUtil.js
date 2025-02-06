import moment from 'moment';
import { getConfig } from '@/common/Util';


export const lastRenewParser = (item) => { // eslint-disable-line import/prefer-default-export
  const lastRenewDate = item.get('last_renew', false);
  if (!lastRenewDate) {
    return 'Not Renewed Yet';
  }
  return moment(lastRenewDate).format(getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm'));
};
