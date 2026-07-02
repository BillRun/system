import React from 'react';
import PropTypes from 'prop-types';
import { Col, Button } from 'react-bootstrap';
const ErrorInternal500 = ({ onIgnore = null }) => (
  <Col md={12} style={{ textAlign: 'center', marginTop: 50 }}>
    <i className="fa fa-heartbeat fa-fw" style={{ fontSize: 70 }} />
    <h3 style={{ color: '#777' }}>500</h3>
    <h5 style={{ color: 'red' }}>Something went wrong.</h5>
    <br />
    <p>
      <Button variant="link" onClick={() => window.location.reload()}>
        Reload page
      </Button>
      or
      <Button variant="link" onClick={() => window.location = '/'}>
        Return to home page
      </Button>
      { onIgnore && (
        <span>
        or
        <Button variant="link" onClick={onIgnore}>
          Ignore error and continue
        </Button>
      </span>
      )}
    </p>
  </Col>
);

ErrorInternal500.propTypes = {
  onIgnore: PropTypes.func,
};

export default ErrorInternal500;
