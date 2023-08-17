import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, ControlLabel, HelpBlock, Col } from 'react-bootstrap';
import { PlanDescription } from '../../language/FieldDescriptions';
import Help from '../Help';
import Field from '@/components/Field';
import { getConfig } from '@/common/Util';



export default class ChargingPlanDetails extends Component {


  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map).isRequired,
    mode: PropTypes.string.isRequired,
    onChangeField: PropTypes.func.isRequired,
    errorMessages: PropTypes.object,
  }

  static defaultProps = {
    errorMessages: {
      name: {
        allowedCharacters: 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
      },
    },
  };

  state = {
    errors: {
      name: '',
    },
  }

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    return !Immutable.is(this.props.item, nextProps.item) || this.props.mode !== nextProps.mode;
  }

  onChangeName = (e) => {
    const { errorMessages: { name: { allowedCharacters } } } = this.props;
    const { errors } = this.state;
    const value = e.target.value.toUpperCase();
    const newError = (!getConfig('keyUppercaseRegex', /./).test(value)) ? allowedCharacters : '';
    this.setState({ errors: Object.assign({}, errors, { name: newError }) });
    this.props.onChangeField(['name'], value);
  }

  onChangeDescription = (e) => {
    const { value } = e.target;
    this.props.onChangeField(['description'], value);
  }

  onChangeOperation = (value) => {
    this.props.onChangeField(['operation'], value);
  }

  onChangeChargingValue = (e) => {
    const { value } = e.target;
    this.props.onChangeField(['charging_value'], value);
  }

  render() {
    const { errors } = this.state;
    const { item, mode } = this.props;
    const editable = (mode !== 'view');
    const operationOptions = [
      { value: 'new', label: 'New' },
      { value: 'inc', label: 'Increment' },
      { value: 'set', label: 'Set' },
    ];

    return (
      <div className="PrepaidPlanDetails">
        <Form horizontal>

          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
              Title
              <Help contents={PlanDescription.description} />
            </Col>
            <Col sm={8} lg={9}>
              <Field value={item.get('description', '')} onChange={this.onChangeDescription} editable={editable} />
            </Col>
          </FormGroup>

          {['clone', 'create'].includes(mode) &&
            <FormGroup validationState={errors.name.length > 0 ? 'error' : null} >
              <Col componentClass={ControlLabel} sm={3} lg={2}>
                Key
                <Help contents={PlanDescription.name} />
              </Col>
              <Col sm={8} lg={9}>
                <Field value={item.get('name', '')} onChange={this.onChangeName} editable={editable} />
                { errors.name.length > 0 && <HelpBlock>{errors.name}</HelpBlock> }
              </Col>
            </FormGroup>
          }

          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>Operation</Col>
            <Col sm={4}>
              <Field
                fieldType="select"
                options={operationOptions}
                value={item.get('operation', '')}
                onChange={this.onChangeOperation}
               />
            </Col>
          </FormGroup>

          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>Charging value</Col>
            <Col sm={4}>
              <Field value={item.get('charging_value', 0)} fieldType="number" onChange={this.onChangeChargingValue} editable={editable} />
            </Col>
          </FormGroup>

        </Form>
      </div>
    );
  }
}
