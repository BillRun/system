import React from 'react';
import classNames from 'classnames';
import { titleCase } from 'change-case';
import moment from 'moment';
import Field from '@/components/Field';
import { StateIcon, WithTooltip } from '@/components/Elements';
import {
  getConfig,
  getFieldName,
  getChargeStatus,
} from '@/common/Util';


export const md5ShorterParser = (item) => {
  const md5 = item.get('md5', '');
  const shortMd5 = (md5 !== '') ? md5.substring(0, 8) : '-';
  return <WithTooltip helpText={md5}><span className="clickable">{shortMd5}</span></WithTooltip>;
}

export const rateTitleParser = (item) => {
  const description = item.get('description', '');
  if (description !== '') {
    return description;
  }
  return item.get('key', '');
}

export const statusParser = (item) => {
  const status = item.get('status', '');
  const labelClass = classNames('non-editable-field label', {
    'label-info': status === 'open',
    'label-success': status === 'accept',
    'label-danger':  status === 'reject',
  });
  let statusLabel = status.toLocaleLowerCase();
  if (statusLabel === 'accept') {
    statusLabel = 'accepted';
  } else if (statusLabel === 'reject') {
    statusLabel = 'rejected';
  }
  return (
    <span className={labelClass}>{titleCase(statusLabel)}</span>
  );
}

export const chargeRunOnTitleParser = (item) => {
  const include = item.getIn(['body', 'config', 'include'], null);
  if (include && include.size > 0) {
    return `${getFieldName('include_aids', 'charging_process', 'Exclude Customer IDs')}`;
  }
  const exclude = item.getIn(['body', 'config', 'exclude'], null);
  if (exclude && exclude.size > 0) {
    return `${getFieldName('exclude_aids', 'charging_process', 'Exclude Customer IDs')}`;
  }
  return `${getFieldName('charging_type.all', 'charging_process', 'All')}`;
}

export const chargeRunOnParser = (item) => {
  const include = item.getIn(['body', 'config', 'include'], null);
  if (include && include.size > 0) {
    return <Field fieldType="json" className="included-excluded-items" value={include} editable={false} />
  }
  const exclude = item.getIn(['body', 'config', 'exclude'], null);
  if (exclude && exclude.size > 0) {
    return <Field fieldType="json" className="included-excluded-items" value={exclude} editable={false} />
  }
  return <Field value={getFieldName('charging_type.all', 'charging_process', 'All')} editable={false} />
}

export const chargeTypeParser = (item) => {
    const mode = item.getIn(['body', 'config', 'mode'], '');
    switch (mode) {
      case 'charge': return getFieldName('charging_type.charge', 'charging_process', 'Charge');
      case 'refund': return getFieldName('charging_type.refund', 'charging_process', 'Refund');
      default: return getFieldName('charging_type.all', 'charging_process', 'All');
    }
}

export const chargePayModeParser = (item) => {
    const payMode = item.getIn(['body', 'config', 'pay_mode'], '');
    switch (payMode) {
      case 'one_payment': return getFieldName('total_debt', 'charging_process', 'Total Debt');
      case 'multiple_payments': return getFieldName('per_bill', 'charging_process', 'Per Bill');
      default: return '';
    }
}

export const scheduleChargeParser = (item) => {
  let schedule = item.get('schedule', '');
  if (schedule === '') {
    return getFieldName('not_schedule', 'charging_process', 'Not Scheduled');
  }
  schedule = moment(schedule);
  if (moment.isMoment(schedule) && schedule.isValid()) {
    const datetimeFormat = getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm');
    return schedule.format(datetimeFormat);
  }
  return '-';
}

export const cancelledChargeParser = (item) => getChargeStatus(item) === 'cancelled' ? 'Yes' : 'No';

export const statusIconChargeParser = (item) => {
  const status = getChargeStatus(item);
  switch (status) {
    case 'cancelled':
      return (<WithTooltip helpText="Canceled"><StateIcon status="removed" /></WithTooltip>);
    case 'future':
      return (<WithTooltip helpText="Schedule"><StateIcon status="future" /></WithTooltip>);
    case 'idle':
      return (<WithTooltip helpText="Idle"><StateIcon status="idle" /></WithTooltip>);
    case 'active':
      return (<WithTooltip helpText="In Progress"><StateIcon status="active" /></WithTooltip>);
    case 'done':
    default:
      return (<WithTooltip helpText="Completed"><StateIcon status="expired" /></WithTooltip>);
  }
};