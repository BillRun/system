import React from 'react';
import classNames from 'classnames';
import { titleCase } from 'change-case';
import moment from 'moment';
import Field from '@/components/Field';
import { StateIcon, WithTooltip } from '@/components/Elements';
import {
  getConfig,
  getFieldName,
} from '@/common/Util';


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

export const runOnTitleParser = (item) => {
  const runOn = item.getIn(['body', 'config', 'run_on'], '');
  switch (runOn) {
    case 'include': return `${getFieldName('include_aids', 'charging_process', 'Exclude Customer IDs')}`;
    case 'exclude': return `${getFieldName('exclude_aids', 'charging_process', 'Exclude Customer IDs')}`;
    default: return getFieldName('run_on', 'charging_process')
  }
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
      case 'total_debt': return getFieldName('total_debt', 'charging_process', 'Total Debt');
      case 'per_bill': return getFieldName('per_bill', 'charging_process', 'Per Bill');
      default: return '';
    }
}

export const chargeRunOnParser = (item) => {
  const runOn = item.getIn(['body', 'config', 'run_on'], '');
  switch (runOn) {
    case 'include':
      return <Field fieldType="json" className="included-excluded-items" value={item.getIn(['body', 'config', 'include'], [])} editable={false} />
    case 'exclude':
      return <Field fieldType="json" className="included-excluded-items" value={item.getIn(['body', 'config', 'exclude'], [])} editable={false} />
    default:
      return <Field value={getFieldName('charging_type.all', 'charging_process', 'All')} editable={false} />
  }
}

export const scheduleChargeParser = (item) => {
  let schedule = item.get('schedule', '');
  if (schedule === '') {
    return 'Not a scheduled';
  }
  schedule = moment(schedule);
  if (moment.isMoment(schedule) && schedule.isValid()) {
    const datetimeFormat = getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm');
    return schedule.format(datetimeFormat);
  }
  return '-';
}

export const cancelledChargeParser = (item) => item.get('cancelled', '') === 1 ? 'Yes' : 'No';

export const statusChargeParser = (item) => {
  if (item.get('cancelled', '') === 1) {
    return (<WithTooltip helpText="Canceled"><StateIcon status="removed" /></WithTooltip>);
  }
  if (item.get('active', false)) {
    return (<WithTooltip helpText="In Progress"><StateIcon status="active" /></WithTooltip>);
  }
  const scheduleTime = moment(item.get('schedule', ''));
  if (moment.isMoment(scheduleTime) && scheduleTime.isValid() && scheduleTime.isAfter(moment())) {
    return (<WithTooltip helpText="Schedule"><StateIcon status="future" /></WithTooltip>);
  }
  if (item.get('start_time', '') === '') {
    return (<WithTooltip helpText="Idle"><StateIcon status="idle" /></WithTooltip>);
  }
  return (<WithTooltip helpText="Completed"><StateIcon status="expired" /></WithTooltip>);
};