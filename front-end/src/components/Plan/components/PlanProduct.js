import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { Col, Panel, Button, FormGroup, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import Help from '../../Help';
import { CreateButton } from '@/components/Elements';
import ProductPrice from '../../Product/components/ProductPrice';
import { getUnitLabel } from '@/common/Util';

export default class PlanProduct extends Component {

  static propTypes = {
    onProductInitRate: PropTypes.func.isRequired,
    onProductAddRate: PropTypes.func.isRequired,
    onProductEditRate: PropTypes.func.isRequired,
    onProductEditRateTo: PropTypes.func.isRequired,
    onProductRemoveRate: PropTypes.func.isRequired,
    onProductRemove: PropTypes.func.isRequired,
    onProductRestore: PropTypes.func.isRequired,
    mode: PropTypes.string,
    usaget: PropTypes.string.isRequired,
    item: PropTypes.instanceOf(Immutable.Map),
    prices: PropTypes.instanceOf(Immutable.List),
    usageTypes: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    percentage: PropTypes.number,
  }

  static defaultProps = {
    item: Immutable.Map(),
    prices: Immutable.List(),
    usageTypes: Immutable.List(),
    propertyTypes: Immutable.List(),
    mode: 'create',
    percentage: null,
  };

  componentWillMount() {
    const { item, usaget, prices, percentage } = this.props;
    this.addDefaultPriceIfNoPrice(item, usaget, prices, percentage);
  }

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    const { prices, mode, usageTypes, propertyTypes, percentage } = this.props;
    return !Immutable.is(prices, nextProps.prices)
      || mode !== nextProps.mode
      || percentage !== nextProps.percentage
      || !Immutable.is(prices, nextProps.prices)
      || !Immutable.is(usageTypes, nextProps.usageTypes)
      || !Immutable.is(propertyTypes, nextProps.propertyTypes);
  }

  componentWillUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    const { item, usaget, prices, percentage } = nextProps;
    this.addDefaultPriceIfNoPrice(item, usaget, prices, percentage);
  }

  addDefaultPriceIfNoPrice = (item, usaget, prices, percentage) => {
    // if product don't have pricing for this plan, init with BASE price
    const isPercentage = percentage !== null;
    if (prices.size === 0 && !isPercentage) {
      const productKey = item.get('key', '');
      const productPath = ['rates', productKey, usaget, 'rate'];
      this.props.onProductInitRate(item, productPath);
    }
  }

  onProductEditRate = (index, fieldName, value) => {
    const { item, usaget } = this.props;
    const productKey = item.get('key');
    switch (fieldName) {
      case 'to': {
        const fieldPath = ['rates', productKey, usaget, 'rate'];
        this.props.onProductEditRateTo(fieldPath, index, value);
      }
        break;

      default: {
        const fieldPath = ['rates', productKey, usaget, 'rate', index, fieldName];
        this.props.onProductEditRate(fieldPath, value);
      }
    }
  }

  onProductAddRate = () => {
    const { item, usaget } = this.props;
    const productKey = item.get('key');
    const productPath = ['rates', productKey, usaget, 'rate'];
    this.props.onProductAddRate(productPath);
  }

  onProductRemoveRate = (index) => {
    const { item, usaget } = this.props;
    const productKey = item.get('key');
    const productPath = ['rates', productKey, usaget, 'rate'];
    this.props.onProductRemoveRate(productPath, index);
  }

  onProductRemove = () => {
    const { item } = this.props;
    const productKey = item.get('key');
    const productPath = ['rates'];
    this.props.onProductRemove(productPath, productKey);
  }

  onProductRestore = () => {
    const { item, usaget } = this.props;
    const productKey = item.get('key', '');
    const productPath = ['rates', productKey, usaget, 'rate'];
    this.props.onProductRestore(item, productPath);
  }

  onChangeOverrideType = (e) => {
    const { item, usaget } = this.props;
    const productKey = item.get('key');
    const { value } = e.target;
    if (value === 'percentage') {
      const initPercentage = Immutable.Map({ [usaget]: Immutable.Map({ percentage: 100 }) });
      const fieldPath = ['rates', productKey];
      this.props.onProductEditRate(fieldPath, initPercentage);
    } else {
      // Remove percentage if exists
      const initPercentage = Immutable.Map({ [usaget]: Immutable.Map({ rate: Immutable.List() }) });
      const fieldPath = ['rates', productKey];
      this.props.onProductEditRate(fieldPath, initPercentage);
    }
  }

  onChangePercentage = (e) => {
    const { value } = e.target;
    const { item, usaget } = this.props;
    const newValue = isNumber(value) ? parseFloat(value) : value;
    const productKey = item.get('key');
    const fieldPath = ['rates', productKey, usaget, 'percentage'];
    this.props.onProductEditRate(fieldPath, newValue);
  }

  render() {
    const { item, prices, usaget, mode, propertyTypes, usageTypes, percentage } = this.props;
    const unit = prices.getIn([0, 'uom_display', 'range'], '');
    const editable = (mode !== 'view');
    const priceCount = prices.size;
    const isPercentage = percentage !== null;
    const pricingMethod = (item.get('pricing_method', 'Tiered') === 'volume') ? 'Volume' : 'Tiered';
    const header = (
      <h3>
        { `${item.get('key')} (${usaget}) `} <i>{item.get('code', '')}</i><Help contents={item.get('description', '')} />
        { editable && <Button onClick={this.onProductRemove} bsSize="xsmall" className="pull-right" style={{ minWidth: 80 }}><i className="fa fa-trash-o danger-red" />&nbsp;Remove</Button>}
        { editable && <Button onClick={this.onProductRestore} bsSize="xsmall" className="pull-right" style={{ marginRight: 10, minWidth: 80 }}><i className="fa fa-undo fa-lg" /> &nbsp;Undo Changes </Button>}
      </h3>
    );

    return (
      <Panel header={header}>
        { editable && (
          <FormGroup className="mb0">
            <span className="inline mr10">
              <Field
                fieldType="radio"
                value="no"
                onChange={this.onChangeOverrideType}
                name={`${item.get('key')}-override-type`}
                label="Override with specific prices"
                checked={!isPercentage}
              />
            </span>
            <span className="inline">
              <Field
                className="inline mr10"
                fieldType="radio"
                value="percentage"
                onChange={this.onChangeOverrideType}
                name={`${item.get('key')}-override-type`}
                label="Override by percentage of the original price"
                checked={isPercentage}
              />
              { isPercentage && (
                <Field
                  style={{ display: 'inline-block', width: 115, verticalAlign: 'middle' }}
                  fieldType="number"
                  onChange={this.onChangePercentage}
                  value={percentage}
                  editable={editable}
                  suffix="%"
                  max={10000}
                  min={0}
                  step={1}
                />
              )}
            </span>
          </FormGroup>
        )}
        { isPercentage && !editable && (
          <FormGroup className="mb0">
            <span>Original price overridden by: </span>
            <Field
              style={{ display: 'inline-block', width: 115, verticalAlign: 'middle' }}
              fieldType="number"
              value={percentage}
              editable={false}
              suffix="%"
            />
          </FormGroup>
        )}
        { !isPercentage && (
          <FormGroup style={{ margin: 3 }}>
            {<ControlLabel>{`${pricingMethod} Pricing`}</ControlLabel>}
          </FormGroup>
        )}
        { !isPercentage && prices.map((price, i) => (
          <Col sm={12} key={`${item.get('key', i)}_${i}`}>
            <ProductPrice
              item={price}
              index={i}
              mode={mode}
              count={priceCount}
              unit={getUnitLabel(propertyTypes, usageTypes, usaget, unit)}
              onProductEditRate={this.onProductEditRate}
              onProductRemoveRate={this.onProductRemoveRate}
            />
          </Col>
        ))}
        { !isPercentage && editable && (
          <FormGroup style={{ margin: 0 }}>
            <CreateButton onClick={this.onProductAddRate} label="Add New" buttonStyle={{ marginTop: 0 }} />
          </FormGroup>
        )}
      </Panel>
    );
  }
}
