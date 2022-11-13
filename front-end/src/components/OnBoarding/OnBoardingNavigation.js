import React from 'react';
import PropTypes from 'prop-types';
import { MenuItem } from 'react-bootstrap';


const OnBoardingNavigation = ({ isRunnig, isReady, isPaused, eventKeyBase, onRun, onStart }) => {
  if (isReady) {
    return (
      <MenuItem eventKey={eventKeyBase} onClick={onStart} active={false}>
        Start Tour
      </MenuItem>
    );
  }

  if (isPaused) {
    return (
      <MenuItem eventKey={eventKeyBase} onClick={onRun} className="running" active={true}>
        <span><i className="fa fa-play fa-fw" /> Resume Tour</span>
      </MenuItem>
    );
  }

  if (isRunnig) {
    return (
      <MenuItem eventKey={eventKeyBase} className="running" active={true}>
        You are in  tour
      </MenuItem>
    );
  }

  return (null);
};


OnBoardingNavigation.propTypes = {
  eventKeyBase: PropTypes.number,
  isRunnig: PropTypes.bool,
  isPaused: PropTypes.bool,
  isReady: PropTypes.bool,
  onRun: PropTypes.func,
  onStart: PropTypes.func,
};

OnBoardingNavigation.defaultProps = {
  eventKeyBase: 1,
  isRunnig: false,
  isReady: false,
  isPaused: false,
  onRun: () => {},
  onStart: () => {},
};

export default OnBoardingNavigation;
