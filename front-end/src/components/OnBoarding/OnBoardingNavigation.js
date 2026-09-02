import React from 'react';
import PropTypes from 'prop-types';

const OnBoardingNavigation = ({ isRunnig = false, isReady = false, isPaused = false, onRun = () => {}, onStart = () => {} }) => {
  const onClick = (handler) => (e) => {
    e.preventDefault();
    handler();
  };

  if (isReady) {
    return (
      <li>
        <a href="#start-tour" onClick={onClick(onStart)}>
          Start Tour
        </a>
      </li>
    );
  }

  if (isPaused) {
    return (
      <li className="running">
        <a href="#resume-tour" onClick={onClick(onRun)}>
          <span><i className="fa fa-play fa-fw" /> Resume Tour</span>
        </a>
      </li>
    );
  }

  if (isRunnig) {
    return (
      <li className="running">
        <a href="#running-tour" onClick={onClick(() => {})}>
          You are in  tour
        </a>
      </li>
    );
  }

  return (null);
};

OnBoardingNavigation.propTypes = {
  isRunnig: PropTypes.bool,
  isPaused: PropTypes.bool,
  isReady: PropTypes.bool,
  onRun: PropTypes.func,
  onStart: PropTypes.func,
};

export default OnBoardingNavigation;
