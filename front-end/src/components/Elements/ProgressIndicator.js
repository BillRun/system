import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
// import { CSSTransitionGroup } from 'react-transition-group'


// <CSSTransitionGroup transitionName="progressindicator" transitionEnterTimeout={enterTimeout} transitionLeaveTimeout={exitTimeout}>
// </CSSTransitionGroup>
const ProgressIndicator = ({ progressIndicator, enterTimeout, exitTimeout }) => (
  <div className="progress-indicator-container" style={{ position: 'fixed', top: 0, width: '100%', zIndex: 5001 }}>
      { progressIndicator && <div key="progressIndicator" className="system-progress-indecator" /> }
  </div>
);


ProgressIndicator.defaultProps = {
  progressIndicator: false,
  enterTimeout: 250,
  exitTimeout: 250,
};

ProgressIndicator.propTypes = {
  progressIndicator: PropTypes.bool,
  enterTimeout: PropTypes.number,
  exitTimeout: PropTypes.number,
};

const mapStateToProps = state => ({
  progressIndicator: state.progressIndicator > 0,
});

export default connect(mapStateToProps)(ProgressIndicator);
