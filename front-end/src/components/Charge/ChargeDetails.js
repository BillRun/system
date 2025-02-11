import React, { Component } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, ControlLabel, Col, Row, Panel, HelpBlock } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import { titleCase, paramCase } from 'change-case';
import isNumber from 'is-number';
import DiscountServiceValue from '@/components/Discount/Elements/DiscountServiceValue';
import DiscountConditions from '@/components/Discount/Elements/DiscountConditions';
import DiscountPlanValue from '@/components/Discount/Elements/DiscountPlanValue';
import Field from '@/components/Field';
import { EntityFields, EntityField } from '@/components/Entity';
import { getFieldName, getConfig } from '@/common/Util';
import { entitiesOptionsSelector } from '@/selectors/listSelectors';
import { getSettings } from '@/actions/settingsActions';
import { getEntitiesOptions } from '@/actions/listActions';
import { formModalErrosSelector } from '@/selectors/guiSelectors';
import { setFormModalError } from '@/actions/guiStateActions/pageActions';


class ChargeDetails extends Component {

  static propTypes = {
    charge: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string.isRequired,
    errors: PropTypes.instanceOf(Immutable.Map),
    currency: PropTypes.string,
    errorMessages: PropTypes.object,
    onFieldUpdate: PropTypes.func.isRequired,
    onFieldRemove: PropTypes.func.isRequired,
    availableEntities: PropTypes.instanceOf(Immutable.Map),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    charge: Immutable.Map(),
    errors: Immutable.Map(),
    fields: Immutable.Map({
      description: Immutable.Map({
        title: getFieldName('description', 'charge'),
        field_name: 'description',
        mandatory: true,
      }),
      key: Immutable.Map({
        title: getFieldName('key', 'charge'),
        field_name: 'key',
        mandatory: true,
      }),
      priority: Immutable.Map({
        title: getFieldName('priority', 'charge'),
        field_name: 'priority',
        system: true,
        type: 'number'
      }),
      paramsMinSubscribers: Immutable.Map({
        title: getFieldName('min_subscribers', 'charge'),
        field_name: 'params.min_subscribers',
        system: true,
        type: 'number'
      }),
      paramsMaxSubscribers: Immutable.Map({
        title: getFieldName('max_subscribers', 'charge'),
        field_name: 'params.max_subscribers',
        system: true,
        type: 'number'
      }),
      proration: Immutable.Map({
        field_name: 'proration',
        title:  getFieldName('proration', 'charge'),
        select_list: true,
        select_options: [
          Immutable.Map({value: 'inherited', label: getFieldName('proration_inherited', 'charge')}),
          Immutable.Map({value: 'no', label: getFieldName('proration_no', 'charge')})
        ],
        default_value: 'inherited',
      }),
    }),
    currency: '',
    availableEntities: Immutable.Map(),
    errorMessages: {
      name: {
        allowedCharacters: 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
      },
    },
  };

  static requiredEntityLists = ['discount', 'plan', 'service'];

  state = {
    errors: {
      name: '',
    },
  }

  componentWillMount() {
    const { availableEntities } = this.props;
    this.props.dispatch(getSettings([
      'subscribers.subscriber',
      'subscribers.account',
    ]));
    const listsToGet = ChargeDetails.requiredEntityLists
      .filter(entity => availableEntities.get(entity, Immutable.List()).isEmpty());
    this.props.dispatch(getEntitiesOptions(listsToGet));
  }

  onChangeFiled = (path, value) => {
    const pathString = path.join('.');
    switch (pathString) {
      case 'params.cycles':
        if (value !== '') {
          this.props.onFieldUpdate(path, value);
        } else {
          this.props.onFieldRemove(path);
        }
      break;
      case 'type':
        // if (value === 'percentage') {
        //   this.props.onFieldRemove(['subject.service']);
        //   this.props.onFieldRemove(['subject.plan']);
        //   this.props.onFieldRemove(['subject.monthly_fees']);
        //   this.props.onFieldRemove(['subject.matched_plans']);
        //   this.props.onFieldRemove(['subject.matched_services']);
        // } else if (value === 'monetary') {
        //   this.props.onFieldRemove(['subject.general']);
        // }
        this.props.onFieldUpdate(path, value);
      break;
      case 'subject.general.value':
        if (value !== null) {
          this.props.onFieldUpdate(path, value);
        } else {
          this.props.onFieldRemove(path);
        }
      break;
      case 'key':
        const valueKey = value.toUpperCase().replace(getConfig('keyUppercaseCleanRegex', /./), "_");
        if (value !== '') {
          const { errorMessages: { name: { allowedCharacters } } } = this.props;
          const { errors } = this.state;
          const newError = (!getConfig('keyUppercaseRegex', /./).test(valueKey)) ? allowedCharacters : '';
          this.setState({ errors: Object.assign({}, errors, { name: newError }) });
        }
        this.props.onFieldUpdate(path, valueKey);
        break;
      default: this.props.onFieldUpdate(path, value);
    }
  }

  onChangeCycles = (value) => {
    this.props.dispatch(setFormModalError('params.cycles'));
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.onChangeFiled(['params', 'cycles'], newValue);
  }

  onChangeSubjectGeneral = (value) => {
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.onChangeFiled(['subject', 'general', 'value'], newValue);
  }

  onChangeDiscountType = (e) => {
    const { value } = e.target;
    this.onChangeFiled(['type'], value);
  }

  onChangeAdditionalField = (field, value) => {
    this.onChangeFiled(field, value);
  }

  onChangeExcludes = (excludes) => {
    const newValuesArray = Immutable.List((excludes.length) ? excludes.split(',') : []);
    if (newValuesArray.isEmpty()) {
      this.props.onFieldRemove(['excludes']);
    } else {
      this.onChangeFiled(['excludes'], newValuesArray);
    }
  }

  onChangeService = (services) => {
    const { charge } = this.props;
    const newValuesArray = Immutable.List((services.length) ? services.split(',') : []);
    const defaultNewValue = Immutable.Map({ value: '' });
    if (newValuesArray.isEmpty()) {
      this.props.onFieldRemove(['subject', 'service']);
      this.props.onFieldRemove(['subject', 'matched_services']);
    } else {
      const existsServices = charge.getIn(['subject', 'service'], Immutable.Map());
      const updatedServicesList = Immutable.Map().withMutations((plansWithMutations) => {
        newValuesArray.forEach((newServiceName) => {
          if (newServiceName !== 'matched_services') {
            plansWithMutations.set(newServiceName, existsServices.get(newServiceName, defaultNewValue));
          }
        });
      });
      if (!updatedServicesList.isEmpty()) {
        this.onChangeFiled(['subject', 'service'], updatedServicesList);
      } else {
        this.props.onFieldRemove(['subject', 'service']);
      }
      if (newValuesArray.includes('matched_services')) {
        this.onChangeFiled(['subject', 'matched_services'], charge.getIn(['subject', 'matched_services'], defaultNewValue));
      } else {
        this.props.onFieldRemove(['subject', 'matched_services']);
      }
    }
  }

  onChangePlan = (plans) => {
    const { charge } = this.props;
    const newValuesArray = Immutable.List((plans.length) ? plans.split(',') : []);
    const defaultNewValue = Immutable.Map({ value: '' });
    if (newValuesArray.isEmpty()) {
      this.props.onFieldRemove(['subject', 'plan']);
      this.props.onFieldRemove(['subject', 'matched_plans']);
      this.props.onFieldRemove(['subject', 'monthly_fees']);
    } else {
      const existsPlans = this.getSelectedPlans();
      const updatedPalsList = Immutable.Map().withMutations((plansWithMutations) => {
        newValuesArray.forEach((planFromList) => {
          if (!['matched_plans', 'monthly_fees'].includes(planFromList)) {
            plansWithMutations.set(planFromList, existsPlans.get(planFromList, defaultNewValue));
          }
        });
      });
      if (!updatedPalsList.isEmpty()) {
        this.onChangeFiled(['subject', 'plan'], updatedPalsList);
      } else {
        this.props.onFieldRemove(['subject', 'plan']);
      }
      if (newValuesArray.includes('matched_plans')) {
        this.onChangeFiled(['subject', 'matched_plans'], charge.getIn(['subject', 'matched_plans'], defaultNewValue));
      } else {
        this.props.onFieldRemove(['subject', 'matched_plans']);
      }
      if (newValuesArray.includes('monthly_fees')) {
        this.onChangeFiled(['subject', 'monthly_fees'], charge.getIn(['subject', 'monthly_fees'], defaultNewValue));
      } else {
        this.props.onFieldRemove(['subject', 'monthly_fees']);
      }
    }
  }


  onChangePlanDiscount = (planName, newSubject) => {
    const path = ['matched_plans', 'monthly_fees'].includes(planName)
      ? ['subject', planName]
      : ['subject', 'plan', planName];
    this.onChangeFiled(path, newSubject);
  }

  onChangeServiceDiscountValue = (serviceKey, newSubject) => {
    if ([serviceKey].includes('matched_services')) {
      this.onChangeFiled(['subject', serviceKey], newSubject);
    } else {
      this.onChangeFiled(['subject', 'service', serviceKey], newSubject);
    }
  }

  onChangeConditionField = (path, index, value) => {
    this.onChangeFiled([...path, index, 'field'], value);
  }

  onChangeConditionOp = (path, index, value) => {
    this.onChangeFiled([...path, index, 'op'], value);
  }

  onChangeConditionValue = (path, index, value) => {
    this.onChangeFiled([...path, index, 'value'], value);
  }

  onAddCondition = (path, condition) => {
    const { charge } = this.props;
    const conditions = charge.getIn(path, Immutable.List());
    this.onChangeFiled(path, conditions.push(condition));
  }

  onRemoveAdditionalField = (field) => {
    this.props.onFieldRemove(field);
  }

  onRemoveCondition = (path, index) => {
    const { charge } = this.props;
    const conditions = charge.getIn(path, Immutable.List());
    this.onChangeFiled(path, conditions.delete(index));
  }

  createPlansOptions = () => this.props.availableEntities
    .get('plan', Immutable.List())
    .push(Immutable.Map({name: 'matched_plans', description: getFieldName('matched_plans', 'charge')}))
    .push(Immutable.Map({name: 'monthly_fees', description: getFieldName('monthly_fees', 'charge')}))
    .map(this.createOption)
    .toArray();

  createExcludeDiscountOptions = () => {
    const { charge, availableEntities } = this.props;
    return availableEntities
     .get('charge', Immutable.List())
     .filter(option => option.get('key', '') !== charge.get('key', ''))
     .map(this.createOption)
     .toArray();
  }

  createServicesOptions = () => {
    const { availableEntities } = this.props;
    return availableEntities
      .get('service', Immutable.List())
      .push(Immutable.Map({
        name: 'matched_services',
        description: getFieldName('matched_services', 'charge')
      }))
      .map(this.createOption)
      .toArray();
  }

  createOption = item => ({
    value: item.get('name', item.get('key', '')),
    label: item.get('description', ''),
  })

  getLabel = (items, key) => {
    if (['matched_services', 'matched_plans', 'monthly_fees'].includes(key)) {
      return getFieldName(key, 'charge');
    }
    return items
      .find(item => item.get('name') === key, null, Immutable.Map())
      .get('description', key);
  }

  getSelectedServices = () => {
    const { charge } = this.props;
    const defaultNewValue = Immutable.Map({ value: '' });
    let services = charge.getIn(['subject', 'service'], Immutable.Map());
    if (charge.hasIn(['subject', 'matched_services'])) {
      services = services.set('matched_services', charge.getIn(['subject', 'matched_services'], defaultNewValue));
    }
    return services;
  }

  getSelectedPlans = () => {
    const { charge } = this.props;
    const defaultNewValue = Immutable.Map({ value: '' });
    let plans = charge.getIn(['subject', 'plan'], Immutable.Map());
    if (charge.hasIn(['subject', 'matched_plans'])) {
      plans = plans.set('matched_plans', charge.getIn(['subject', 'matched_plans'], defaultNewValue));
    }
    if (charge.hasIn(['subject', 'monthly_fees'])) {
      plans = plans.set('monthly_fees', charge.getIn(['subject', 'monthly_fees'], defaultNewValue));
    }
    return plans;
  }

  isServiceQuantitative = (name) => {
    const { availableEntities } = this.props;
    return availableEntities
      .get('service', Immutable.List())
      .findIndex(service => (
        service.get('name', '') === name && service.get('quantitative', false) === true
      )) !== -1;
  }

  isPercentage = () => {
    const { charge } = this.props;
    return charge.get('type', '') === 'percentage'
  }

  renderServivesDiscountValues = () => {
    const { availableEntities, mode, currency } = this.props;
    const discountSubject = this.getSelectedServices();
    if (discountSubject.isEmpty()) {
      return null;
    }
    const isPercentage = this.isPercentage();
    return discountSubject.map((service, serviceName) => {
      const label = this.getLabel(availableEntities.get('service', Immutable.List()), serviceName);
      const isQuantitative = this.isServiceQuantitative(serviceName);
      return (
        <DiscountServiceValue
          key={`${paramCase(serviceName)}-charge-value`}
          mode={mode}
          service={service}
          name={serviceName}
          label={label}
          isQuantitative={isQuantitative}
          isPercentage={isPercentage}
          currency={currency}
          onChange={this.onChangeServiceDiscountValue}
        />
      );
    })
    .toList()
    .toArray();
  }

  renderPlanDiscountValue = () => {
    const { availableEntities, mode, currency } = this.props;
    const plans = this.getSelectedPlans();
    if (plans.isEmpty()) {
      return null;
    }
    const editable = (mode !== 'view');
    const isPercentage = this.isPercentage();
    return plans
      .filter(value => (
        editable || (!editable && Immutable.Map.isMap(value) && value.get('value', '') !== '')
      ))
      .map((value, planName) => (
        <DiscountPlanValue
          key={`${paramCase(planName)}-charge-value`}
          name={planName}
          label={this.getLabel(availableEntities.get('plan', Immutable.List()), planName)}
          plan={value}
          isPercentage={isPercentage}
          mode={mode}
          currency={currency}
          onChange={this.onChangePlanDiscount}
        />
      ))
      .toList()
      .toArray()
  }

  render() {
    const { errors:onChangeErrors } = this.state;
    const { charge, mode, currency, fields, errors } = this.props;
    const editable = (mode !== 'view');
    const isPercentage = this.isPercentage();
    const plansOptions = this.createPlansOptions();
    const servicesOptions = this.createServicesOptions();
    const excludeDiscounts = charge.get('excludes', Immutable.List()).join(',');
    const excludeDiscountsOptions = this.createExcludeDiscountOptions();
    const services = this.getSelectedServices().keySeq().toList().join(',');
    const plans = this.getSelectedPlans().keySeq().toList().join(',');
    const suffix = isPercentage ? undefined : getSymbolFromCurrency(currency);
    return (
      <Row>
        <Col lg={12}>
          <Form horizontal>
            <Panel>
              <EntityField
                field={fields.get('description')}
                entity={charge}
                onChange={this.onChangeFiled}
                editable={editable}
                error={errors.get('description', onChangeErrors.description)}
              />
              { ['clone', 'create'].includes(mode) &&
                <EntityField
                  field={fields.get('key')}
                  entity={charge}
                  onChange={this.onChangeFiled}
                  editable={editable}
                  disabled={!['clone', 'create'].includes(mode)}
                  error={errors.get('key', onChangeErrors.name)}
                />
              }
              <FormGroup >
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('type', 'charge')}
                </Col>
                <Col sm={8} lg={9}>
                  { editable
                    ? (
                      <span>
                        <span style={{ display: 'inline-block', marginRight: 20 }}>
                          <Field fieldType="radio" onChange={this.onChangeDiscountType} name="type" value="monetary" label={getFieldName('type_monetary', 'charge')} checked={!isPercentage} />
                        </span>
                        <span style={{ display: 'inline-block' }}>
                          <Field fieldType="radio" onChange={this.onChangeDiscountType} name="type" value="percentage" label={getFieldName('type_percentage', 'charge')} checked={isPercentage} />
                        </span>
                      </span>
                    )
                  : <div className="non-editable-field">{ titleCase(charge.get('type', '')) }</div>
                  }
                </Col>
              </FormGroup>
              <FormGroup validationState={errors.has('params.cycles') ? 'error' : null}>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('cycles', 'charge')}
                </Col>
                <Col sm={8} lg={9}>
                  <Field value={charge.getIn(['params', 'cycles'], '')} onChange={this.onChangeCycles} fieldType="unlimited" unlimitedValue="" unlimitedLabel="Infinite" editable={editable} />
                  { errors.has('params.cycles') && (<HelpBlock><small>{errors.get('params.cycles', '')}</small></HelpBlock>)}
                </Col>
              </FormGroup>
              <EntityField
                field={fields.get('proration')}
                entity={charge}
                onChange={this.onChangeFiled}
                editable={editable}
                error={errors.get('proration', onChangeErrors.proration)}
              />
              <EntityField
                field={fields.get('priority')}
                entity={charge}
                onChange={this.onChangeFiled}
                editable={editable}
                error={errors.get('priority', onChangeErrors.priority)}
              />
              <EntityField
                field={fields.get('paramsMinSubscribers')}
                entity={charge}
                onChange={this.onChangeFiled}
                editable={editable}
                error={errors.get('params.min_subscribers', onChangeErrors.paramsMinSubscribers)}
              />
              <EntityField
                field={fields.get('paramsMaxSubscribers')}
                entity={charge}
                onChange={this.onChangeFiled}
                editable={editable}
                error={errors.get('params.max_subscribers', onChangeErrors.paramsMaxSubscribers)}
              />
              <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  Excludes
                </Col>
                <Col sm={8} lg={9}>
                  <Field
                    fieldType="select"
                    multi={true}
                    value={excludeDiscounts}
                    options={excludeDiscountsOptions}
                    onChange={this.onChangeExcludes}
                    editable={editable}
                  />
                </Col>
              </FormGroup>

              <EntityFields
                entityName="charges"
                entity={charge}
                onChangeField={this.onChangeAdditionalField}
                onRemoveField={this.onRemoveAdditionalField}
                editable={editable}
                errors={errors}
              />
            </Panel>

            <DiscountConditions
              discount={charge}
              editable={editable}
              onChangeConditionField={this.onChangeConditionField}
              onChangeConditionOp={this.onChangeConditionOp}
              onChangeConditionValue={this.onChangeConditionValue}
              addCondition={this.onAddCondition}
              removeCondition={this.onRemoveCondition}
              errors={errors}
            />
            { isPercentage && (
              <Panel header={<h3>{getFieldName('panel_plan_discount', 'charge')}</h3>}>
                <FormGroup>
                  <Col componentClass={ControlLabel} sm={3} lg={2}>
                    {getFieldName('select_plans', 'charge')}
                  </Col>
                  <Col sm={8} lg={9}>
                    <Field
                      fieldType="select"
                      multi={true}
                      value={plans}
                      options={plansOptions}
                      onChange={this.onChangePlan}
                      editable={editable}
                    />
                  </Col>
                </FormGroup>
                { (!this.getSelectedPlans().isEmpty()) && <hr /> }
                { this.renderPlanDiscountValue() }
                </Panel>
              )}
              { isPercentage && (
                <Panel header={<h3>{getFieldName('panel_service_discount', 'charge')}</h3>}>
                <FormGroup>
                  <Col componentClass={ControlLabel} sm={3} lg={2}>
                    {getFieldName('select_services', 'charge')}
                  </Col>
                  <Col sm={8} lg={9}>
                    <Field
                      fieldType="select"
                      multi={true}
                      value={services}
                      options={servicesOptions}
                      onChange={this.onChangeService}
                      editable={editable}
                    />
                  </Col>
                </FormGroup>
                { (!this.getSelectedServices().isEmpty()) && <hr /> }
                { this.renderServivesDiscountValues() }
              </Panel>
            )}
            { !isPercentage && (
              <Panel header={<h3>{getFieldName('charge_values', 'charge')}</h3>}>
                <FormGroup>
                  <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('subject_general', 'charge')}
                  </Col>
                  <Col sm={8} lg={9}>
                    <Field
                      fieldType="toggeledInput"
                      value={charge.getIn(['subject', 'general', 'value'], null)}
                      onChange={this.onChangeSubjectGeneral}
                      label="Charge by"
                      editable={editable}
                      suffix={suffix}
                      inputProps={{ fieldType: isPercentage ? 'percentage' : 'number' }}
                    />
                  </Col>
                </FormGroup>
              </Panel>
            )}
          </Form>
        </Col>
      </Row>
    );
  }
}


const mapStateToProps = (state, props) => {
  const parentErrors = typeof props.errors !== 'undefined' ? props.errors : Immutable.Map();
  const reduxErrors = formModalErrosSelector(state) || Immutable.Map();
  return ({
    availableEntities: entitiesOptionsSelector(state, props, ['charge', 'plan', 'service']),
    errors: Immutable.merge(parentErrors, reduxErrors),
})};

export default connect(mapStateToProps)(ChargeDetails);
