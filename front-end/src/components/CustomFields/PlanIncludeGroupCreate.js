import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Modal, Form, FormGroup, FormControl, ControlLabel, HelpBlock, Button, Checkbox, Col } from 'react-bootstrap';
import changeCase from 'change-case';
import isNumber from 'is-number';
import Stepper from '@material-ui/core/Stepper';
import Step from '@material-ui/core/Step';
import StepLabel from '@material-ui/core/StepLabel';
import getSymbolFromCurrency from 'currency-symbol-map';
import { GroupsInclude } from '../../../config/FieldDescriptions';
import CreateButton from '../../Elements/CreateButton';
import Help from '../../Help';
import ProductSearchByUsagetype from './ProductSearchByUsagetype';
import Field from '../../Field';
import { validateUnlimitedValue, validatePriceValue, validateKey } from '../../../common/Validators';
import {
  currencySelector,
  usageTypesDataSelector,
  propertyTypeSelector,
} from '../../../selectors/settingsSelector';
import UsageTypesSelector from '../../UsageTypes/UsageTypesSelector';
import { getUnitLabel, getUsagePropertyType } from '../../../common/Util';


class PlanIncludeGroupCreate extends Component {

  static propTypes = {
    existinGrousNames: PropTypes.instanceOf(Immutable.List),
    usedProducts: PropTypes.instanceOf(Immutable.List),
    modalTitle: PropTypes.string,
    addGroup: PropTypes.func.isRequired,
    currency: PropTypes.string,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    type: PropTypes.string,
    plays: PropTypes.string,
  }

  static defaultProps = {
    usedProducts: Immutable.List(),
    modalTitle: 'Create New Group',
    allGroupsProductsKeys: Immutable.List(),
    existinGrousNames: Immutable.List(),
    currency: 'USD',
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
    type: '',
    plays: '',
  };

  static defaultState = {
    name: '',
    usages: Immutable.List(['']),
    usagesPropertyType: '',
    unit: '',
    include: '',
    products: Immutable.List(),
    shared: false,
    pooled: false,
    quantityAffected: false,
    error: '',
    stepIndex: 0,
    open: false,
    monetaryBased: false,
    steps: Immutable.Map({
      SetNameAndType: { index: 0, label: 'Set Name & Type' },
      SetUsageType: { index: 1, label: 'Set Usage Type' },
      SetIncludes: { index: 2, label: 'Set Includes' },
      SetProducts: { index: 3, label: 'Set Products' },
    }),
  }

  state = Object.assign({}, PlanIncludeGroupCreate.defaultState);

  errors = {
    name: {
      required: 'Group name is required',
      exist: 'Group name already exists',
      allowedCharacters: 'Group name contains illegal characters, name should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
    },
    usage: {
      required: 'Usage type is required',
      exist: 'Group with same name and usage type already exists',
    },
    unit: {
      required: 'Unit is required',
    },
    include: {
      required: 'Include is required',
      allowedCharacters: 'Include must be positive number or Unlimited',
      allowedCharactersMonetary: 'Include must be positive number',
    },
    products: {
      required: 'Products is required',
      exist: 'Product(s) already exist in another group : ',
    },
  }

  validateStep = (step) => {
    const { steps } = this.state;
    switch (step) {
      case steps.get('SetNameAndType', { index: -1 }).index: {
        const { existinGrousNames } = this.props;
        const { name: nameErrors } = this.errors;
        const { name } = this.state;

        if (name === '') {
          this.setState({ error: nameErrors.required });
          return false;
        }
        if (existinGrousNames.includes(name)) {
          this.setState({ error: nameErrors.exist });
          return false;
        }
        if (!validateKey(name)) {
          this.setState({ error: nameErrors.allowedCharacters });
          return false;
        }
        return true;
      }

      case steps.get('SetUsageType', { index: -1 }).index: {
        const { usage: usageErrors, unit: unitErrors } = this.errors;
        const { usages, unit } = this.state;

        if (usages.includes('')) {
          this.setState({ error: usageErrors.required });
          return false;
        }
        if (unit === '') {
          this.setState({ error: unitErrors.required });
          return false;
        }
        return true;
      }

      case steps.get('SetIncludes', { index: -1 }).index: {
        const { include: includeErrors } = this.errors;
        const { include } = this.state;

        if (include === '') {
          this.setState({ error: includeErrors.required });
          return false;
        }
        if (!validateUnlimitedValue(include)) {
          this.setState({ error: includeErrors.allowedCharacters });
          return false;
        }
        return true;
      }

      case steps.get('SetProducts', { index: -1 }).index: {
        const { usedProducts } = this.props;
        const { products: productsErrors } = this.errors;
        const { products } = this.state;

        if (products.size < 1) {
          this.setState({ error: productsErrors.required });
          return false;
        }
        const duplicateProducts = products.filter(product => usedProducts.includes(product));
        if (duplicateProducts.size) {
          this.setState({ error: productsErrors.exist + duplicateProducts.join(', ') });
          return false;
        }
        return true;
      }

      default: return true;

    }
  }

  onChangeGroupName = (e) => {
    const name = e.target.value.toUpperCase();
    const error = (name.length && !validateKey(name)) ? this.errors.name.allowedCharacters : '';
    this.setState({ name, error });
  }

  onChangeShared = (e) => {
    const { checked: shared } = e.target;
    if (shared) {
      this.setState({ shared });
    } else {
      this.setState({ shared, pooled: false });
    }
  }

  onChangePooled = (e) => {
    const { checked: pooled } = e.target;
    if (pooled) {
      this.setState({ pooled });
    } else {
      this.setState({ pooled });
    }
  }

  onChangeQuantityAffected = (e) => {
    const { checked: quantityAffected } = e.target;
    this.setState({ quantityAffected });
  }

  onChangeUnit = (newValue) => {
    this.setState({ unit: newValue, error: '' });
  }

  onChangeInclud = (value) => {
    const error = !validateUnlimitedValue(value) ? this.errors.include.allowedCharacters : '';
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.setState({ include: newValue, error });
  }

  onChangeIncludeMonetaryBased = (e) => {
    const { value } = e.target;
    const error = !validatePriceValue(value) ? this.errors.include.allowedCharactersMonetary : '';
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.setState({ include: newValue, error });
  }

  onChangeGroupRates = (keys) => {
    this.setState({ products: Immutable.List(keys), error: '' });
  }

  handlePrev = () => {
    const { stepIndex } = this.state;
    if (stepIndex > 0) {
      this.setState({ stepIndex: stepIndex - 1, error: '' });
    }
  }

  handleNext = () => {
    const { stepIndex } = this.state;
    if (this.validateStep(stepIndex)) {
      this.setState({
        stepIndex: stepIndex + 1,
      });
    }
  };

  resetState = (open = true) => {
    this.setState(Object.assign({}, PlanIncludeGroupCreate.defaultState, { open }));
  }

  handleCancel = () => {
    this.resetState(false);
  }

  handleFinish = () => {
    const {
      stepIndex,
      name,
      usages,
      unit,
      include,
      products,
      shared,
      pooled,
      quantityAffected,
    } = this.state;
    if (this.validateStep(stepIndex)) {
      this.props.addGroup(name, usages, unit, include, shared, pooled, quantityAffected, products);
      this.resetState(false);
    }
  };

  handleReset = () => {
    this.resetState();
  }

  handleToggleBoby = () => {
    this.setState({ open: !this.state.open });
  }

  noMoreOptions = () => {
    const { usagesPropertyType, usages } = this.state;
    const { usageTypesData } = this.props;
    const relevantOptions = usageTypesData.filter(usagetype => usagesPropertyType === usagetype.get('property_type', ''));
    return usages.size >= relevantOptions.size;
  }

  onChangeBasedOn = (e) => {
    const { usageTypesData } = this.props;
    const { value } = e.target;
    const monetaryBased = (value === 'monetary');
    let usage = '';
    let steps;
    if (monetaryBased) {
      usage = 'cost';
      steps = Immutable.Map({
        SetNameAndType: { index: 0, label: 'Set Name & Type' },
        SetIncludes: { index: 1, label: 'Set Includes' },
        SetProducts: { index: 2, label: 'Set Products' },
      });
    } else {
      steps = Immutable.Map({
        SetNameAndType: { index: 0, label: 'Set Name & Type' },
        SetUsageType: { index: 1, label: 'Set Usage Type' },
        SetIncludes: { index: 2, label: 'Set Includes' },
        SetProducts: { index: 3, label: 'Set Products' },
      });
    }
    this.setState({ monetaryBased, steps, usages: Immutable.List([usage]), usagesPropertyType: getUsagePropertyType(usageTypesData, usage), unit: '', products: Immutable.List(), error: '' });
  }

  onClickAddUnitType = () => {
    const { usages } = this.state;
    this.setState({ usages: usages.push('') });
  }

  onRemoveUnitType = index => () => {
    const { usages } = this.state;
    this.setState({ usages: usages.delete(index) });
  }

  onChangeUnitType = index => (usaget) => {
    const { usageTypesData } = this.props;
    const { usages } = this.state;
    if (index === 0) {
      const usagesPropertyType = getUsagePropertyType(usageTypesData, usaget);
      this.setState({ usages: Immutable.List([usaget]), usagesPropertyType, unit: '', products: Immutable.List(), error: '' });
    } else {
      this.setState({ usages: usages.set(index, usaget), error: '' });
    }
  }

  filterUsageTypes = index => (usaget) => {
    const { usages, usagesPropertyType } = this.state;
    return [-1, index].includes(usages.findIndex(usage => usage === usaget.get('usage_type', ''))) &&
      (usagesPropertyType === '' || usaget.get('property_type', '') === usagesPropertyType);
  }

  getUsagesLabel = () => {
    const { usages } = this.state;
    return usages.map(usage => changeCase.sentenceCase(usage)).join(', ');
  }

  getStepContent = (stepIndex) => {
    const { usedProducts, currency, propertyTypes, usageTypesData, type, plays } = this.props;
    const {
      name,
      products,
      include,
      usages,
      unit,
      shared,
      pooled,
      quantityAffected,
      error,
      monetaryBased,
      steps,
    } = this.state;
    const existingProductsKeys = usedProducts.push(...products);
    const setIncludesTitle = monetaryBased
      ? `Total ${getSymbolFromCurrency(currency)} included`
      : `${getUnitLabel(propertyTypes, usageTypesData, usages.get(0, ''), unit)} includes (${this.getUsagesLabel()})`;

    switch (stepIndex) {

      case steps.get('SetNameAndType', { index: -1 }).index:
        return ([
          <FormGroup key="group_name" validationState={error.length > 0 ? 'error' : null} className="mb10">
            <Col componentClass={ControlLabel} sm={3}>
              Name<Help contents={GroupsInclude.name} />
            </Col>
            <Col sm={8}>
              <FormControl type="text" placeholder="Enter Group Name.." value={name} onChange={this.onChangeGroupName} />
              { error.length > 0 && <HelpBlock>{error}</HelpBlock>}
            </Col>
          </FormGroup>,
          <FormGroup key="group_type" validationState={error.length > 0 ? 'error' : null} style={{ marginBottom: 8 }}>
            <Col smOffset={3} sm={8}>
              <Field
                fieldType="radio"
                name="based-on"
                id="usage-based"
                value="usage"
                onChange={this.onChangeBasedOn}
                checked={!monetaryBased}
                label="&nbsp;Usage based"
              />
              <Field
                fieldType="radio"
                name="based-on"
                id="monetary-based"
                value="monetary"
                checked={monetaryBased}
                onChange={this.onChangeBasedOn}
                label="&nbsp;Monetary based"
              />
            </Col>
          </FormGroup>,
        ]);

      case steps.get('SetUsageType', { index: -1 }).index:
        return (
          <div>
            { usages.map((usaget, index) => (
                <FormGroup validationState={error.length > 0 ? 'error' : null} key={`usages_${usaget}_${index}`}>
                  <Col componentClass={ControlLabel} sm={3}>{index === 0 ? 'Unit Type/s' : ''}</Col>
                  <Col sm={8}>
                    <UsageTypesSelector
                      usaget={usaget}
                      unit={unit}
                      onChangeUsaget={this.onChangeUnitType(index)}
                      onChangeUnit={this.onChangeUnit}
                      showUnits={index === 0}
                      showAddButton={index === 0}
                      usagetFilter={this.filterUsageTypes(index)}
                    />
                  </Col>
                  { index !== 0 && (
                    <Col sm={1} className="pr0 pl0">
                      <Button onClick={this.onRemoveUnitType(index)} bsStyle="link" className="pl0">
                        <i className="fa fa-fw danger-red fa-trash-o" />
                      </Button>
                    </Col>
                  )}
                </FormGroup>
              ))
            }
            <FormGroup>
              <Col sm={3} />
              <Col sm={8}>
                <CreateButton onClick={this.onClickAddUnitType} label="Additional" disabled={usages.get(0, '') === '' || this.noMoreOptions()} />
              </Col>
            </FormGroup>
          </div>
        );

      case steps.get('SetIncludes', { index: -1 }).index:
        return ([
          <FormGroup validationState={error.length > 0 ? 'error' : null} key={`${usages.get(0, '')}_includes`} className="mb10">
            <Col componentClass={ControlLabel} sm={4}>
              {setIncludesTitle}
            </Col>
            <Col sm={7}>
              {monetaryBased
                ? <Field onChange={this.onChangeIncludeMonetaryBased} value={include} fieldType="text" />
                : <Field onChange={this.onChangeInclud} value={include} fieldType="unlimited" />
              }
              { error.length > 0 && <HelpBlock>{error}</HelpBlock> }
            </Col>
          </FormGroup>,
          <FormGroup key={`${usages.get(0, '')}_shared`} className="mb10">
            <Col smOffset={3} sm={8}>
              <Checkbox checked={shared} onChange={this.onChangeShared}>
                {"Share with all account's subscribers"}
                <Help contents={GroupsInclude.shared_desc} />
              </Checkbox>
            </Col>
            <Col smOffset={3} sm={8}>
              <Checkbox disabled={!shared} checked={pooled} onChange={this.onChangePooled}>
                {'Includes is pooled?'}
                <Help contents={GroupsInclude.pooled_desc} />
              </Checkbox>
            </Col>
            {type === 'service' &&
              <Col smOffset={3} sm={8}>
                <Checkbox checked={quantityAffected} onChange={this.onChangeQuantityAffected}>
                  {'Multiply by Service Quantity'}
                  <Help contents={GroupsInclude.quantityAffected_desc} />
                </Checkbox>
              </Col>
            }
          </FormGroup>,
        ]);

      case steps.get('SetProducts', { index: -1 }).index:
        return (
          <FormGroup validationState={error.length > 0 ? 'error' : null}>
            {monetaryBased
              ? <Col componentClass={ControlLabel} sm={3}>Products</Col>
              : <Col componentClass={ControlLabel} sm={3} style={{ paddingTop: 2 }}>
                Products of type/s <br />{this.getUsagesLabel()}
                <Help contents={GroupsInclude.products} />
              </Col>
            }
            <Col sm={8}>
              <div style={{ width: '100%' }}>
                <ProductSearchByUsagetype
                  products={products}
                  usages={usages}
                  onChangeGroupRates={this.onChangeGroupRates}
                  existingProducts={existingProductsKeys}
                  plays={plays}
                />
              </div>
              { error.length > 0 && <HelpBlock>{error}</HelpBlock>}
            </Col>
          </FormGroup>
        );

      default:
        return '...';
    }
  }

  renderSteps = () => {
    const { steps } = this.state;
    return steps
      .map(step => (
        <Step key={step.index}>
          <StepLabel>{step.label}</StepLabel>
        </Step>
      ))
      .toList()
      .toArray();
  }

  render() {
    const { stepIndex, open, name, steps } = this.state;
    let { modalTitle } = this.props;
    if (name.length) {
      modalTitle += ` - ${name}`;
    }
    const styleStepper = { height: 20 };

    return (
      <div>
        <CreateButton onClick={this.handleToggleBoby} type="Group" label="Create New" />
        <Modal show={open} keyboard={false}>

          <Modal.Header closeButton onHide={this.handleCancel}>
            <Modal.Title>
              {modalTitle}
            </Modal.Title>
          </Modal.Header>

          <Modal.Body style={{ minHeight: 235 }}>
            <Stepper activeStep={stepIndex} style={styleStepper}>
              {this.renderSteps()}
            </Stepper>
            <hr style={{ margin: '20px -20px 35px -20px' }} />
            <Form horizontal className="mb0">
              {this.getStepContent(stepIndex)}
            </Form>
          </Modal.Body>

          <Modal.Footer>
            <Button bsSize="small" onClick={this.handlePrev} style={{ marginRight: 9, minWidth: 90 }}><i className="fa fa-angle-left" />&nbsp;Back</Button>
            { (stepIndex === steps.count() - 1)
              ? <Button bsSize="small" onClick={this.handleFinish} style={{ minWidth: 90 }} bsStyle="primary">Add</Button>
              : <Button bsSize="small" onClick={this.handleNext} style={{ minWidth: 90 }}>Next&nbsp;<i className="fa fa-angle-right" /></Button>
            }
          </Modal.Footer>
        </Modal>
      </div>
    );
  }
}

const mapStateToProps = (state, props) => {
  const currency = currencySelector(state, props);
  return {
    currency,
    usageTypesData: usageTypesDataSelector(state, props),
    propertyTypes: propertyTypeSelector(state, props),
  };
};

export default connect(mapStateToProps)(PlanIncludeGroupCreate);
