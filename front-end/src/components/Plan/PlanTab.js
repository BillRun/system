import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import isNumber from 'is-number';
import { Form, FormGroup, ControlLabel, Col, Row, Panel, HelpBlock } from 'react-bootstrap';
import { PlanDescription } from '../../language/FieldDescriptions';
import Help from '../Help';
import Field from '@/components/Field';
import { CreateButton, RecurrenceFrequency } from '@/components/Elements';
import PlanPrice from './components/PlanPrice';
import { EntityFields } from '../Entity';
import PlaysSelector from '../Plays/PlaysSelector';
import {
  getConfig,
  getFieldName,
  getFieldNameType,
} from '@/common/Util';

export default class Plan extends Component {

  static propTypes = {
    plan: PropTypes.instanceOf(Immutable.Map).isRequired,
    originalPlan: PropTypes.instanceOf(Immutable.Map).isRequired,
    mode: PropTypes.string.isRequired,
    onChangeFieldValue: PropTypes.func.isRequired,
    onRemoveField: PropTypes.func.isRequired,
    onPlanCycleUpdate: PropTypes.func.isRequired,
    onPlanTariffAdd: PropTypes.func.isRequired,
    onPlanTariffRemove: PropTypes.func.isRequired,
    chargingModeOptions: PropTypes.array,
    errorMessages: PropTypes.object,
  }

  static defaultProps = {
    originalPlan: Immutable.Map(),
    chargingModeOptions: [
      { value: 'true', label: 'Upfront' },
      { value: 'false', label: 'Arrears' }
    ],
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

  componentWillMount() {
    const { plan } = this.props;
    const count = plan.get('price', Immutable.List()).size;
    if (count === 0) {
      this.props.onPlanTariffAdd();
    }
  }

  onPlanTrailTariffInit = (e) => {
    this.props.onPlanTariffAdd(true);
  }

  onPlanTariffInit = (e) => {
    this.props.onPlanTariffAdd();
  }

  onChangePlanName = (e) => {
    const { errorMessages: { name: { allowedCharacters } } } = this.props;
    const { errors } = this.state;
    const value = e.target.value.toUpperCase().replace(getConfig('keyUppercaseCleanRegex', /./), "_");
    const newError = (!getConfig('keyUppercaseRegex', /./).test(value)) ? allowedCharacters : '';
    this.setState({ errors: Object.assign({}, errors, { name: newError }) });
    this.props.onChangeFieldValue(['name'], value);
  }

  onChangeProrated = (e) => {
    const { value, id } = e.target;
    this.props.onChangeFieldValue([id], value);
  }

  onChangePlanDescription = (e) => {
    const { value } = e.target;
    this.props.onChangeFieldValue(['description'], value);
  }

  onChangePlays = (plays, playWasRemoved = false) => {
    const playsToSave = plays === '' ? [] : plays.split(',');
    this.props.onChangeFieldValue(['play'], Immutable.List(playsToSave));
    if (playWasRemoved) {
      this.props.onChangeFieldValue(['rates'], Immutable.Map());
      this.props.onChangeFieldValue(['include', 'groups'], Immutable.Map());
      this.props.onChangeFieldValue(['include', 'services'], Immutable.List());
    }
  }


  onPlanPriceUpdate = (index, value) => {
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.props.onChangeFieldValue(['price', index, 'price'], newValue);
  }

  onChangeUpfront = (value) => {
    if (value.toUpperCase() === 'TRUE') {
      value = true;
    } else if (value.toUpperCase() === 'FALSE') {
      value = false;
    }
    this.props.onChangeFieldValue(['upfront'], value);
  }

  onChangeAdditionalField = (field, value) => {
    this.props.onChangeFieldValue(field, value);
  }

  onRemoveAdditionalField = (field) => {
    this.props.onRemoveField(field);
  }

  getAddPriceButton = (trial = false) => {
    const onclick = trial ? this.onPlanTrailTariffInit : this.onPlanTariffInit;
    return (<CreateButton onClick={onclick} label="Add New" />);
  }

  getTrialPrice = () => {
    const { plan, mode } = this.props;
    const editable = (mode !== 'view');
    const trial = (plan.getIn(['price', 0, 'trial']) === true) ? plan.getIn(['price', 0]) : null;
    if (trial) {
      return (
        <PlanPrice
          index={0}
          count={plan.get('price', Immutable.List()).size}
          item={trial}
          mode={mode}
          isTrialExist={true}
          onPlanPriceUpdate={this.onPlanPriceUpdate}
          onPlanCycleUpdate={this.props.onPlanCycleUpdate}
          onPlanTariffAdd={this.props.onPlanTariffAdd}
          onPlanTariffRemove={this.props.onPlanTariffRemove}
        />);
    }
    return editable ? this.getAddPriceButton(true) : null;
  }

  getPrices = () => {
    const { plan, mode } = this.props;
    const isTrialExist = (plan.getIn(['price', 0, 'trial']) === true || plan.getIn(['price', 0, 'trial']) === 'true');
    const count = plan.get('price', Immutable.List()).size;
    const prices = [];

    plan.get('price', Immutable.List()).forEach((price, i) => {
      if (price.get('trial') !== true) {
        prices.push(
          <PlanPrice
            key={i}
            index={i}
            count={count}
            item={price}
            mode={mode}
            isTrialExist={isTrialExist}
            onPlanPriceUpdate={this.onPlanPriceUpdate}
            onPlanCycleUpdate={this.props.onPlanCycleUpdate}
            onPlanTariffRemove={this.props.onPlanTariffRemove}
          />
        );
      }
    });
    return prices;
  }

  getProrationLabel = () => {
    const { plan } = this.props;
    let label = [];
    if (plan.get('prorated_start', '') !== '') {
      label.push(getFieldName('prorated_start', 'plan', ''));
    }
    if (plan.get('prorated_end', '') !== '') {
      label.push(getFieldName('prorated_end', 'plan', ''));
    }
    if (plan.get('prorated_termination', '') !== '') {
      label.push(getFieldName('prorated_termination', 'plan', ''));
    }
    return label.join(', ');
  }
  render() {
    const { errors } = this.state;
    const { plan, mode, originalPlan, chargingModeOptions } = this.props;
    const upfront = typeof plan.get('upfront') !== 'boolean' ? '' : plan.get('upfront', '').toString();
    const editable = (mode !== 'view');

    return (
      <Row>
        <Col lg={12}>
          <Form horizontal>
            <Panel>

              <PlaysSelector
                entity={plan}
                editable={editable && mode === 'create'}
                multi={true}
                onChange={this.onChangePlays}
              />

              <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('description', getFieldNameType('service'), sentenceCase('title'))}
                  <span className="danger-red"> *</span>
                  <Help contents={PlanDescription.description} />
                </Col>
                <Col sm={8} lg={9}>
                  <Field value={plan.get('description', '')} onChange={this.onChangePlanDescription} editable={editable} />
                </Col>
              </FormGroup>

              {['clone', 'create'].includes(mode) &&
                <FormGroup validationState={errors.name.length > 0 ? 'error' : null} >
                  <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('name', getFieldNameType('service'), sentenceCase('key'))}
                    <span className="danger-red"> *</span>
                    <Help contents={PlanDescription.name} />
                  </Col>
                  <Col sm={8} lg={9}>
                    <Field id="PlanName" onChange={this.onChangePlanName} value={plan.get('name', '')} required={true} editable={editable} />
                    { errors.name.length > 0 && <HelpBlock>{errors.name}</HelpBlock> }
                  </Col>
                </FormGroup>
              }

              <RecurrenceFrequency
                item={plan}
                sourceItem={originalPlan}
                itemName="plan"
                    editable={editable}
                onChange={this.props.onChangeFieldValue}
                onRemove={this.props.onRemoveField}
                  />

              <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  Charging Mode
                  <span className="danger-red"> *</span>
                </Col>
                <Col sm={4}>
                  <Field
                    fieldType="select"
                    options={chargingModeOptions}
                    onChange={this.onChangeUpfront}
                    value={upfront}
                    editable={editable}
                  />
                </Col>
              </FormGroup>

              <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>Proration</Col>
                <Col sm={8} lg={9} className="pt5">
                  <Field
                    fieldType="checkbox"
                    value={plan.get('prorated_start', '')}
                    onChange={this.onChangeProrated}
                    className="mr10 inline"
                      label={getFieldName('prorated_start', 'plan', '')}
                    id="prorated_start"
                  />
                  <Field
                    fieldType="checkbox"
                    value={plan.get('prorated_end', '')}
                    onChange={this.onChangeProrated}
                    className="mr10 inline"
                      label={getFieldName('prorated_end', 'plan', '')}
                    id="prorated_end"
                  />
                  <Field
                    fieldType="checkbox"
                    value={plan.get('prorated_termination', '')}
                    onChange={this.onChangeProrated}
                    className="inline"
                      label={getFieldName('prorated_termination', 'plan', '')}
                    id="prorated_termination"
                  />
                </Col>
                {!editable && (
                  <Col sm={8} lg={9} className="non-editable-field">
                    {this.getProrationLabel()}
                  </Col>
                )}
              </FormGroup>

              <EntityFields
                entityName="plans"
                entity={plan}
                onChangeField={this.onChangeAdditionalField}
                onRemoveField={this.onRemoveAdditionalField}
                editable={editable}
              />

            </Panel>

            <Panel header={<h3>Trial Period</h3>}>
              { this.getTrialPrice() }
            </Panel>

            <Panel header={<h3>Recurring Charges</h3>}>
              { this.getPrices() }
              <br />
              { editable && this.getAddPriceButton(false) }
            </Panel>

          </Form>
        </Col>
      </Row>
    );
  }
}
