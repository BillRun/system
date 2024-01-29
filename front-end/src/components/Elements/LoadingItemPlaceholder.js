import React from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';


const LoadingItemPlaceholder = props => (
  <div>
    <p>{props.loadingLabel}</p>
    { props.onClick && <Button onClick={props.onClick} bsStyle="default">{props.buttonLabel}</Button> }
  </div>
);

LoadingItemPlaceholder.defaultProps = {
  buttonLabel: 'Back',
  loadingLabel: 'Loading...',
};

LoadingItemPlaceholder.propTypes = {
  onClick: PropTypes.func,
  buttonLabel: PropTypes.string,
  loadingLabel: PropTypes.string,
};

export default LoadingItemPlaceholder;
