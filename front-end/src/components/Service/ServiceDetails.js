import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { titleCase, sentenceCase } from 'change-case';
import isNumber from 'is-number';
import pluralize from 'pluralize';
import { Form, FormGroup, ControlLabel, HelpBlock, Col, InputGroup, DropdownButton, MenuItem } from 'react-bootstrap';
import { ServiceDescription } from '../../language/FieldDescriptions';
import Help from '../Help';
import Field from '@/components/Field';
import { RecurrenceFrequency } from '@/components/Elements';
import { EntityFields } from '../Entity';
import PlaysSelector from '../Plays/PlaysSelector';
import {
  getConfig,
  getFieldName,
  getFieldNameType,
} from '@/common/Util';


export default class ServiceDetails extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map).isRequired,
    sourceItem: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string.isRequired,
    updateItem: PropTypes.func.isRequired,
    onFieldRemove: PropTypes.func.isRequired,
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
    this.props.updateItem(['name'], value);
  }

  onChangePrice = (e) => {
    const { value } = e.target;
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.props.updateItem(['price', 0, 'price'], newValue);
  }

  onChangeCycle = (value) => {
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.props.updateItem(['price', 0, 'to'], newValue);
  }

  onChangeProrated = (e) => {
    const { value } = e.target;
    this.props.updateItem(['prorated'], value);
  }

  onChangeQuantitative = (e) => {
    const { value } = e.target;
    this.props.updateItem(['quantitative'], value);
  }

  onChangePlays = (plays, playWasRemoved = false) => {
    const playsToSave = plays === '' ? [] : plays.split(',');
    this.props.updateItem(['play'], Immutable.List(playsToSave));
    if (playWasRemoved) {
      this.props.updateItem(['rates'], Immutable.Map());
      this.props.updateItem(['include', 'groups'], Immutable.Map());
    }
  }

  onChangeDescription = (e) => {
    const { value } = e.target;
    this.props.updateItem(['description'], value);
  }

  onChangeAdditionalField = (field, value) => {
    this.props.updateItem(field, value);
  }

  onRemoveAdditionalField = (field) => {
    this.props.onFieldRemove(field);
  }

  onChangeServicePeriodType = (e) => {
    const { value } = e.target;
    this.props.updateItem(['balance_period', 'type'], value);
  }

  onSelectPeriodUnit = (unit) => {
    this.props.updateItem(['balance_period', 'unit'], unit);
  }

  onChangeBalancePeriod = (e) => {
    const { value } = e.target;
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.props.updateItem(['balance_period', 'value'], newValue);
  }

  render() {
    const { errors } = this.state;
    const { item, mode, sourceItem } = this.props;
    const serviceCycleUnlimitedValue = getConfig('serviceCycleUnlimitedValue', 'UNLIMITED');
    const editable = (mode !== 'view');
    const balancePeriodUnit = item.getIn(['balance_period', 'unit'], '');
    const balancePeriodUnitTitle = (balancePeriodUnit === '')
      ? 'Select unit...'
      : titleCase(pluralize(balancePeriodUnit, Number(item.getIn(['balance_period', 'value'], 2))));
    const isByCycles = item.getIn(['balance_period', 'type'], 'default') === 'default';
    return (
      <Form horizontal>

        <PlaysSelector
          entity={item}
          editable={editable && mode === 'create'}
          multi={true}
          onChange={this.onChangePlays}
        />

        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            { getFieldName('description', getFieldNameType('service'), sentenceCase('title'))}
            <span className="danger-red"> *</span>
            <Help contents={ServiceDescription.description} />
          </Col>
          <Col sm={8} lg={9}>
            <Field value={item.get('description', '')} onChange={this.onChangeDescription} editable={editable} />
          </Col>
        </FormGroup>

        {['clone', 'create'].includes(mode) &&
          <FormGroup validationState={errors.name.length > 0 ? 'error' : null} >
            <Col componentClass={ControlLabel} sm={3} lg={2}>
              { getFieldName('name', getFieldNameType('service'), sentenceCase('key'))}
              <span className="danger-red"> *</span>
              <Help contents={ServiceDescription.name} />
            </Col>
            <Col sm={8} lg={9}>
              <Field value={item.get('name', '')} onChange={this.onChangeName} editable={editable} />
              { errors.name.length > 0 && <HelpBlock>{errors.name}</HelpBlock> }
            </Col>
          </FormGroup>
        }

        <RecurrenceFrequency
          item={item}
          sourceItem={sourceItem}
          itemName="service"
          editable={editable}
          onChange={this.props.updateItem}
          onRemove={this.props.onFieldRemove}
        />

        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            { getFieldName('price', getFieldNameType('service'), sentenceCase('price'))}
            <span className="danger-red"> *</span>
          </Col>
          <Col sm={4}>
            <Field value={item.getIn(['price', 0, 'price'], '')} onChange={this.onChangePrice} fieldType="price" editable={editable} />
          </Col>
        </FormGroup>

        {['clone', 'create'].includes(mode) &&
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
              { getFieldName('period_type', getFieldNameType('service'), 'Eligibility Period')}
              <span className="danger-red"> *</span>
            </Col>
            <Col sm={8} lg={9}>
              <span className="inline mr10">
                <Field
                  fieldType="radio"
                  onChange={this.onChangeServicePeriodType}
                  name="service_period_type"
                  value="default"
                  label={
                    <span>
                      No. of Cycles
                      <Help contents={ServiceDescription.service_period_type_cycle} />
                    </span>
                  }
                  checked={isByCycles}
                />
              </span>
              <span className="inline">
                <Field
                  fieldType="radio"
                  onChange={this.onChangeServicePeriodType}
                  name="service_period_type"
                  value="custom_period"
                  label={
                    <span>
                      { getFieldName('custom_period_type', getFieldNameType('service'), 'Custom')}
                      <Help contents={ServiceDescription.service_period_type_custom_period} />
                    </span>
                  }
                  checked={!isByCycles}
                />
              </span>
            </Col>
          </FormGroup>
        }

        {(!isByCycles) &&
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2} >
              {!['clone', 'create'].includes(mode) && 'Custom Period'}
            </Col>
            <Col sm={4}>
              <InputGroup>
                <Field
                  disabled={isByCycles}
                  fieldType="number"
                  min="1"
                  step="1"
                  value={item.getIn(['balance_period', 'value'], '')}
                  onChange={this.onChangeBalancePeriod}
                  editable={editable}
                  suffix={!editable ? ` ${balancePeriodUnitTitle}` : null }
                />
                { editable && (
                  <DropdownButton
                    id="balance-period-unit"
                    componentClass={InputGroup.Button}
                    title={balancePeriodUnitTitle}
                    disabled={isByCycles}
                    >
                    <MenuItem eventKey="days" onSelect={this.onSelectPeriodUnit}>Days</MenuItem>
                    <MenuItem eventKey="weeks" onSelect={this.onSelectPeriodUnit}>Weeks</MenuItem>
                    <MenuItem eventKey="months" onSelect={this.onSelectPeriodUnit}>Months</MenuItem>
                    <MenuItem eventKey="years" onSelect={this.onSelectPeriodUnit}>Years</MenuItem>
                  </DropdownButton>
                )}
              </InputGroup>
            </Col>
          </FormGroup>
        }

        {(isByCycles) &&
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2} >
              {!['clone', 'create'].includes(mode) && 'No. of Cycles'}
            </Col>
            <Col sm={4}>
              <Field
                disabled={!isByCycles}
                value={item.getIn(['price', 0, 'to'], '')}
                onChange={this.onChangeCycle}
                fieldType="unlimited"
                unlimitedValue={serviceCycleUnlimitedValue}
                unlimitedLabel="Infinite"
                editable={editable}
              />
            </Col>
          </FormGroup>
        }

        {(['clone', 'create'].includes(mode) || (!['clone', 'create'].includes(mode) && isByCycles)) &&
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>Prorated?</Col>
            <Col sm={4} style={editable ? { padding: '10px 15px' } : { paddingTop: 5 }}>
              <Field
                fieldType="checkbox"
                value={!isByCycles ? false : item.get('prorated', '')}
                onChange={this.onChangeProrated}
                editable={editable}
                disabled={!isByCycles}
              />
            </Col>
          </FormGroup>
        }

        {(['clone', 'create'].includes(mode) || (!['clone', 'create'].includes(mode) && isByCycles)) &&
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>Quantitative?</Col>
            <Col sm={4} style={['clone', 'create'].includes(mode) ? { padding: '10px 15px' } : { paddingTop: 5 }}>
              <Field
                fieldType="checkbox"
                value={!isByCycles ? false : item.get('quantitative', '')}
                onChange={this.onChangeQuantitative}
                editable={['clone', 'create'].includes(mode)}
                disabled={!isByCycles}
              />
            </Col>
          </FormGroup>
        }
        <EntityFields
          entityName="services"
          entity={item}
          onChangeField={this.onChangeAdditionalField}
          onRemoveField={this.onRemoveAdditionalField}
          editable={editable}
        />

      </Form>
    );
  }
}
