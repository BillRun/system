import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Panel, Col, Form, FormGroup, ControlLabel } from 'react-bootstrap';
import { ActionButtons } from '@/components/Elements';
import Field from '@/components/Field';
import Help from '../Help';


const EventSettings = ({ eventsSettings, methodOptions, decoderOptions, ...props }) => {
  const onChange = eventNotifier => (e) => {
    const { value, id } = e.target;
    props.onEdit(eventNotifier, id, value);
  };

  const onChangeSelect = (eventNotifier, id) => (value) => {
    props.onEdit(eventNotifier, id, value);
  };

  const pasteSplit = (data) => {
    const separators = [',', ';', '\\(', '\\)', '\\*', '/', ':', '\\?', '\n', '\r', '\t'];
    return data.split(new RegExp(separators.join('|'))).map(d => d.trim());
  };

  const onChangeGlobalMail = (val) => {
    props.onEdit('email', 'global_addresses', Immutable.List(val));
  };

  return (
    <Form horizontal>
      <Col sm={12}>
        <Panel header="HTTP" key="http">
          <FormGroup>
            <Col sm={2} componentClass={ControlLabel}>
              Url <Help contents="URL to send the requests to" />
            </Col>
            <Col sm={6}>
              <Field id="url" value={eventsSettings.getIn(['http', 'url'], '')} onChange={onChange('http')} />
            </Col>
          </FormGroup>
          <FormGroup >
            <Col sm={2} componentClass={ControlLabel}>
              Method <Help contents="HTTP method" />
            </Col>
            <Col sm={6}>
              <Field
                fieldType="select"
                value={eventsSettings.getIn(['http', 'method'], '')}
                onChange={onChangeSelect('http', 'method')}
                options={methodOptions}
              />
            </Col>
          </FormGroup>
          <FormGroup>
            <Col sm={2} componentClass={ControlLabel}>
              Decoder <Help contents="Method to decode HTTP response" />
            </Col>
            <Col sm={6}>
              <Field
                fieldType="select"
                value={eventsSettings.getIn(['http', 'decoder'], '')}
                onChange={onChangeSelect('http', 'decoder')}
                options={decoderOptions}
              />
            </Col>
          </FormGroup>
        </Panel>
        <Panel header="Mail" key="mail">
          <FormGroup>
            <Col sm={2} componentClass={ControlLabel}>
              Mails <Help contents="Send events to the following email addresses (For supported events)" />
            </Col>
            <Col sm={6}>
              <Field
                fieldType="tags"
                value={eventsSettings.getIn(['email', 'global_addresses'], Immutable.List()).toArray()}
                onChange={onChangeGlobalMail}
                addOnPaste
                pasteSplit={pasteSplit}
              />
            </Col>
          </FormGroup>
        </Panel>
      </Col>
      <Col sm={12}>
        <ActionButtons onClickSave={props.onSave} onClickCancel={props.onCancel} />
      </Col>
    </Form>
  );
};

EventSettings.propTypes = {
  eventsSettings: PropTypes.instanceOf(Immutable.Map),
  methodOptions: PropTypes.array,
  decoderOptions: PropTypes.array,
  onSave: PropTypes.func.isRequired,
  onEdit: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
};

EventSettings.defaultProps = {
  eventsSettings: Immutable.Map(),
  methodOptions: [{ value: 'post', label: 'POST' }, { value: 'get', label: 'GET' }],
  decoderOptions: [{ value: 'json', label: 'JSON' }, { value: 'xml', label: 'XML' }],
};

export default EventSettings;
