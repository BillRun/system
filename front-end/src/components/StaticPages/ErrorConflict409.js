import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Link } from 'react-router';
import { Col, Button } from 'react-bootstrap';

import { userDoLogout } from '@/actions/userActions';


const ErrorConflict409 = ({ logout }) => (
  <Col md={12} style={{ textAlign: 'center', marginTop: 50 }}>
    <i className="fa fa-smile-o fa-fw" style={{ fontSize: 70 }} />
    <h5 style={{ color: '#777' }}>You already logged in</h5>
    <br />
    <p>
      <Link to="/">Return to home page</Link>
      or
      <Button variant="link" onClick={logout}>
        <i className="fa fa-sign-out fa-fw" />
        Logout
      </Button>
    </p>
  </Col>
);

ErrorConflict409.propTypes = {
  logout: PropTypes.func.isRequired,
};


const mapDispatchToProps = dispatch => ({
  logout: dispatch(userDoLogout()),
});

export default connect(null, mapDispatchToProps)(ErrorConflict409);
