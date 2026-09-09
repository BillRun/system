import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Modal, Col, Button, Form, Tooltip, OverlayTrigger } from 'react-bootstrap';
import { Checkbox, ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import Immutable from 'immutable';
import changeCase from 'change-case';
import isNumber from 'is-number';
import getSymbolFromCurrency from 'currency-symbol-map';
import { GroupsInclude } from '../../../language/FieldDescriptions';
import Help from '../../Help';
import { ConfirmModal, Actions } from '@/components/Elements';
import Field from '@/components/Field';
import { validateUnlimitedValue, validatePriceValue } from '@/common/Validators';
import { getUomByPropertyType, getUnitLabelByPropertyType } from '@/common/Util';


export default class ServiceProductsGroupEdit extends Component {

  static propTypes = {
    index: PropTypes.number.isRequired,
    productsGroup: PropTypes.instanceOf(Immutable.Map).isRequired,
    productsGroupData: PropTypes.instanceOf(Immutable.Map),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    currency: PropTypes.string,
    mode: PropTypes.string,
    onChangeFieldValue: PropTypes.func.isRequired,
    onGroupRemove: PropTypes.func.isRequired,
  }

  static defaultProps = {
    productsGroupData: Immutable.Map(),
    propertyTypes: Immutable.List(),
    currency: 'USD',
    mode: 'create',
  };

  state = {
    isEditMode: false,
    showConfirm: false,
    errorInclude: '',
  }

  errors = {
    include: {
      required: 'Include is required',
      allowedCharacters: 'Include must be positive number or Unlimited',
      allowedCharactersMonetary: 'Include must be positive number',
    },
  }

  isMonetaryBased = () => this.props.productsGroup.has('cost');

  getPathPrefix = () => ['include', 'products_groups', this.props.index];

  getPropertyType = () => this.props.productsGroupData.get('property_type', '') || '';

  onChangeInclude = (value) => {
    let errorInclude = '';
    if (value === '') {
      errorInclude = this.errors.include.required;
    } else if (this.isMonetaryBased() && !validatePriceValue(value)) {
      errorInclude = this.errors.include.allowedCharactersMonetary;
    } else if (!this.isMonetaryBased() && !validateUnlimitedValue(value)) {
      errorInclude = this.errors.include.allowedCharacters;
    }
    const valueField = this.isMonetaryBased() ? 'cost' : 'value';
    const newValue = isNumber(value) ? parseFloat(value) : value;
    this.props.onChangeFieldValue([...this.getPathPrefix(), valueField], newValue);
    this.setState({ errorInclude });
  }

  onChangeIncludeMonetaryBased = (e) => {
    const { value } = e.target;
    this.onChangeInclude(value);
  }

  onChangeUnit = (unit) => {
    this.props.onChangeFieldValue([...this.getPathPrefix(), 'unit'], unit);
  }

  onChangeShared = (e) => {
    const { checked } = e.target;
    this.props.onChangeFieldValue([...this.getPathPrefix(), 'account_shared'], checked);
    if (!checked) {
      this.props.onChangeFieldValue([...this.getPathPrefix(), 'account_pool'], false);
      this.props.onChangeFieldValue([...this.getPathPrefix(), 'quantity_affected'], false);
    }
  }

  onChangePooled = (e) => {
    const { checked } = e.target;
    this.props.onChangeFieldValue([...this.getPathPrefix(), 'account_pool'], checked);
    if (!checked) {
      this.props.onChangeFieldValue([...this.getPathPrefix(), 'quantity_affected'], false);
    }
  }

  onChangeQuantityAffected = (e) => {
    const { checked } = e.target;
    this.props.onChangeFieldValue([...this.getPathPrefix(), 'quantity_affected'], checked);
  }

  onGroupRemoveAsk = () => {
    this.setState({ showConfirm: true });
  }

  onGroupRemoveOk = () => {
    const { index } = this.props;
    this.props.onGroupRemove(index);
    this.setState({ showConfirm: false });
  }

  onGroupRemoveCancel = () => {
    this.setState({ showConfirm: false });
  }

  toggleBoby = () => {
    const { errorInclude } = this.state;
    if (errorInclude === '') {
      this.setState({ isEditMode: !this.state.isEditMode });
    }
  }

  getListActions = () => {
    const { mode } = this.props;
    const allowEdit = mode !== 'view';
    return ([
      { type: 'edit', helpText: 'Edit products group include', onClick: this.toggleBoby, enable: allowEdit, actionClass: 'pl0 pr0' },
      { type: 'remove', helpText: 'Remove products group include', onClick: this.onGroupRemoveAsk, enable: allowEdit, actionClass: 'pl0 pr0' },
    ]);
  }

  getUnitsOptions = () => {
    const { propertyTypes } = this.props;
    return getUomByPropertyType(propertyTypes, this.getPropertyType())
      .filter(unit => unit.get('unit', false) !== false)
      .map(unit => ({ value: unit.get('name', ''), label: unit.get('label', '') }))
      .toArray();
  }

  getValue = () => {
    const { productsGroup } = this.props;
    return this.isMonetaryBased() ? productsGroup.get('cost', '') : productsGroup.get('value', '');
  }

  getUnitLabel = () => {
    const { productsGroup, propertyTypes, currency } = this.props;
    if (this.isMonetaryBased()) {
      return getSymbolFromCurrency(currency);
    }
    return getUnitLabelByPropertyType(propertyTypes, this.getPropertyType(), productsGroup.get('unit', ''));
  }

  renderProductsTooltip = productsLabels => (
    <Tooltip id={productsLabels}>
      {productsLabels}
    </Tooltip>
  )

  renderEdit = () => {
    const { productsGroup, productsGroupData } = this.props;
    const { isEditMode, errorInclude } = this.state;
    const name = productsGroup.get('key', '');
    const shared = productsGroup.get('account_shared', false);
    const pooled = productsGroup.get('account_pool', false);
    const quantityAffected = productsGroup.get('quantity_affected', false);
    const value = this.getValue();
    const typeLabel = this.isMonetaryBased() ? 'Monetary based' : 'Usage based';
    return (
      <Modal show={isEditMode} size="lg">
        <div className="modal-header">
          <button type="button" className="close" onClick={this.toggleBoby} aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 className="modal-title">Edit {name} <i>{typeLabel}</i></h4>
        </div>
        <Modal.Body>
          <Form className="form-horizontal mb0">
            <FormGroup validationState={errorInclude.length > 0 ? 'error' : null}>
              <Col as={ControlLabel} sm={2}>Include</Col>
              <Col sm={5}>
                {this.isMonetaryBased()
                  ? <Field onChange={this.onChangeIncludeMonetaryBased} value={value} fieldType="text" />
                  : <Field onChange={this.onChangeInclude} value={value} fieldType="unlimited" />
                }
                { errorInclude.length > 0 && <HelpBlock>{errorInclude}</HelpBlock> }
              </Col>
              {!this.isMonetaryBased() &&
                <Col sm={3}>
                  <Field
                    fieldType="select"
                    value={productsGroup.get('unit', '')}
                    options={this.getUnitsOptions()}
                    onChange={this.onChangeUnit}
                  />
                </Col>}
            </FormGroup>

            <FormGroup>
              <Col sm={9} className="col-sm-offset-2">
                <Checkbox checked={shared} onChange={this.onChangeShared}>{"Share with all account's subscribers"}<Help contents={GroupsInclude.shared_desc} /></Checkbox>
              </Col>
            </FormGroup>

            <FormGroup>
              <Col sm={9} className="col-sm-offset-2">
                <Checkbox disabled={!shared} checked={pooled} onChange={this.onChangePooled}>{'Includes is pooled?'}<Help contents={GroupsInclude.pooled_desc} /></Checkbox>
              </Col>
            </FormGroup>

            <FormGroup>
              <Col sm={9} className="col-sm-offset-2">
                <Checkbox checked={quantityAffected} onChange={this.onChangeQuantityAffected}>{'Multiply by Service Quantity'}<Help contents={GroupsInclude.quantityAffected_desc} /></Checkbox>
              </Col>
            </FormGroup>

            <FormGroup>
              <Col as={ControlLabel} sm={2}>Products</Col>
              <Col sm={9}>
                <p className="non-editable-field mt10">
                  {productsGroupData.get('rates', Immutable.List()).join(', ')}
                </p>
              </Col>
            </FormGroup>
          </Form>
        </Modal.Body>
        <Modal.Footer>
          <Button onClick={this.toggleBoby} variant="primary" size="sm" style={{ minWidth: 90 }}>OK</Button>
        </Modal.Footer>
      </Modal>
    );
  }

  render() {
    const { productsGroup, productsGroupData } = this.props;
    const { showConfirm } = this.state;
    const name = productsGroup.get('key', '');
    const shared = productsGroup.get('account_shared', false);
    const pooled = productsGroup.get('account_pool', false);
    const quantityAffected = productsGroup.get('quantity_affected', false);
    const confirmMessage = `Are you sure you want to remove ${name} products group?`;
    const sharedLabel = shared ? 'Yes' : 'No';
    const pooledLabel = pooled ? 'Yes' : 'No';
    const quantityAffectedLabel = quantityAffected ? 'Yes' : 'No';
    const typeLabel = this.isMonetaryBased() ? 'Monetary based' : 'Usage based';
    const valueLabel = changeCase.titleCase(this.getValue());
    const productsLabels = productsGroupData.get('rates', Immutable.List()).join(', ');
    const tooltip = this.renderProductsTooltip(productsLabels);
    const actions = this.getListActions();
    return (
      <tr className="List">
        <td className="td-ellipsis">
          <OverlayTrigger placement="right" overlay={tooltip}>
            <span>{name}</span>
          </OverlayTrigger>
        </td>
        <td className="td-ellipsis">{typeLabel}</td>
        <td className="td-ellipsis">{valueLabel}</td>
        <td className="td-ellipsis">{this.getUnitLabel()}</td>
        <td className="td-ellipsis text-center">{sharedLabel}</td>
        <td className="td-ellipsis text-center">{pooledLabel}</td>
        <td className="td-ellipsis text-center">{quantityAffectedLabel}</td>
        <td className="text-right row pr0 pl0">
          <Actions actions={actions} />
          <ConfirmModal onOk={this.onGroupRemoveOk} onCancel={this.onGroupRemoveCancel} show={showConfirm} message={confirmMessage} labelOk="Yes" />
          {this.renderEdit()}
        </td>
      </tr>
    );
  }

}
