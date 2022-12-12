import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { Form, FormGroup, ControlLabel, Col, HelpBlock, InputGroup } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import { getFieldName } from '@/common/Util';
import { ModalWrapper, Actions } from '@/components/Elements';
import Field from '@/components/Field';
import Help from '@/components/Help';
import { DiscountDescription } from '@/language/FieldDescriptions';

class DiscountServiceValue extends Component {

  static propTypes = {
    name: PropTypes.string,
    label: PropTypes.string,
    service: PropTypes.instanceOf(Immutable.Map),
    isQuantitative: PropTypes.bool.isRequired,
    isPercentage: PropTypes.bool.isRequired,
    mode: PropTypes.string.isRequired,
    currency: PropTypes.string,
    onChange: PropTypes.func.isRequired,
  };

  static defaultProps = {
    name: '',
    label: '',
    service: Immutable.Map(),
    isQuantitative: false,
    isPercentage: false,
    currency: '',
  };

  state = {
    showAdvancedEdit: false,
  }

  onChangeValue = (e) => {
    const { value } = e.target;
    const { name, service } = this.props;
    const newValue = service.set('value', value);
    this.props.onChange(name, newValue);
  }

  onChangeSequential = (e) => {
    const { value } = e.target;
    const { name, service } = this.props;
    const newSequential = service.set('sequential', value);
    this.props.onChange(name, newSequential);
  }

  onChangeAmount = (value) => {
    const { service, name } = this.props;
    if (value === null || value === '') { // delete
      this.props.onChange(name, this.removeQuantity(service));
      return;
    }
    let newAmount = isNumber(value) ? parseFloat(value) : value;
    newAmount = Number.isInteger(newAmount) ? newAmount : '';

    const newParam = Immutable.Map({
      name: 'quantity',
      value: newAmount,
    });
    const newOperation = Immutable.Map({
      name: 'recurring_by_quantity',
      params: Immutable.List([newParam]),
    });

    const operations = service.get('operations', Immutable.List());
    const recurringByQuantityIdx = operations.findIndex(operation => operation.get('name') === 'recurring_by_quantity');
    if (recurringByQuantityIdx === -1) { // No operations - recurring_by_quantity
      const withNewOperation = service.updateIn(
        ['operations'],
        Immutable.List(),
        ops => ops.push(newOperation),
      );
      this.props.onChange(name, withNewOperation);
      return;
    }
    const params = operations.getIn([recurringByQuantityIdx, 'params'], Immutable.List());
    const quantityIdx = params.findIndex(param => param.get('name', '') === 'quantity');
    if (quantityIdx === -1) { // No param - quantity
      const withQuantity = service.updateIn(
        ['operations', recurringByQuantityIdx, 'params'],
        Immutable.List(),
        list => list.push(newParam),
      );
      this.props.onChange(name, withQuantity);
      return;
    }
    const withQuantity = service.updateIn(
      ['operations', recurringByQuantityIdx, 'params', quantityIdx],
      Immutable.Map(),
      param => param.set('value', newAmount),
    );
    this.props.onChange(name, withQuantity);
  }

  onCloseModal = () => {
    this.setState({ showAdvancedEdit: false });
  };

  onOpenModal = () => {
    this.setState({ showAdvancedEdit: true });
  };

  removeQuantity = (currentDiscountValue) => {
    const operationsPath = ['operations'];

    const recurringByQuantityIndex = currentDiscountValue
      .getIn(operationsPath, Immutable.List())
      .findIndex(operation => operation.get('name', '') === 'recurring_by_quantity');

    const paramsPath = [...operationsPath, recurringByQuantityIndex, 'params'];
    const quantityIndex = recurringByQuantityIndex === -1
      ? -1
      : currentDiscountValue
        .getIn(paramsPath)
        .findIndex(param => param.get('name', '') === 'quantity');
    const quantityPath = [...paramsPath, quantityIndex];

    // remove quantity if exists
    const discountWithoutQuantity = (quantityIndex !== -1)
      ? currentDiscountValue.deleteIn(quantityPath)
      : currentDiscountValue;
    // remove params if empty
    const isParamEmpty = discountWithoutQuantity.getIn(paramsPath, Immutable.Map()).isEmpty();
    const discountWithoutParams = (recurringByQuantityIndex !== -1 && isParamEmpty)
      ? discountWithoutQuantity.deleteIn(paramsPath)
      : discountWithoutQuantity;
    // remove recurring_by_quantity if empty
    const recurringByQuantitySize = discountWithoutParams.getIn(paramsPath, Immutable.Map()).size; // can has {name:"recurring_by_quantity"} param
    const discountWithoutRecurringByQuantity = (recurringByQuantityIndex !== -1 && recurringByQuantitySize < 2)
      ? discountWithoutParams.deleteIn([...operationsPath, recurringByQuantityIndex])
      : discountWithoutParams;
    // remove operations if empty
    const isOperationsEmpty = discountWithoutRecurringByQuantity
      .getIn(operationsPath, Immutable.Map())
      .isEmpty();
    const discountWithoutOperations = isOperationsEmpty
      ? discountWithoutRecurringByQuantity.deleteIn(operationsPath)
      : discountWithoutRecurringByQuantity;
    return discountWithoutOperations;
  }

  getDiscountDisplayValue = () => {
    const { service, isPercentage, currency } = this.props;
    let value = service.get('value', '');
    if (isPercentage && isNumber(value)) {
      value *= 100;
      value = `${value}%`;
    }
    if (!isPercentage && isNumber(value)) {
      value = `${value}${getSymbolFromCurrency(currency)}`;
    }
    return value;
  }

  getDiscountAmount = () => {
    const { service } = this.props;
    return service.get('operations', Immutable.List())
      .find(operation => operation.get('name') === 'recurring_by_quantity', null, Immutable.Map())
      .get('params', Immutable.List())
      .find(param => param.get('name', '') === 'quantity', null, Immutable.Map())
      .get('value', '');
  }

  getActions = () => {
    const { mode, isQuantitative, service } = this.props;
    const value = service.get('value', '');
    return ([{
      type: 'settings',
      onClick: this.onOpenModal,
      show: mode !== 'view' && isQuantitative,
      actionSize: 'small',
      enable: ![null, ''].includes(value),
      helpText: 'Advanced Options',
    }]);
  }

  renderAdvancedEdit = () => {
    const { showAdvancedEdit } = this.state;
    const { label, mode, isQuantitative } = this.props;
    if (!showAdvancedEdit) {
      return null;
    }
    const title = `Edit ${label} service value options`;
    const editable = (mode !== 'view');

    return (
      <ModalWrapper show={true} onOk={this.onCloseModal} title={title}>
        <Form horizontal>
          {isQuantitative && (
            <FormGroup>
              <Col sm={11} smOffset={1}>
                <Field
                  fieldType="toggeledInput"
                  value={this.getDiscountAmount()}
                  disabledDisplayValue=""
                  disabledValue=""
                  onChange={this.onChangeAmount}
                  label={`Apply discount value ${this.getDiscountDisplayValue()} for every`}
                  editable={editable}
                  suffix="units"
                  style={{ width: 300 }}
                  inputProps={{ fieldType: 'number', style: { width: 85 }, min: 1 }}
                />
              </Col>
            </FormGroup>
          )}
        </Form>
      </ModalWrapper>
    );
  }

  renderQuantitativeDescription = () => {
    const discountAmount = this.getDiscountAmount();
    const discountValue = this.getDiscountDisplayValue();
    if (['', null].includes(discountAmount) && discountValue) {
      return (
        <HelpBlock>
          Amount will be multiplied by the subscriber&apos;s service quantity
        </HelpBlock>
      );
    }
    if (!['', null].includes(discountValue)) {
      return (
        <HelpBlock>
          Apply discount value {discountValue} for every {discountAmount} units
        </HelpBlock>
      );
    }
    return null;
  }

  renderSuffix = () => {
    const { isPercentage, currency } = this.props;
    return isPercentage ? undefined : getSymbolFromCurrency(currency);
  }

  renderSequentialLabel = () => (
    <span>
      {getFieldName('sequential', 'discount')}
      <Help contents={DiscountDescription.sequential} />
    </span>
  )

  render() {
    const { name, label, mode, service, isQuantitative, isPercentage } = this.props;
    if (name === '') {
      return null;
    }
    const editable = (mode !== 'view');
    const value = this.getDiscountDisplayValue();
    if (!editable && value === null) {
      return null;
    }
    const actions = this.getActions();
    const hasEnabledActions = actions
      .reduce((acc, action) => (action.show !== false ? true : acc), false);
    const showSequential = isPercentage && !isQuantitative;
    return (
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3} lg={2}>
          { label }
        </Col>
        <Col sm={8} lg={9}>
          <InputGroup className="full-width">
            <Field
              value={service.get('value', '')}
              onChange={this.onChangeValue}
              fieldType={isPercentage ? "percentage" : "number"}
              editable={editable}
              suffix={this.renderSuffix()}
            />
            { hasEnabledActions && (
              <InputGroup.Addon className="input-group-space pr0 pl5"> </InputGroup.Addon>
            )}
            { hasEnabledActions && (
                <InputGroup.Addon className="not-left-border">
                  <Actions actions={actions} data={service} />
                </InputGroup.Addon>
            )}
            { showSequential && (
                <InputGroup.Addon className="input-group-space pr0 pl5"> </InputGroup.Addon>
            )}
            { showSequential && (
              <InputGroup.Addon>
                <Field
                  value={service.get('sequential', '')}
                  onChange={this.onChangeSequential}
                  fieldType="checkbox"
                  label={this.renderSequentialLabel()}
                />
              </InputGroup.Addon>
            )}
          </InputGroup>
          { isQuantitative && this.renderQuantitativeDescription() }
          { this.renderAdvancedEdit() }
        </Col>
      </FormGroup>
    );
  }
}

export default DiscountServiceValue;
