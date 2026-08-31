import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { sentenceCase } from 'change-case';
import { Modal, Form, Col, Button } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import { CreateButton } from '@/components/Elements';
import Field from '@/components/Field';
import UsageTypesSelector from '../../UsageTypes/UsageTypesSelector';
import ProductSearchByUsagetype from '../../Plan/components/ProductSearchByUsagetype';
import { saveProductsGroup } from '@/actions/productsGroupsActions';
import { usageTypesDataSelector } from '@/selectors/settingsSelector';
import {
  getConfig,
  getFieldName,
  getFieldNameType,
  getUsagePropertyType,
} from '@/common/Util';

/**
 * Popup to create a products group on-the-fly from the "Service Includes" screen.
 * For a monetary based quota the activity types are disabled and the products
 * are not filtered by activity type.
 */
class ProductsGroupCreatePopup extends Component {

  static propTypes = {
    show: PropTypes.bool,
    monetaryBased: PropTypes.bool,
    plays: PropTypes.string,
    onCancel: PropTypes.func.isRequired,
    onCreated: PropTypes.func.isRequired,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    show: false,
    monetaryBased: false,
    plays: '',
    usageTypesData: Immutable.List(),
  };

  static defaultState = {
    description: '',
    key: '',
    usageTypes: Immutable.List(['']),
    rates: Immutable.List(),
    error: '',
    progress: false,
  }

  state = Object.assign({}, ProductsGroupCreatePopup.defaultState);

  errors = {
    description: 'Title is required',
    keyRequired: 'Key is required',
    keyAllowedCharacters: 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
    usageTypes: 'At least one activity type is required',
    products: 'At least one product is required',
  }

  onChangeDescription = (e) => {
    this.setState({ description: e.target.value, error: '' });
  }

  onChangeKey = (e) => {
    const key = e.target.value.toUpperCase().replace(getConfig('keyUppercaseCleanRegex', /./), '_');
    const error = (key.length && !getConfig('keyUppercaseRegex', /./).test(key)) ? this.errors.keyAllowedCharacters : '';
    this.setState({ key, error });
  }

  getSelectedUsageTypes = () => this.state.usageTypes.filter(usageType => usageType !== '');

  getPropertyType = () => {
    const { usageTypesData } = this.props;
    return getUsagePropertyType(usageTypesData, this.state.usageTypes.get(0, ''));
  }

  onChangeUsageType = index => (usageType) => {
    const { usageTypes } = this.state;
    if (index === 0) {
      // (re)choosing the first activity type sets the group property type -> reset selections
      this.setState({ usageTypes: Immutable.List([usageType]), rates: Immutable.List(), error: '' });
    } else {
      this.setState({ usageTypes: usageTypes.set(index, usageType), error: '' });
    }
  }

  onClickAddUsageType = () => {
    const { usageTypes } = this.state;
    this.setState({ usageTypes: usageTypes.push('') });
  }

  onRemoveUsageType = index => () => {
    const { usageTypes } = this.state;
    this.setState({ usageTypes: usageTypes.delete(index) });
  }

  onChangeProducts = (productKeys) => {
    this.setState({ rates: productKeys, error: '' });
  }

  filterUsageTypes = index => (usageType) => {
    const { usageTypes } = this.state;
    const propertyType = this.getPropertyType();
    return [-1, index].includes(usageTypes.findIndex(usage => usage === usageType.get('usage_type', ''))) &&
      (propertyType === '' || usageType.get('property_type', '') === propertyType);
  }

  noMoreOptions = () => {
    const { usageTypesData } = this.props;
    const propertyType = this.getPropertyType();
    const relevantOptions = usageTypesData.filter(usageType => propertyType === usageType.get('property_type', ''));
    return this.state.usageTypes.size >= relevantOptions.size;
  }

  validate = () => {
    const { monetaryBased } = this.props;
    const { description, key, rates } = this.state;
    if (description === '') {
      return this.errors.description;
    }
    if (key === '') {
      return this.errors.keyRequired;
    }
    if (!getConfig('keyUppercaseRegex', /./).test(key)) {
      return this.errors.keyAllowedCharacters;
    }
    if (!monetaryBased && this.getSelectedUsageTypes().isEmpty()) {
      return this.errors.usageTypes;
    }
    if (rates.isEmpty()) {
      return this.errors.products;
    }
    return '';
  }

  resetState = () => {
    this.setState(Object.assign({}, ProductsGroupCreatePopup.defaultState));
  }

  handleCancel = () => {
    this.resetState();
    this.props.onCancel();
  }

  handleSave = () => {
    const { monetaryBased, usageTypesData } = this.props;
    const { description, key, rates } = this.state;
    const error = this.validate();
    if (error !== '') {
      this.setState({ error });
      return;
    }
    const usageTypes = monetaryBased ? Immutable.List() : this.getSelectedUsageTypes();
    const item = Immutable.Map({
      key,
      description,
      usage_types: usageTypes,
      rates,
      from: moment().toISOString(),
    });
    this.setState({ progress: true });
    this.props.dispatch(saveProductsGroup(item, 'create')).then((response) => {
      this.setState({ progress: false });
      if (response.status) {
        const propertyType = monetaryBased ? null : getUsagePropertyType(usageTypesData, usageTypes.get(0, ''));
        const productsGroup = Immutable.Map({
          key,
          description,
          property_type: propertyType,
          usage_types: usageTypes,
          rates,
        });
        this.resetState();
        this.props.onCreated(productsGroup);
      }
    });
  }

  renderUsageTypes = () => {
    const { monetaryBased } = this.props;
    const usageTypes = monetaryBased ? Immutable.List(['']) : this.state.usageTypes;
    return (
      <div>
        { usageTypes.map((usageType, index) => (
          <FormGroup key={`usage_type_${index}`}>
            <Col as={ControlLabel} sm={3}>
              {index === 0 ? getFieldName('usage_types', getFieldNameType('products_group'), sentenceCase('activity types')) : ''}
            </Col>
            <Col sm={8}>
              <Col sm={8} className="pl0">
                <UsageTypesSelector
                  usaget={usageType}
                  onChangeUsaget={this.onChangeUsageType(index)}
                  showUnits={false}
                  showAddButton={index === 0 && !monetaryBased}
                  usagetFilter={this.filterUsageTypes(index)}
                  enabled={!monetaryBased}
                />
              </Col>
              { index !== 0 && (
                <Col sm={1} className="pr0 pl0">
                  <Button onClick={this.onRemoveUsageType(index)} variant="link" className="pl0">
                    <i className="fa fa-fw danger-red fa-trash-o" />
                  </Button>
                </Col>
              )}
            </Col>
          </FormGroup>
        ))
        }
        { !monetaryBased && (
          <FormGroup>
            <Col sm={3} />
            <Col sm={8}>
              <CreateButton
                onClick={this.onClickAddUsageType}
                label="Additional"
                disabled={usageTypes.get(0, '') === '' || this.noMoreOptions()}
              />
            </Col>
          </FormGroup>
        )}
      </div>
    );
  }

  render() {
    const { show, monetaryBased, plays } = this.props;
    const { description, key, rates, error, progress } = this.state;
    // for a monetary based quota the products are not filtered by activity type
    const searchUsages = monetaryBased ? Immutable.List() : this.getSelectedUsageTypes();
    return (
      <Modal show={show} keyboard={false} size="lg">
        <div className="modal-header">
          <button type="button" className="close" onClick={this.handleCancel} aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 className="modal-title">Create New Products Group</h4>
        </div>
        <Modal.Body>
          <Form className="form-horizontal mb0">
            <FormGroup>
              <Col as={ControlLabel} sm={3}>
                { getFieldName('description', getFieldNameType('products_group'), sentenceCase('title'))}
                <span className="danger-red"> *</span>
              </Col>
              <Col sm={8}>
                <Field value={description} onChange={this.onChangeDescription} />
              </Col>
            </FormGroup>
            <FormGroup>
              <Col as={ControlLabel} sm={3}>
                { getFieldName('key', getFieldNameType('products_group'), sentenceCase('key'))}
                <span className="danger-red"> *</span>
              </Col>
              <Col sm={8}>
                <Field value={key} onChange={this.onChangeKey} />
              </Col>
            </FormGroup>
            {this.renderUsageTypes()}
            <FormGroup>
              <Col as={ControlLabel} sm={3}>
                { getFieldName('rates', getFieldNameType('products_group'), sentenceCase('products'))}
                <span className="danger-red"> *</span>
              </Col>
              <Col sm={8}>
                <ProductSearchByUsagetype
                  products={rates}
                  usages={searchUsages}
                  onChangeGroupRates={this.onChangeProducts}
                  plays={plays}
                />
              </Col>
            </FormGroup>
            { error.length > 0 && (
              <FormGroup validationState="error">
                <Col sm={8} className="col-sm-offset-3">
                  <HelpBlock>{error}</HelpBlock>
                </Col>
              </FormGroup>
            )}
          </Form>
        </Modal.Body>
        <Modal.Footer>
          <Button size="sm" variant="outline-secondary" onClick={this.handleCancel} style={{ marginRight: 9, minWidth: 90 }}>Cancel</Button>
          <Button size="sm" variant="primary" onClick={this.handleSave} disabled={progress} style={{ minWidth: 90 }}>Save</Button>
        </Modal.Footer>
      </Modal>
    );
  }
}

const mapStateToProps = (state, props) => ({
  usageTypesData: usageTypesDataSelector(state, props),
});

export default connect(mapStateToProps)(ProductsGroupCreatePopup);
