import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import { Form, Col, Button } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import { CreateButton } from '@/components/Elements';
import Field from '@/components/Field';
import EntityFields from '../../Entity/EntityFields';
import UsageTypesSelector from '../../UsageTypes/UsageTypesSelector';
import ProductSearchByUsagetype from '../../Plan/components/ProductSearchByUsagetype';
import { usageTypesDataSelector } from '@/selectors/settingsSelector';
import {
  getConfig,
  getFieldName,
  getFieldNameType,
  getUsagePropertyType,
} from '@/common/Util';

class ProductsGroupDetails extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map).isRequired,
    mode: PropTypes.string.isRequired,
    onFieldUpdate: PropTypes.func.isRequired,
    onFieldRemove: PropTypes.func.isRequired,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    errorMessages: PropTypes.object,
    // a monetary based group is not limited to a property type: the activity types
    // cannot be chosen and the products are not filtered by them
    monetaryBased: PropTypes.bool,
    plays: PropTypes.string,
  }

  static defaultProps = {
    usageTypesData: Immutable.List(),
    monetaryBased: false,
    plays: '',
    errorMessages: {
      key: {
        allowedCharacters: 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
      },
    },
  };

  state = {
    errors: {
      key: '',
    },
  }

  onChangeKey = (e) => {
    const { errorMessages: { key: { allowedCharacters } } } = this.props;
    const { errors } = this.state;
    const value = e.target.value.toUpperCase().replace(getConfig('keyUppercaseCleanRegex', /./), "_");
    const newError = (!getConfig('keyUppercaseRegex', /./).test(value)) ? allowedCharacters : '';
    this.setState({ errors: Object.assign({}, errors, { key: newError }) });
    this.props.onFieldUpdate(['key'], value);
  }

  onChangeDescription = (e) => {
    const { value } = e.target;
    this.props.onFieldUpdate(['description'], value);
  }

  isNew = () => ['create', 'clone'].includes(this.props.mode);

  getUsageTypes = () => this.props.item.get('usage_types', Immutable.List());

  getSelectedUsageTypes = () => this.getUsageTypes().filter(usageType => usageType !== '');

  /**
   * the property type of the group: for a new group it is derived from the first
   * chosen activity type; after the group was saved it can not be changed.
   */
  getPropertyType = () => {
    const { item, usageTypesData } = this.props;
    if (this.isNew()) {
      return getUsagePropertyType(usageTypesData, this.getUsageTypes().get(0, ''));
    }
    return item.get('property_type', '');
  }

  /**
   * the activity types the products are searched by: the chosen ones, or - while none
   * is chosen - all the activity types of the group property type, so that clearing the
   * activity types can not be used to add products of another property type.
   */
  getProductsUsageTypes = () => {
    const { usageTypesData, monetaryBased } = this.props;
    if (monetaryBased) {
      return Immutable.List();
    }
    const selectedUsageTypes = this.getSelectedUsageTypes();
    const propertyType = this.getPropertyType();
    if (!selectedUsageTypes.isEmpty() || propertyType === '') {
      return selectedUsageTypes;
    }
    return usageTypesData
      .filter(usageType => usageType.get('property_type', '') === propertyType)
      .map(usageType => usageType.get('usage_type', ''));
  }

  onChangeUsageType = index => (usageType) => {
    const usageTypes = this.getUsageTypes();
    if (index === 0 && this.isNew()) {
      // (re)choosing the first activity type sets the group property type -> reset selections
      this.props.onFieldUpdate(['usage_types'], Immutable.List([usageType]));
      this.props.onFieldUpdate(['rates'], Immutable.List());
    } else {
      this.props.onFieldUpdate(['usage_types'], usageTypes.set(index, usageType));
    }
  }

  onClickAddUsageType = () => {
    const usageTypes = this.getUsageTypes();
    this.props.onFieldUpdate(['usage_types'], usageTypes.push(''));
  }

  onRemoveUsageType = index => () => {
    const usageTypes = this.getUsageTypes();
    this.props.onFieldUpdate(['usage_types'], usageTypes.delete(index));
  }

  onChangeProducts = (productKeys) => {
    this.props.onFieldUpdate(['rates'], productKeys);
  }

  filterUsageTypes = index => (usageType) => {
    const usageTypes = this.getUsageTypes();
    const propertyType = this.getPropertyType();
    return [-1, index].includes(usageTypes.findIndex(usage => usage === usageType.get('usage_type', ''))) &&
      (propertyType === '' || usageType.get('property_type', '') === propertyType);
  }

  noMoreOptions = () => {
    const { usageTypesData } = this.props;
    const propertyType = this.getPropertyType();
    const relevantOptions = usageTypesData.filter(usageType => propertyType === usageType.get('property_type', ''));
    return this.getUsageTypes().size >= relevantOptions.size;
  }

  renderUsageTypes = (editable) => {
    const { monetaryBased } = this.props;
    const usageTypesEditable = editable && !monetaryBased;
    // a saved group with no activity types has a null property type,
    // which can not be changed after the group was saved
    const lockedWithoutUsageTypes = !this.isNew() && this.getPropertyType() === '' && this.getSelectedUsageTypes().isEmpty();
    if (lockedWithoutUsageTypes) {
      return (
        <FormGroup>
          <Col as={ControlLabel} sm={3} lg={2}>
            { getFieldName('usage_types', getFieldNameType('products_group'), sentenceCase('activity types'))}
          </Col>
          <Col sm={8} lg={9}>
            <p className="non-editable-field mt10">All (activity types cannot be added after the group was saved)</p>
          </Col>
        </FormGroup>
      );
    }
    const usageTypes = this.getUsageTypes().isEmpty() && this.isNew()
      ? Immutable.List([''])
      : this.getUsageTypes();
    return (
      <div>
        { usageTypes.map((usageType, index) => (
          <FormGroup key={`usage_type_${index}`}>
            <Col as={ControlLabel} sm={3} lg={2}>
              {index === 0 ? getFieldName('usage_types', getFieldNameType('products_group'), sentenceCase('activity types')) : ''}
            </Col>
            <Col sm={8} lg={9}>
              <Col sm={8} className="pl0">
                <UsageTypesSelector
                  usaget={usageType}
                  onChangeUsaget={this.onChangeUsageType(index)}
                  showUnits={false}
                  showAddButton={index === 0 && this.isNew() && !monetaryBased}
                  usagetFilter={this.filterUsageTypes(index)}
                  editable={editable}
                  enabled={!monetaryBased}
                />
              </Col>
              { index !== 0 && usageTypesEditable && (
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
        { usageTypesEditable && (
          <FormGroup>
            <Col sm={3} lg={2} />
            <Col sm={8} lg={9}>
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

  render () {
    const { errors } = this.state;
    const { item, mode, plays } = this.props;
    const editable = (mode !== 'view');
    const productsUsageTypes = this.getProductsUsageTypes();
    return (
      <Form className="form-horizontal">
        <FormGroup>
          <Col as={ControlLabel} sm={3} lg={2}>
            { getFieldName('description', getFieldNameType('products_group'), sentenceCase('title'))}
            <span className="danger-red"> *</span>
          </Col>
          <Col sm={8} lg={9}>
            <Field value={item.get('description', '')} onChange={this.onChangeDescription} editable={editable} />
          </Col>
        </FormGroup>

        {['clone', 'create'].includes(mode) &&
          <FormGroup validationState={errors.key.length > 0 ? 'error' : null} >
            <Col as={ControlLabel} sm={3} lg={2}>
              { getFieldName('key', getFieldNameType('products_group'), sentenceCase('key'))}
              <span className="danger-red"> *</span>
            </Col>
            <Col sm={8} lg={9}>
              <Field value={item.get('key', '')} onChange={this.onChangeKey} editable={editable} />
              { errors.key.length > 0 && <HelpBlock>{errors.key}</HelpBlock> }
            </Col>
          </FormGroup>
        }

        {this.renderUsageTypes(editable)}

        <FormGroup>
          <Col as={ControlLabel} sm={3} lg={2}>
            { getFieldName('rates', getFieldNameType('products_group'), sentenceCase('products'))}
            <span className="danger-red"> *</span>
          </Col>
          <Col sm={8} lg={9}>
            <ProductSearchByUsagetype
              products={item.get('rates', Immutable.List())}
              usages={productsUsageTypes}
              onChangeGroupRates={this.onChangeProducts}
              disabled={!editable}
              plays={plays}
            />
          </Col>
        </FormGroup>

        <EntityFields
          entityName="productgroups"
          entity={item}
          onChangeField={this.props.onFieldUpdate}
          onRemoveField={this.props.onFieldRemove}
          editable={editable}
        />
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  usageTypesData: usageTypesDataSelector(state, props),
});

export default connect(mapStateToProps)(ProductsGroupDetails);
