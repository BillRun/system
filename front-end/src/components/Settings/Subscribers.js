import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, ControlLabel, Col, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import { getFieldName } from '@/common/Util';


const Subscribers = ({ data, typesOptions, onChange }) => {

  const type = data.getIn(['subscriber', 'type'], 'db');
  const auth_url = data.getIn(['external_authentication', 'access_token_url'], '');
  const auth_secret = data.getIn(['external_authentication', 'data', 'client_secret'], '');
  const auth_id = data.getIn(['external_authentication', 'data', 'client_id'], '');
  const url_gsd = data.getIn(['subscriber', 'external_url'], '');
  const url_gad = data.getIn(['account', 'external_url'], '');
  const url_gba = data.getIn(['billable', 'url'], '');

  const disabled = type === 'db';

  const authData = Immutable.Map({
    type: 'oauth2',
    access_token_url: auth_url,
    data: Immutable.Map({
        grant_type: 'client_credentials',
        client_id: auth_id,
        client_secret: auth_secret,
        scope: ''
    }),
    cache: true
  });

  const onChangeType = (value) => {
    onChange('subscribers',['account', 'type'], value);
    onChange('subscribers', ['subscriber', 'type'], value);
  }
  const onChangeAuthUrl = (e) => {
    const { value } = e.target;
    onChange('subscribers', 'external_authentication', authData.setIn(['access_token_url'], value));
  };
  
  const onChangeAuthSecret = (e) => {
    const { value } = e.target;
    onChange('subscribers', 'external_authentication', authData.setIn(['data', 'client_secret'], value));
  };
  const onChangeAuthId = (e) => {
    const { value } = e.target;
    onChange('subscribers', 'external_authentication', authData.setIn(['data', 'client_id'], value));
  };
  
  
  const onChangeUrlGba = (e) => {
    const { value } = e.target;
    onChange('subscribers', ['billable', 'url'], value);
  };
  
  const onChangeUrlGsd = (e) => {
    const { value } = e.target;
    onChange('subscribers', ['subscriber', 'external_url'], value);
  };
  
  const onChangeUrlGad = (e) => {
    const { value } = e.target;
    onChange('subscribers', ['account', 'external_url'], value);
  };

  return (
    <div className="Subscribers">
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
              { getFieldName('type', 'settings')}
          </Col>
          <Col sm={6}>
            <Field
              fieldType="select"
              value={type}
              clearable={false}
              options={typesOptions}
              onChange={onChangeType}
            />
          </Col>
        </FormGroup>
        <Panel header={<h3>{getFieldName('auth', 'settings')}<small> | {getFieldName('auth.type', 'settings')}</small></h3>}>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
                { getFieldName('auth.url', 'settings')}
            </Col>
            <Col sm={6}>
                <Field onChange={onChangeAuthUrl} value={auth_url} disabled={disabled} />
            </Col>
          </FormGroup>

          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
                { getFieldName('auth.secret', 'settings')}
            </Col>
            <Col sm={6}>
                <Field fieldType="password" onChange={onChangeAuthSecret} value={auth_secret} disabled={disabled} />
            </Col>
          </FormGroup>

          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
                { getFieldName('auth.id', 'settings')}
            </Col>
            <Col sm={6}>
                <Field onChange={onChangeAuthId} value={auth_id} disabled={disabled} />
            </Col>
          </FormGroup>

        </Panel>
        <Panel header={<h3>{getFieldName('url', 'settings')}</h3>}>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
                { getFieldName('url.gba', 'settings')}
            </Col>
            <Col sm={6}>
                <Field onChange={onChangeUrlGba} value={url_gba} disabled={disabled} />
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
                { getFieldName('url.gad', 'settings')}
            </Col>
            <Col sm={6}>
                <Field onChange={onChangeUrlGad} value={url_gad} disabled={disabled} />
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
                { getFieldName('url.gsd', 'settings')}
            </Col>
            <Col sm={6}>
                <Field onChange={onChangeUrlGsd} value={url_gsd} disabled={disabled} />
            </Col>
          </FormGroup>
        </Panel>
      </Form>
    </div>
  );
}

Subscribers.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  typesOptions: PropTypes.array,
};

Subscribers.defaultProps = {
  data: Immutable.Map(),
  typesOptions: [
      { value: 'db', label: getFieldName('type.db', 'settings', 'DB1') },
      { value: 'external', label: getFieldName('type.external', 'settings', 'Externa1') },
  ],
};

export default Subscribers;
