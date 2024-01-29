import React from 'react';
import PropTypes from 'prop-types';
import moment from 'moment';
import classNames from 'classnames';


const StateIcon = ({ from, to, status }) => {
  let stateClass = '';
  if (status.length) {
    stateClass = classNames('cycle', {
      expired: status === 'expired',
      future: status === 'future',
      active: status === 'active',
    });
  } else {
    const fromTime = moment(from);
    const toTime = moment(to);
    stateClass = classNames('cycle', {
      expired: toTime.isBefore(moment()),
      future: fromTime.isAfter(moment()),
      active: !toTime.isBefore(moment()) && !fromTime.isAfter(moment()),
    });
  }
  return (<div className={stateClass} />);
};

StateIcon.propTypes = {
  status: PropTypes.string,
  from: PropTypes.string,
  to: PropTypes.string,
};

StateIcon.defaultProps = {
  from: '',
  to: '',
  status: '',
};

export default StateIcon;
