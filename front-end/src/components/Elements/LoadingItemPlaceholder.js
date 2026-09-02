import React from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';
const LoadingItemPlaceholder = ({ loadingLabel = 'Loading...', onClick, buttonLabel }) => (
  <div>
    <p>{loadingLabel}</p>
    { onClick && <Button onClick={onClick} variant="outline-secondary">{buttonLabel}</Button> }
  </div>
);

LoadingItemPlaceholder.propTypes = {
  onClick: PropTypes.func,
  buttonLabel: PropTypes.string,
  loadingLabel: PropTypes.string,
};

export default LoadingItemPlaceholder;
