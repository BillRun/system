import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Modal, Form, Button, Col } from 'react-bootstrap';
import { Checkbox, ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import isNumber from 'is-number';
import getSymbolFromCurrency from 'currency-symbol-map';
import { GroupsInclude } from '@/language/FieldDescriptions';
import { CreateButton, Stepper } from '@/components/Elements';
import Help from '../../Help';
import Field from '@/components/Field';
import ProductsGroupSelect from './ProductsGroupSelect';
import { validateUnlimitedValue, validatePriceValue } from '@/common/Validators';
import { currencySelector, propertyTypeSelector } from '@/selectors/settingsSelector';
import { getUomByPropertyType, getUnitLabelByPropertyType } from '@/common/Util';


class ServiceProductsGroupCreate extends Component {

  static propTypes = {
    availableProductsGroups: PropTypes.instanceOf(Immutable.List),
    usedKeys: PropTypes.instanceOf(Immutable.List),
    addGroup: PropTypes.func.isRequired,
    onGroupCreated: PropTypes.func,
    currency: PropTypes.string,
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    plays: PropTypes.string,
    existingGroupsNames: PropTypes.instanceOf(Immutable.List),
  }

  static defaultProps = {
    availableProductsGroups: Immutable.List(),
    usedKeys: Immutable.List(),
    onGroupCreated: () => {},
    currency: 'USD',
    propertyTypes: Immutable.List(),
    plays: '',
    existingGroupsNames: Immutable.List(),
  };

  static defaultState = {
    monetaryBased: false,
    groupKey: '',
    include: '',
    unit: '',
    shared: false,
    pooled: false,
    quantityAffected: false,
    error: '',
    stepIndex: 0,
    open: false,
  }

  static steps = Immutable.Map({
    SetType: { index: 0, label: 'Set Type' },
    SelectGroup: { index: 1, label: 'Select Products Group' },
    SetIncludes: { index: 2, label: 'Set Includes' },
  });

  state = Object.assign({}, ServiceProductsGroupCreate.defaultState);

  errors = {
    group: {
      required: 'Products group is required',
    },
    include: {
      required: 'Include is required',
      allowedCharacters: 'Include must be positive number or Unlimited',
      allowedCharactersMonetary: 'Include must be positive number',
    },
    unit: {
      required: 'Unit is required',
    },
  }

  getSelectedGroup = () => {
    const { availableProductsGroups } = this.props;
    const { groupKey } = this.state;
    return availableProductsGroups.find(productsGroup => productsGroup.get('key', '') === groupKey) || Immutable.Map();
  }

  getSelectedGroupPropertyType = () => this.getSelectedGroup().get('property_type', '') || '';

  validateStep = (step) => {
    const steps = ServiceProductsGroupCreate.steps;
    switch (step) {

      case steps.get('SelectGroup', { index: -1 }).index: {
        const { groupKey } = this.state;
        if (groupKey === '') {
          this.setState({ error: this.errors.group.required });
          return false;
        }
        return true;
      }

      case steps.get('SetIncludes', { index: -1 }).index: {
        const { monetaryBased, include, unit } = this.state;
        if (include === '') {
          this.setState({ error: this.errors.include.required });
          return false;
        }
        if (monetaryBased && !validatePriceValue(include)) {
          this.setState({ error: this.errors.include.allowedCharactersMonetary });
          return false;
        }
        if (!monetaryBased && !validateUnlimitedValue(include)) {
          this.setState({ error: this.errors.include.allowedCharacters });
          return false;
        }
        if (!monetaryBased && unit === '') {
          this.setState({ error: this.errors.unit.required });
          return false;
        }
        return true;
      }

      default: return true;
    }
  }

  onChangeBasedOn = (e) => {
    const { value } = e.target;
    const monetaryBased = (value === 'monetary');
    this.setState({ monetaryBased, groupKey: '', include: '', unit: '', error: '' });
  }

  onChangeGroup = (groupKey) => {
    this.setState({ groupKey, unit: '', error: '' });
  }

  onGroupCreated = (productsGroup) => {
    this.props.onGroupCreated(productsGroup);
  }

  onChangeInclude = (value) => {
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

  onChangeUnit = (unit) => {
    this.setState({ unit, error: '' });
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
    this.setState({ pooled });
  }

  onChangeQuantityAffected = (e) => {
    const { checked: quantityAffected } = e.target;
    this.setState({ quantityAffected });
  }

  handlePrev = () => {
    const { stepIndex } = this.state;
    if (stepIndex > 0) {
      this.setState({ stepIndex: stepIndex - 1, error: '' });
    } else {
      // On first step, Back behaves as Cancel
      this.handleCancel();
    }
  }

  handleNext = () => {
    const { stepIndex } = this.state;
    if (this.validateStep(stepIndex)) {
      this.setState({ stepIndex: stepIndex + 1, error: '' });
    }
  };

  resetState = (open = true) => {
    this.setState(Object.assign({}, ServiceProductsGroupCreate.defaultState, { open }));
  }

  handleCancel = () => {
    this.resetState(false);
  }

  handleFinish = () => {
    const { stepIndex, monetaryBased, groupKey, include, unit, shared, pooled, quantityAffected } = this.state;
    if (this.validateStep(stepIndex)) {
      const productsGroup = Immutable.Map().withMutations((groupWithMutations) => {
        groupWithMutations.set('key', groupKey);
        groupWithMutations.set('account_shared', shared);
        groupWithMutations.set('account_pool', pooled);
        groupWithMutations.set('quantity_affected', quantityAffected);
        if (monetaryBased) {
          groupWithMutations.set('cost', parseFloat(include));
        } else {
          groupWithMutations.set('value', isNumber(include) ? parseFloat(include) : include);
          groupWithMutations.set('unit', unit);
        }
      });
      this.props.addGroup(productsGroup);
      this.resetState(false);
    }
  };

  handleToggleBoby = () => {
    this.setState({ open: !this.state.open });
  }

  getUnitsOptions = () => {
    const { propertyTypes } = this.props;
    const propertyType = this.getSelectedGroupPropertyType();
    return getUomByPropertyType(propertyTypes, propertyType)
      .filter(unit => unit.get('unit', false) !== false)
      .map(unit => ({ value: unit.get('name', ''), label: unit.get('label', '') }))
      .toArray();
  }

  getStepContent = (stepIndex) => {
    const { availableProductsGroups, usedKeys, currency, propertyTypes, plays, existingGroupsNames } = this.props;
    const { monetaryBased, groupKey, include, unit, shared, pooled, quantityAffected, error } = this.state;
    const steps = ServiceProductsGroupCreate.steps;
    const selectedGroup = this.getSelectedGroup();
    const unitLabel = getUnitLabelByPropertyType(propertyTypes, this.getSelectedGroupPropertyType(), unit);
    const setIncludesTitle = monetaryBased
      ? `Total ${getSymbolFromCurrency(currency)} included`
      : <>{unitLabel} includes<br />({groupKey})</>;

    switch (stepIndex) {

      case steps.get('SetType', { index: -1 }).index:
        return (
          <FormGroup key="group_type" style={{ marginBottom: 8 }}>
            <Col sm={8} className="col-sm-offset-3">
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
          </FormGroup>
        );

      case steps.get('SelectGroup', { index: -1 }).index:
        return ([
          <FormGroup key="products_group" validationState={error.length > 0 ? 'error' : null} className="mb10">
            <Col as={ControlLabel} sm={3}>
              Products Group<Help contents={GroupsInclude.products_group} />
            </Col>
            <Col sm={8}>
              <ProductsGroupSelect
                value={groupKey}
                onChange={this.onChangeGroup}
                productsGroups={availableProductsGroups}
                usedKeys={usedKeys}
                usageBased={!monetaryBased}
                onGroupCreated={this.onGroupCreated}
                existingGroupsNames={existingGroupsNames}
                plays={plays}
              />
              { error.length > 0 && <HelpBlock>{error}</HelpBlock>}
            </Col>
          </FormGroup>,
          !selectedGroup.isEmpty() && (
            <FormGroup key="products_group_products" className="mb10">
              <Col as={ControlLabel} sm={3}>Products</Col>
              <Col sm={8}>
                <p className="non-editable-field mt10">
                  {selectedGroup.get('rates', Immutable.List()).join(', ')}
                </p>
              </Col>
            </FormGroup>
          ),
        ]);

      case steps.get('SetIncludes', { index: -1 }).index:
        return ([
          <FormGroup validationState={error.length > 0 ? 'error' : null} key="group_includes" className="mb10">
            <Col as={ControlLabel} sm={3}>
              {setIncludesTitle}
            </Col>
            <Col sm={monetaryBased ? 7 : 4}>
              {monetaryBased
                ? <Field onChange={this.onChangeIncludeMonetaryBased} value={include} fieldType="text" />
                : <Field onChange={this.onChangeInclude} value={include} fieldType="unlimited" />
              }
              { error.length > 0 && <HelpBlock>{error}</HelpBlock> }
            </Col>
            {!monetaryBased && (
              <Col sm={3}>
                <Field
                  fieldType="select"
                  value={unit}
                  options={this.getUnitsOptions()}
                  onChange={this.onChangeUnit}
                  placeholder="Select unit..."
                />
              </Col>
            )}
          </FormGroup>,
          <FormGroup key="group_shared" className="mb10">
            <Col sm={8} className="col-sm-offset-3">
              <Checkbox checked={shared} onChange={this.onChangeShared}>
                &nbsp;{"Share with all account's subscribers"}
                <Help contents={GroupsInclude.shared_desc} />
              </Checkbox>
            </Col>
            <Col sm={8} className="col-sm-offset-3">
              <Checkbox disabled={!shared} checked={pooled} onChange={this.onChangePooled}>
                &nbsp;{'Includes is pooled?'}
                <Help contents={GroupsInclude.pooled_desc} />
              </Checkbox>
            </Col>
            <Col sm={8} className="col-sm-offset-3">
              <Checkbox checked={quantityAffected} onChange={this.onChangeQuantityAffected}>
                &nbsp;{'Multiply by Service Quantity'}
                <Help contents={GroupsInclude.quantityAffected_desc} />
              </Checkbox>
            </Col>
          </FormGroup>,
        ]);

      default:
        return '...';
    }
  }

  getSteps = () => ServiceProductsGroupCreate.steps
    .sort(step => step.index)
    .map(step => ({ title: step.label }))
    .toList()
    .toArray();

  render() {
    const { stepIndex, open, groupKey } = this.state;
    const steps = ServiceProductsGroupCreate.steps;
    let modalTitle = 'Add Products Group';
    if (groupKey.length) {
      modalTitle += ` - ${groupKey}`;
    }
    return (
      <div>
        <CreateButton onClick={this.handleToggleBoby} type="Products Group" label="Add" />
        <Modal show={open} keyboard={false} size="lg">

          <div className="modal-header">
            <button type="button" className="close" onClick={this.handleCancel} aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <h4 className="modal-title">{modalTitle}</h4>
          </div>

          <Modal.Body style={{ minHeight: 235 }}>
            <Stepper activeIndex={stepIndex} steps={this.getSteps()} />
            <hr style={{ margin: '20px -20px 35px -20px' }} />
            <Form className="form-horizontal mb0">
              {this.getStepContent(stepIndex)}
            </Form>
          </Modal.Body>

          <Modal.Footer>
            <Button size="sm" variant="outline-secondary" onClick={this.handlePrev} style={{ marginRight: 9, minWidth: 90 }}><i className="fa fa-angle-left" />&nbsp;Back</Button>
            { (stepIndex === steps.count() - 1)
              ? <Button size="sm" onClick={this.handleFinish} style={{ minWidth: 90 }} variant="primary">Add</Button>
              : <Button size="sm" variant="outline-secondary" onClick={this.handleNext} style={{ minWidth: 90 }}>Next&nbsp;<i className="fa fa-angle-right" /></Button>
            }
          </Modal.Footer>
        </Modal>
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  currency: currencySelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
});

export default connect(mapStateToProps)(ServiceProductsGroupCreate);
