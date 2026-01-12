import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, FormControl, Checkbox } from 'react-bootstrap';
import Field from '@/components/Field';

const Email = ({ data, onChange }) => {

  const transport = data.get('transport', Immutable.Map());
  
  const type = transport.get('type', '');
  const host = transport.get('host', '');
  const port = transport.get('port', '');
  const ssl = transport.get('ssl', '');
  const username = transport.get('username', '');
  const password = transport.get('password', '');
  const authMethod = transport.get('auth', 'login');
  const oauthData = transport.get('oauth2', Immutable.Map());

  const onFieldChange = (field, e) => {
    const value = e.target.value;
    onChange('mailer', ['transport', field], value);
  };

  const onAuthToggle = (e) => {
    const isChecked = e.target.checked;
    onChange('mailer', ['transport', 'auth'], isChecked ? 'oauth2' : 'login');
  };

  const onOAuthChange = (e) => {
    const value = (e && e.target) ? e.target.value : e;
    onChange('mailer', ['transport', 'oauth2'], value);
  };

  const isOAuth = authMethod === 'oauth2';

  return (
    <div>
      <Form horizontal style={{ marginTop: '20px' }}>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>Type</Col>
          <Col sm={6}>
            <FormControl
              type="text"
              value={type}
              onChange={(e) => onFieldChange('type', e)}
            />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>Host</Col>
          <Col sm={6}>
            <FormControl
              type="text"
              value={host}
              onChange={(e) => onFieldChange('host', e)}
            />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>Port</Col>
          <Col sm={6}>
            <FormControl
              type="text"
              value={port}
              onChange={(e) => onFieldChange('port', e)}
            />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>SSL</Col>
          <Col sm={6}>
            <FormControl
              type="text"
              value={ssl}
              placeholder="tls"
              onChange={(e) => onFieldChange('ssl', e)}
            />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>Email Address / Username</Col>
          <Col sm={6}>
            <FormControl
              type="text"
              value={username}
              onChange={(e) => onFieldChange('username', e)}
            />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>Authentication</Col>
          <Col sm={6} style={{ paddingTop: '7px' }}>
            <Checkbox checked={isOAuth} onChange={onAuthToggle}>
              Use OAuth2
            </Checkbox>
          </Col>
        </FormGroup>

        {isOAuth ? (
          <FormGroup>
            <Col componentClass={ControlLabel} sm={2}>OAuth2</Col>
            <Col sm={6}>
              <Field
                fieldType="oauth2"
                label="Connect Mailer"
                editable={true}
                value={oauthData}
                onChange={onOAuthChange}
              />
            </Col>
          </FormGroup>
        ) : (
          <FormGroup>
            <Col componentClass={ControlLabel} sm={2}>Password</Col>
            <Col sm={6}>
              <FormControl
                type="password"
                value={password}
                placeholder="Enter password"
                onChange={(e) => onFieldChange('password', e)}
              />
            </Col>
          </FormGroup>
        )}

      </Form>
    </div>
  );
};

Email.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func.isRequired,
};

Email.defaultProps = {
  data: Immutable.Map(),
};

export default Email;