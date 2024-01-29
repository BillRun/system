import React from 'react';
import classNames from 'classnames';
import { titleCase } from 'change-case';


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