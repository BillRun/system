import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, ControlLabel, Col, Panel, HelpBlock, Alert } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  getFieldName,
  isEmptyString,
  isValueOn,
  isValidUrl,
} from '@/common/Util';


const Subscribers = ({ data, typesOptions, onChange }) => {

  const [localData, setLocalData] = useState(Immutable.Map());

  useEffect(() => {
    setLocalData(data);
  }, []);

  const type = data.getIn(['subscriber', 'type'], 'db');
  const auth_url = data.getIn(['external_authentication', 'access_token_url'], '');
  const auth_cache = isValueOn(data.getIn(['external_authentication', 'cache'], false));
  const auth_secret = data.getIn(['external_authentication', 'data', 'client_secret'], '');
  const auth_id = data.getIn(['external_authentication', 'data', 'client_id'], '');
  const url_gsd = data.getIn(['subscriber', 'external_url'], '');
  const url_gad = data.getIn(['account', 'external_url'], '');
  const url_gba = data.getIn(['billable', 'url'], '');

  const isSourceDb = type === 'db';
  const validAuthUrl = !isEmptyString(auth_url) && isValidUrl(auth_url) ;
  const validUrlGba = !isEmptyString(url_gba) && isValidUrl(url_gba);
  const validUrlGad = !isEmptyString(url_gad) && isValidUrl(url_gad);
  const validUrlGsd = !isEmptyString(url_gsd) && isValidUrl(url_gsd);

  const isDirtyType = localData.getIn(['subscriber', 'type'], 'db') !== type;
  const isDirtyUrl = localData.getIn(['subscriber', 'external_url'], '') !== url_gsd
    || localData.getIn(['account', 'external_url'], '') !== url_gad
    || localData.getIn(['billable', 'url'], '') !== url_gba;
  const isDirtyAuth = localData.getIn(['external_authentication', 'access_token_url'], '') !== auth_url
    || localData.getIn(['external_authentication', 'data', 'client_secret'], '') !== auth_secret
    || localData.getIn(['external_authentication', 'data', 'client_id'], '') !== auth_id
    || isValueOn(localData.getIn(['external_authentication', 'cache'], false)) !== auth_cache;

  const authData = Immutable.Map({
    type: 'oauth2',
    access_token_url: auth_url,
    cache: auth_cache,
    data: Immutable.Map({
        grant_type: 'client_credentials',
        client_id: auth_id,
        client_secret: auth_secret,
        scope: ''
    }),
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
  const onChangeAuthCache = (e) => {
    const { value } = e.target;
    onChange('subscribers', 'external_authentication', authData.setIn(['cache'], value));
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
    <div className="subscribers">

      {(isDirtyType || isDirtyAuth || isDirtyUrl) && (<Alert bsStyle="warning"> { getFieldName('unsaved_changes')}</Alert>)}

      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
              { getFieldName('ext_subs_type', 'settings')}
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

        {!isSourceDb && (
          <Panel
            header={<h3>{getFieldName('ext_subs_auth', 'settings')}<small> | {authData.get('type', '')}</small></h3>}
            bsStyle={isDirtyAuth ? "warning" : "default"}
          >
            <FormGroup validationState={!validAuthUrl ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('ext_subs_auth.url', 'settings')}
                  <span className="danger-red"> *</span>
              </Col>
              <Col sm={6}>
                  <Field onChange={onChangeAuthUrl} value={auth_url} disabled={isSourceDb} />
                  { isEmptyString(auth_url) && (
                    <HelpBlock className="mb0"><small>{getFieldName('field_required', 'settings', null, {field: getFieldName('ext_subs_auth.url', 'settings')}, '')}</small></HelpBlock>
                  )}
                  { !isEmptyString(auth_url) && !isValidUrl(auth_url) === true && (
                    <HelpBlock className="mb0"><small>URL is not valid</small></HelpBlock>
                  )}
              </Col>
            </FormGroup>
            <FormGroup validationState={isEmptyString(auth_secret) ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('ext_subs_auth.secret', 'settings')}
                  <span className="danger-red"> *</span>
              </Col>
              <Col sm={6}>
                  <Field
                    fieldType="password"
                    value={auth_secret}
                    onChange={onChangeAuthSecret}
                    autoComplete="new-password"
                    disabled={isSourceDb}
                  />
                  { isEmptyString(auth_secret) && (
                    <HelpBlock className="mb0"><small>{getFieldName('field_required', 'settings', null, {field: getFieldName('ext_subs_auth.secret', 'settings')}, '')}</small></HelpBlock>
                  )}
              </Col>
            </FormGroup>
            <FormGroup validationState={isEmptyString(auth_id) ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('ext_subs_auth.id', 'settings')}
                  <span className="danger-red"> *</span>
              </Col>
              <Col sm={6}>
                  <Field onChange={onChangeAuthId} value={auth_id} disabled={isSourceDb} />
                  { isEmptyString(auth_id) && (
                    <HelpBlock className="mb0"><small>{getFieldName('field_required', 'settings', null, {field: getFieldName('ext_subs_auth.id', 'settings')}, '')}</small></HelpBlock>
                  )}
              </Col>
            </FormGroup>
            <FormGroup>
              <Col componentClass={ControlLabel} sm={3} lg={2}/>
              <Col sm={6}>
                <Field
                  fieldType="checkbox"
                  value={auth_cache}
                  onChange={onChangeAuthCache}
                  label={getFieldName('ext_subs_auth_cache', 'settings')}
                  disabled={isSourceDb}
                />
              </Col>
            </FormGroup>
          </Panel>
        )}

        {!isSourceDb && (
          <Panel header={<h3>{getFieldName('ext_subs_url', 'settings')}</h3>} bsStyle={isDirtyUrl ? "warning" : "default"}>
            <FormGroup validationState={!validUrlGba ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('ext_subs_url.gba', 'settings')}
                  <span className="danger-red"> *</span>
              </Col>
              <Col sm={6}>
                  <Field onChange={onChangeUrlGba} value={url_gba} disabled={isSourceDb} />
                  { isEmptyString(url_gba) && (
                    <HelpBlock className="mb0"><small>{getFieldName('field_required', 'settings', null, {field: getFieldName('ext_subs_url.gba', 'settings')}, '')}</small></HelpBlock>
                  )}
                  { !isEmptyString(url_gba) && !isValidUrl(url_gba) === true && (
                    <HelpBlock className="mb0"><small>URL is not valid</small></HelpBlock>
                  )}
              </Col>
            </FormGroup>
            <FormGroup validationState={!validUrlGad ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('ext_subs_url.gad', 'settings')}
                  <span className="danger-red"> *</span>
              </Col>
              <Col sm={6}>
                  <Field onChange={onChangeUrlGad} value={url_gad} disabled={isSourceDb} />
                  { isEmptyString(url_gad) && (
                    <HelpBlock className="mb0"><small>{getFieldName('field_required', 'settings', null, {field: getFieldName('ext_subs_url.gad', 'settings')}, '')}</small></HelpBlock>
                  )}
                  { !isEmptyString(url_gad) && !isValidUrl(url_gad) === true && (
                    <HelpBlock className="mb0"><small>URL is not valid</small></HelpBlock>
                  )}
              </Col>
            </FormGroup>
            <FormGroup validationState={!validUrlGsd ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('ext_subs_url.gsd', 'settings')}
                  <span className="danger-red"> *</span>
              </Col>
              <Col sm={6}>
                  <Field onChange={onChangeUrlGsd} value={url_gsd} disabled={isSourceDb} />
                  { isEmptyString(url_gsd) && (
                    <HelpBlock className="mb0"><small>{getFieldName('field_required', 'settings', null, {field: getFieldName('ext_subs_url.gsd', 'settings')}, '')}</small></HelpBlock>
                  )}
                  { !isEmptyString(url_gsd) && !isValidUrl(url_gsd) === true && (
                    <HelpBlock className="mb0"><small>URL is not valid</small></HelpBlock>
                  )}
              </Col>
            </FormGroup>
          </Panel>
        )}
      </Form>
    </div>
  );
}

Subscribers.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func.isRequired,
  typesOptions: PropTypes.array,
};

Subscribers.defaultProps = {
  data: Immutable.Map(),
  typesOptions: [
    { value: 'db', label: getFieldName('ext_subs_type.db', 'settings', 'DB') },
    { value: 'external', label: getFieldName('ext_subs_type.external', 'settings', 'External') },
  ],
};

export default Subscribers;
