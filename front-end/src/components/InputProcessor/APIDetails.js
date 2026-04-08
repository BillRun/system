import React from 'react';
import { connect } from 'react-redux';

import { Form, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup } from '@/common/BootstrapCompat';
const APIDetails = (props) => (
  <div className="APIDetails">
    <Form>
      <FormGroup>
        <Col as={ ControlLabel } md={ 1 }>URL</Col>
        <Col lg={ 7 }>
          <input className="form-control disabled" value="http://billrun/api/something" />
        </Col>
      </FormGroup>
      <FormGroup>
        <Col as={ ControlLabel } md={ 1 }>API Token</Col>
        <Col lg={ 7 }>
          <input className="form-control disabled" value="123abc456efg" />
        </Col>
      </FormGroup>
    </Form>
  </div>
);

export default connect()(APIDetails);
