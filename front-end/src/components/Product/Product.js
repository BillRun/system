import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { sentenceCase } from 'change-case';
import { Form, FormGroup, ControlLabel, Col, Row, Panel, HelpBlock } from 'react-bootstrap';
import Help from '../Help';
import Field from '@/components/Field';
import { CreateButton } from '@/components/Elements';
import ProductPrice from './components/ProductPrice';
import { ProductDescription } from '@/language/FieldDescriptions';
import { EntityFields } from '../Entity';
import UsageTypesSelector from '../UsageTypes/UsageTypesSelector';
import PlaysSelector from '../Plays/PlaysSelector';
import { EntityTaxDetails } from '@/components/Tax';
import {
  getConfig,
  getUnitLabel,
  getFieldName,
  getFieldNameType,
} from '@/common/Util';
import {
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';
import { object } from 'bfj/src/events';


class Product extends Component {

  static propTypes = {
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    ratingParams: PropTypes.instanceOf(Immutable.List),
    product: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string.isRequired,
    usaget: PropTypes.string,
    planName: PropTypes.string,
    errorMessages: PropTypes.object,
    onFieldUpdate: PropTypes.func.isRequired,
    onFieldRemove: PropTypes.func.isRequired,
    onProductRateAdd: PropTypes.func.isRequired,
    onProductRateRemove: PropTypes.func.isRequired,
    onToUpdate: PropTypes.func.isRequired,
    onUsagetUpdate: PropTypes.func.isRequired,
    roundingTypeOptions: PropTypes.array,
    roundingDecimalsOptions: PropTypes.array,
  }

  static defaultProps = {
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
    ratingParams: Immutable.List(),
    planName: 'BASE',
    product: Immutable.Map(),
    usaget: undefined,
    errorMessages: {
      name: {
        allowedCharacters: 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
      },
    },
    roundingTypeOptions: [
      { value: 'down', label: 'Down' },
      { value: 'up', label: 'Up' },
      { value: 'nearest', label: 'Nearest' }
    ],
    roundingDecimalsOptions:[...Array(11)].map((_, i) => ({value: i , label: i }))
  };

  state = {
    errors: {
      name: '',
    },
    newProductParam: false,
    unit: null,
  }

  componentDidMount() {
    const { product, planName, usaget } = this.props;
    const productPath = ['rates', usaget, planName, 'rate'];
    const prices = product.getIn(productPath, Immutable.List());

    if (prices.size === 0) {
      this.props.onProductRateAdd(productPath);
    }
  }

  onChangeName = (e) => {
    const { errorMessages: { name: { allowedCharacters } } } = this.props;
    const { errors } = this.state;
    const value = e.target.value.toUpperCase();
    const newError = (!getConfig('keyUppercaseRegex', /./).test(value)) ? allowedCharacters : '';
    this.setState({ errors: Object.assign({}, errors, { name: newError }) });
    this.props.onFieldUpdate(['key'], value);
  }

  onChangePlay = (play) => {
    this.props.onFieldUpdate(['play'], play);
  }

  onChangeDescription = (e) => {
    const { value } = e.target;
    this.props.onFieldUpdate(['description'], value);
  }

  onChangeAddToRetail = (e) => {
    const { value } = e.target;
    this.props.onFieldUpdate(['add_to_retail'], value);
  }

  onChangeUsaget = (value) => {
    const { usaget } = this.props;
    this.props.onUsagetUpdate(['rates'], usaget, value);
  }

  onChangeUnit = (unit, usageType) => {
    const { product, planName, usaget } = this.props;
    const usageTypeForRate = usageType || usaget;
    const ratePath = ['rates', usageTypeForRate, planName, 'rate'];
    const rates = product.getIn(ratePath, Immutable.List());
    this.setState({ unit });
    rates.forEach((rate, index) => {
      const rangeUnitsPath = [...ratePath, index, 'uom_display', 'range'];
      const intervalUnitsPath = [...ratePath, index, 'uom_display', 'interval'];
      this.props.onFieldUpdate(rangeUnitsPath, unit);
      this.props.onFieldUpdate(intervalUnitsPath, unit);
    });
  }

  onChangePricingMethod = (e) => {
    const { value } = e.target;
    this.props.onFieldUpdate(['pricing_method'], value);
  }

  onProductRateUpdate = (index, fieldName, value) => {
    const { planName, usaget } = this.props;
    switch (fieldName) {
      case 'to': {
        const fieldPath = ['rates', usaget, planName, 'rate'];
        this.props.onToUpdate(fieldPath, index, value);
      }
        break;

      default: {
        const fieldPath = ['rates', usaget, planName, 'rate', index, fieldName];
        this.props.onFieldUpdate(fieldPath, value);
      }
    }
  }

  onProductRateAdd = () => {
    const { planName, usaget } = this.props;
    const productPath = ['rates', usaget, planName, 'rate'];
    this.props.onProductRateAdd(productPath);
  }
  onProductRateRemove = (index) => {
    const { planName, usaget } = this.props;
    const productPath = ['rates', usaget, planName, 'rate'];
    this.props.onProductRateRemove(productPath, index);
  }

  onChangeTariffCategory = (field, value) => {
    if (this.isRetailRate(value)) {
      this.setRetailRate();
    } else {
      this.setRetailRate(false);
    }
    this.onChangeAdditionalField(field, value);
  }

  onChangeAdditionalField = (field, value) => {
    this.props.onFieldUpdate(field, value);
  }

  onChangeRoundingType = (value) => {
    if(value === ""){
      this.props.onFieldUpdate(['rounding_rules', 'rounding_type'], 'None');
      this.props.onFieldUpdate(['rounding_rules', 'rounding_decimals'], undefined);
      return;
    }
    this.props.onFieldUpdate(['rounding_rules', 'rounding_type'], value);
    this.props.onFieldUpdate(['rounding_rules', 'rounding_decimals'], 2);
  }

  onChangeRoundingDecimals = (value) => {
    if(value === ""){
      this.props.onFieldUpdate(['rounding_rules', 'rounding_decimals'], undefined);
      return;
    }
    this.props.onFieldUpdate(['rounding_rules', 'rounding_decimals'], value);
  }

  onRemoveAdditionalField = (field) => {
    this.props.onFieldRemove(field);
  }

  isRetailRate = (tariffCategory = null) => {
    const { product } = this.props;
    const category = tariffCategory === null
      ? product.get('tariff_category')
      : tariffCategory;
    return category === 'retail';
  }

  setRetailRate = (value = true) => {
    this.onChangeAddToRetail({ target: { value } });
  }

  //BRCD-1337 - Not in use,  Display all parameters that are not used by any input processor should still be displayed.
  filterCustomFields = ratingParams => (field) => {
    const fieldName = field.get('field_name', '');
    const usedAsRatingField = ratingParams.includes(fieldName);
    return ((!fieldName.startsWith('params.') && field.get('field_name', '') !== 'tariff_category') || usedAsRatingField) && field.get('display', false) !== false && field.get('editable', false) !== false;
  };

  filterTariffCategory = field => (field.get('field_name', '') === 'tariff_category' && field.get('display', false) !== false && field.get('editable', false) !== false);

  renderPrices = () => {
    const { product, planName, usaget, mode } = this.props;
    const productPath = ['rates', usaget, planName, 'rate'];
    const prices = product.getIn(productPath, Immutable.List());

    return prices.map((price, i) => (
      <ProductPrice
        mode={mode}
        count={prices.size}
        index={i}
        item={price}
        key={i}
        unit={this.getUnitLabel()}
        onProductEditRate={this.onProductRateUpdate}
        onProductRemoveRate={this.onProductRateRemove}
      />
    ));
  }

  getUnit = () => {
    const { product, usaget } = this.props;
    const { unit } = this.state;
    if (unit === null) {
      return product.getIn(['rates', usaget, 'BASE', 'rate', 0, 'uom_display', 'range'], '');
    }
    return unit;
  }

  getUnitLabel = () => {
    const { propertyTypes, usageTypesData, usaget } = this.props;
    return getUnitLabel(propertyTypes, usageTypesData, usaget, this.getUnit());
  }

  render() {
    const { errors } = this.state;
    const { product, usaget, mode, ratingParams, roundingTypeOptions, roundingDecimalsOptions } = this.props;
    const unit = this.getUnit();
    const pricingMethod = product.get('pricing_method', '');
    const roundingType = product.getIn(['rounding_rules', 'rounding_type'], '');
    const roundingDecimals = product.getIn(['rounding_rules', 'rounding_decimals'], '');
    const editable = (mode !== 'view');

    return (
      <Row>
        <Col lg={12}>
          <Form horizontal>
            <Panel>

              <PlaysSelector
                entity={product}
                editable={editable && mode === 'create'}
                onChange={this.onChangePlay}
              />

              <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('description', getFieldNameType('service'), sentenceCase('title'))}
                  <span className="danger-red"> *</span>
                  <Help contents={ProductDescription.description} />
                </Col>
                <Col sm={8} lg={9}>
                  <Field onChange={this.onChangeDescription} value={product.get('description', '')} editable={editable} />
                </Col>
              </FormGroup>

              { ['clone', 'create'].includes(mode) &&
                <FormGroup validationState={errors.name.length > 0 ? 'error' : null} >
                  <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('key', getFieldNameType('service'), sentenceCase('key'))}
                    <span className="danger-red"> *</span>
                    <Help contents={ProductDescription.key} />
                  </Col>
                  <Col sm={8} lg={9}>
                    <Field onChange={this.onChangeName} value={product.get('key', '')} disabled={!['clone', 'create'].includes(mode)} editable={editable} />
                    { errors.name.length > 0 && <HelpBlock>{errors.name}</HelpBlock> }
                  </Col>
                </FormGroup>
              }

              <EntityFields
                entityName="rates"
                entity={product}
                onChangeField={this.onChangeTariffCategory}
                onRemoveField={this.onRemoveAdditionalField}
                fieldsFilter={this.filterTariffCategory}
                editable={editable}
              />

              { !this.isRetailRate() &&
                <FormGroup>
                  <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('add_to_retail', getFieldNameType('product'), sentenceCase('add to retail'))}
                    <Help contents={ProductDescription.addToRetail} />
                  </Col>
                  <Col sm={8} lg={9}>
                    <Field
                      fieldType="checkbox"
                      onChange={this.onChangeAddToRetail}
                      value={product.get('add_to_retail', false)}
                      editable={editable}
                    />
                  </Col>
                </FormGroup>
              }

              <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  { getFieldName('usage_type', getFieldNameType('product'), 'Unit Type')}
                  <span className="danger-red"> *</span>
                </Col>
                <Col sm={8} lg={9}>
                  { editable && ['clone', 'create'].includes(mode)
                    ? (
                      <UsageTypesSelector
                        usaget={usaget}
                        unit={unit}
                        onChangeUsaget={this.onChangeUsaget}
                        onChangeUnit={this.onChangeUnit}
                      />
                    )
                    : (
                      <div>
                        <Col sm={3} style={{ paddingTop: 7 }}>{usaget}</Col>
                        <Col sm={4} componentClass={ControlLabel} className="pr0 pl0">
                          Units of Measure
                        </Col>
                        <Col sm={5} className="pr0">
                          <UsageTypesSelector
                            usaget={usaget}
                            unit={unit}
                            onChangeUsaget={this.onChangeUsaget}
                            onChangeUnit={this.onChangeUnit}
                            showSelectTypes={false}
                            editable={mode !== 'view'}
                          />
                        </Col>
                      </div>)
                  }
                </Col>
              </FormGroup>

              <EntityFields
                entityName="rates"
                entity={product}
                onChangeField={this.onChangeAdditionalField}
                onRemoveField={this.onRemoveAdditionalField}
                highlightParams={ratingParams}
                editable={editable}
              />

            </Panel>

            <Panel header={<h3>Pricing</h3>}>
              <FormGroup>
                <Col sm={12}>
                  { editable
                    ? (
                      <>
                        <div className="inline mr10">
                          <Field
                            fieldType="radio"
                            name="pricing-method"
                            id="pricing-method-tiered"
                            value="tiered"
                            checked={pricingMethod === 'tiered'}
                            onChange={this.onChangePricingMethod}
                            label="Tiered pricing"
                            className="inline"
                          />
                          &nbsp;
                          <Help contents={ProductDescription.tieredPricing} />
                        </div>

                        <div className="inline">
                          <Field
                            fieldType="radio"
                            name="pricing-method"
                            id="pricing-method-volume"
                            value="volume"
                            checked={pricingMethod === 'volume'}
                            onChange={this.onChangePricingMethod}
                            label="Volume pricing"
                            className="inline"
                          />
                          &nbsp;
                          <Help contents={ProductDescription.volumePricing} />
                        </div>
                      </>
                    )
                    : (
                      <div className="non-editable-field">
                        { pricingMethod === 'tiered'
                          ? 'Tiered pricing'
                          : 'Volume pricing'
                        }
                      </div>
                    )
                  }
                </Col>
              </FormGroup>

              <Col sm={12}>
                { this.renderPrices() }
              </Col>
              { editable && <CreateButton onClick={this.onProductRateAdd} label="Add New" />}
            </Panel>
            { product.get('tariff_category', '') === 'retail' && (
            <Panel header="Tax">
              <EntityTaxDetails
                tax={product.get('tax')}
                mode={mode}
                itemName={"rates"}
                onFieldUpdate={this.props.onFieldUpdate}
                onFieldRemove={this.props.onFieldRemove}
                />
            </Panel>
          )}
            <Panel header={<h3>Rounding Rules</h3>} collapsible className="collapsible">
            <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                  Final charge rounding type
                </Col>
                <Col sm={4}>
                  <Field
                    fieldType="select"
                    options={roundingTypeOptions}
                    onChange={this.onChangeRoundingType}
                    value={roundingType}
                    editable={editable}
                  />
                </Col>
              </FormGroup>
              {(roundingType && roundingType !== 'None') && <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                Final charge rounding Decimals
                </Col>
                <Col sm={4}>
                  <Field
                    fieldType="select"
                    options={roundingDecimalsOptions}
                    onChange={this.onChangeRoundingDecimals}
                    value={roundingDecimals}
                    editable={editable}
                  />
                </Col>
              </FormGroup>}

            </Panel>

          </Form>
        </Col>
      </Row>
    );
  }

}

const mapStateToProps = (state, props) => ({
  usageTypesData: usageTypesDataSelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
});

export default connect(mapStateToProps)(Product);
