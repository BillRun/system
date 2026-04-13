import React from 'react';
import { connect } from 'react-redux';

import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';

const APIDetails = (props) => (
  <div className="APIDetails">
    <Form horizontal>
      <FormGroup>
        <Col componentClass={ ControlLabel } md={ 1 }>URL</Col>
        <Col lg={ 7 }>
          <input className="form-control disabled" value="http://billrun/api/something" />
        </Col>
      </FormGroup>
      <FormGroup>
        <Col componentClass={ ControlLabel } md={ 1 }>API Token</Col>
        <Col lg={ 7 }>
          <input className="form-control disabled" value="123abc456efg" />
        </Col>
      </FormGroup>
    </Form>
  </div>
);

export default connect()(APIDetails);
