import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, Col, Form } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock, Panel } from '@/common/BootstrapCompat';
import Field from '@/components/Field';
import { CreateButton } from '@/components/Elements';
import {
  getConfig,
  formatSelectOptions,
} from '@/common/Util';

const CollectionSettings = ({ process = Immutable.Map(), index = 0, errors = Immutable.Map(), httpMethods = getConfig(['collections', 'http', 'methods'], Immutable.List()), onChange }) => {

  const onChangeName = (e) => {
    const { value } = e.target;
    const cleanValue = value.toLowerCase().replace(getConfig('keyCleanRegex', /./), "_");
    onChange(['name'], cleanValue);
  }
  const onChangeLabel = (e) => {
    const { value } = e.target;
    onChange(['label'], value);
  }
  const onChangeMinDeb = (e) => {
    const { value } = e.target;
    onChange(['settings', 'min_debt'], value);
  }

  const onChangeChangeStateUrl = (e) => {
    const { value } = e.target;
    onChange(['settings', 'change_state_url'], value);
  }

  const onChangeChangeStateMethod = (value) => {
    onChange(['settings', 'change_state_method'], value);
  }

  const onAddChangeStateHeader = () => {
    const headers = process.getIn(['settings', 'change_state_headers'], Immutable.List());
    onChange(['settings', 'change_state_headers'], headers.push(Immutable.Map({ key: '', value: '' })));
  }

  const onChangeChangeStateHeader = (headerIndex, field) => (e) => {
    const { value } = e.target;
    onChange(['settings', 'change_state_headers', headerIndex, field], value);
  }

  const onRemoveChangeStateHeader = headerIndex => () => {
    const headers = process.getIn(['settings', 'change_state_headers'], Immutable.List());
    onChange(['settings', 'change_state_headers'], headers.delete(headerIndex));
  }

  const name = process.getIn(['name'], '');
  const label = process.getIn(['label'], '');
  const minDebt = process.getIn(['settings', 'min_debt'], '');
  const changeStateUrl = process.getIn(['settings', 'change_state_url'], '');
  const changeStateMethod =process.getIn(['settings', 'change_state_method'], '');
  const changeStateHeaders = process.getIn(['settings', 'change_state_headers'], Immutable.List());
  const methodOptions = httpMethods.map(formatSelectOptions).toArray();
  return (
    <Col sm={12}>
      <Form className="form-horizontal">
        <Panel header="General Process Settings" className="mb10">
        <FormGroup validationState={errors.has([index, 'label'].join('.')) ? 'error' : null}>
            <Col sm={2} as={ControlLabel}>
              Title
            </Col>
            <Col sm={6}>
              <Field value={label} onChange={onChangeLabel} />
              { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'label'].join('.'), '')}</HelpBlock> }
            </Col>
          </FormGroup>
        <FormGroup validationState={errors.has([index, 'name'].join('.')) ? 'error' : null}>
            <Col sm={2} as={ControlLabel}>
              Key
            </Col>
            <Col sm={6}>
              <Field value={name} onChange={onChangeName} />
              { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'name'].join('.'), '')}</HelpBlock> }
            </Col>
          </FormGroup>
        <FormGroup validationState={errors.has([index, 'settings', 'min_debt'].join('.')) ? 'error' : null}>
            <Col sm={2} as={ControlLabel}>
              Minimum debt
            </Col>
            <Col sm={6}>
              <Field value={minDebt} onChange={onChangeMinDeb} fieldType="number" />
              { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'settings', 'min_debt'].join('.'), '')}</HelpBlock> }
            </Col>
          </FormGroup>
        </Panel>

        <Panel header={
          <h4>Collection State Change<br />
            <small>
              HTTP requests will be triggered to this URL when a customer
              enters / exits from collection
            </small>
          </h4>}
        >
        <FormGroup validationState={errors.has([index, 'settings', 'change_state_url'].join('.')) ? 'error' : null}>
            <Col sm={2} as={ControlLabel}>
              URL
            </Col>
            <Col sm={6}>
              <Field value={changeStateUrl} onChange={onChangeChangeStateUrl} />
              { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'settings', 'change_state_url'].join('.'), '')}</HelpBlock> }
            </Col>
          </FormGroup>
        <FormGroup validationState={errors.has([index, 'settings', 'change_state_method'].join('.')) ? 'error' : null}>
            <Col sm={2} as={ControlLabel}>
              HTTP Method
            </Col>
            <Col sm={6}>
              <Field
                fieldType="select"
                options={methodOptions}
                onChange={onChangeChangeStateMethod}
                value={changeStateMethod}
                clearable={false}
              />
              { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'settings', 'change_state_method'].join('.'), '')}</HelpBlock> }
            </Col>
          </FormGroup>
        <FormGroup>
            <Col sm={2} as={ControlLabel}>
              HTTP Headers
            </Col>
            <Col sm={6}>
              { !changeStateHeaders.isEmpty() && (
                <div className="form-group row form-inner-edit-row mr0 ml0">
                  <div className="col-sm-5"><label>Key</label></div>
                  <div className="col-sm-5"><label>Value</label></div>
                </div>
              )}
              { changeStateHeaders.map((header, headerIndex) => (
                <div className="form-group row form-inner-edit-row mr0 ml0" key={`change_state_header_${headerIndex}`}>
                  <div className="col-sm-5">
                    <Field value={header.get('key', '')} onChange={onChangeChangeStateHeader(headerIndex, 'key')} />
                  </div>
                  <div className="col-sm-5">
                    <Field value={header.get('value', '')} onChange={onChangeChangeStateHeader(headerIndex, 'value')} />
                  </div>
                  <div className="col-sm-2 actions">
                    <Button onClick={onRemoveChangeStateHeader(headerIndex)} size="sm"><i className="fa fa-trash-o danger-red" />&nbsp;Remove</Button>
                  </div>
                </div>
              )).toArray()}
              <CreateButton onClick={onAddChangeStateHeader} label="Add Header" buttonStyle={{}} />
            </Col>
          </FormGroup>
        </Panel>
      </Form>
    </Col>
  );
}

CollectionSettings.propTypes = {
  process: PropTypes.instanceOf(Immutable.Map),
  errors: PropTypes.instanceOf(Immutable.Map),
  httpMethods: PropTypes.instanceOf(Immutable.List),
  index: PropTypes.number,
  onChange: PropTypes.func.isRequired,
}

export default CollectionSettings;
