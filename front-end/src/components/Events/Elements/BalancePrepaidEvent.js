import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, Panel, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import BalancePrepaidEventCondition from './BalancePrepaidEventCondition';
import {
  validateFieldEventCode,
} from '@/actions/eventActions';


class BalancePrepaidEvent extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    errors: PropTypes.instanceOf(Immutable.Map),
    setError: PropTypes.func.isRequired,
    updateField: PropTypes.func.isRequired,
  };

  static defaultProps = {
    item: Immutable.Map(),
    errors: Immutable.Map(),
  };

  onChangeField = (path, value) => {
    this.props.updateField(path, value);
  };

  onChangeEventCode = (e) => {
    const { value } = e.target;
    const isValid = validateFieldEventCode(value);
    this.props.setError('event_code', isValid === true ? null : isValid);
    this.props.updateField(['event_code'], value);
  };

  onChangeDescription = (e) => {
    const { value } = e.target;
    this.props.updateField(['event_description'], value);
  };

  onChangeActive = (e) => {
    const { value } = e.target;
    this.props.updateField(['active'], value === 'yes');
  };

  render() {
    const { item, errors, setError } = this.props;
    const isEventCodeError = errors.get('event_code', false);
    const conditions = item.get('conditions', Immutable.List());
    return (
      <Form horizontal>
        <Panel header={<span>Details</span>}>
          <FormGroup validationState={isEventCodeError ? 'error' : null}>
            <Col componentClass={ControlLabel} sm={3}>
              Event Code
              <span className="danger-red"> *</span>
            </Col>
            <Col sm={7}>
              <Field id="label" onChange={this.onChangeEventCode} value={item.get('event_code', '')} />
              { isEventCodeError && (
                <HelpBlock>{isEventCodeError}</HelpBlock>
              )}
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>
              Description
            </Col>
            <Col sm={7}>
              <Field id="description" onChange={this.onChangeDescription} value={item.get('event_description', '')} />
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>Status</Col>
            <Col sm={7}>
              <span>
                <span className="mr20 inline">
                  <Field
                    fieldType="radio"
                    onChange={this.onChangeActive}
                    name="step-active-status"
                    value="yes"
                    label="Active"
                    checked={item.get('active', true)}
                  />
                </span>
                <span className="inline">
                  <Field
                    fieldType="radio"
                    onChange={this.onChangeActive}
                    name="step-active-status"
                    value="no"
                    label="Not Active"
                    checked={!item.get('active', true)}
                  />
                </span>
              </span>
            </Col>
          </FormGroup>
        </Panel>

        <Panel header={<span>Condition</span>}>
          <FormGroup>
            <Col sm={12}>
              <BalancePrepaidEventCondition
                conditions={conditions}
                onChangeField={this.onChangeField}
                errors={errors}
                setError={setError}
              />
            </Col>
          </FormGroup>
        </Panel>
      </Form>
    );
  }
}


export default BalancePrepaidEvent;
