import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Panel, Col, HelpBlock, FormGroup, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  getConfig,
  formatSelectOptions,
} from '@/common/Util';

const CollectionSettings = ({ process, index, errors, httpMethods, onChange }) => {

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

  const name = process.getIn(['name'], '');
  const label = process.getIn(['label'], '');
  const minDebt = process.getIn(['settings', 'min_debt'], '');
  const changeStateUrl = process.getIn(['settings', 'change_state_url'], '');
  const changeStateMethod =process.getIn(['settings', 'change_state_method'], '');
  const methodOptions = httpMethods.map(formatSelectOptions).toArray();
  return (
    <Col sm={12}>
      <Panel header="General Process Settings">
        <FormGroup validationState={errors.has([index, 'label'].join('.')) ? 'error' : null}>
          <Col sm={2} componentClass={ControlLabel}>
            Title
          </Col>
          <Col sm={6}>
            <Field value={label} onChange={onChangeLabel} />
            { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'label'].join('.'), '')}</HelpBlock> }
          </Col>
        </FormGroup>
        <FormGroup validationState={errors.has([index, 'name'].join('.')) ? 'error' : null}>
          <Col sm={2} componentClass={ControlLabel}>
            Key
          </Col>
          <Col sm={6}>
            <Field value={name} onChange={onChangeName} />
            { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'name'].join('.'), '')}</HelpBlock> }
          </Col>
        </FormGroup>
        <FormGroup validationState={errors.has([index, 'settings', 'min_debt'].join('.')) ? 'error' : null}>
          <Col sm={2} componentClass={ControlLabel}>
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
          <Col sm={2} componentClass={ControlLabel}>
            URL
          </Col>
          <Col sm={6}>
            <Field value={changeStateUrl} onChange={onChangeChangeStateUrl} />
            { errors.has([index, 'label'].join('.')) && <HelpBlock>{errors.get([index, 'settings', 'change_state_url'].join('.'), '')}</HelpBlock> }
          </Col>
        </FormGroup>
        <FormGroup validationState={errors.has([index, 'settings', 'change_state_method'].join('.')) ? 'error' : null}>
          <Col sm={2} componentClass={ControlLabel}>
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
      </Panel>
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

CollectionSettings.defaultProps = {
  process: Immutable.Map(),
  errors: Immutable.Map(),
  index: 0,
  httpMethods: getConfig(['collections', 'http', 'methods'], Immutable.List()),
};

export default CollectionSettings;
