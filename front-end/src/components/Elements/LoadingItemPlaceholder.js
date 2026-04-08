import React from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';
const LoadingItemPlaceholder = props => (
  <div>
    <p>{props.loadingLabel}</p>
    { props.onClick && <Button onClick={props.onClick} variant="outline-secondary">{props.buttonLabel}</Button> }
  </div>
);

LoadingItemPlaceholder.propTypes = {
  onClick: PropTypes.func,
  buttonLabel: PropTypes.string,
  loadingLabel: PropTypes.string,
};

LoadingItemPlaceholder.defaultProps = {
  loadingLabel: 'Loading...',
};

export default LoadingItemPlaceholder;
