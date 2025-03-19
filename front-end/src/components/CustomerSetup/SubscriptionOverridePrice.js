import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { titleCase } from 'change-case';
import { FormGroup, ControlLabel, Col, Panel, Form } from 'react-bootstrap';
import Field from '@/components/Field';
import { CreateButton } from '@/components/Elements';
import PlanPrice from '../Plan/components/PlanPrice';
import {
    getConfig,
    reCalculateCycles,
  } from '@/common/Util';


const SubscriptionOverridePrice = ({
    type,
    overrides,
    options,
    onChangePrice,
    onChangeSelect,
    editable,
}) => {

    const unlimitedKey = type === 'service' ? 'serviceCycleUnlimitedValue' : 'planCycleUnlimitedValue';
    const CYCLE_UNLIMITED = getConfig(unlimitedKey, 'UNLIMITED');
    const defaultTariff = Immutable.Map({
        price: '',
        from: 0,
        to: CYCLE_UNLIMITED,
    });
    
    const typeOverrides = overrides
        .filter(override => override.get('type', '') === type)
        .reduce((acc, data) => acc.set(data.get('key', ''), data.getIn(['value', 'price'], Immutable.Map())), Immutable.Map());

    const selectedItems = typeOverrides.keySeq().toList();

    const onChangeItemSelect = (items) => {
        return onChangeSelect(type, items);
    }

    const onTariffAdd = (name) => {
        const prices = typeOverrides.get(name, Immutable.List());
        if (prices.isEmpty()) {
            const newPrices = prices.push(defaultTariff);
            return onChangePrice(type, name, newPrices);
        }
        const newPrices = prices
            .update(prices.size - 1, Immutable.Map(), item => item.set('to', ''))
            .push(defaultTariff.set('from', ''))
        return onChangePrice(type, name, newPrices);
    }

    const onTariffRemove = (name) => {
        const prices = typeOverrides.get(name, Immutable.List());
        const size = prices.size;
        if (size < 2) {
            const newPrices = Immutable.List([defaultTariff]);
            return onChangePrice(type, name, newPrices);
        }
        const newPrices = prices
            .setIn([size-2, 'to'], 'UNLIMITED')
            .pop();
        return onChangePrice(type, name, newPrices);
    }

    const onPriceUpdate = (name, index, value) => {
        const newValue = isNumber(value) ? parseFloat(value) : value;
        const newPrices = typeOverrides
            .get(name, Immutable.List())
            .setIn([index, 'price'], newValue);
        return onChangePrice(type, name, newPrices);
    }

    const onCycleUpdate = (name, index, value) => {
        const newValue = isNumber(value) ? parseFloat(value) : value;
        const prices = typeOverrides.get(name, Immutable.List())
        const newPrices = reCalculateCycles(prices, index, newValue, CYCLE_UNLIMITED)
        return onChangePrice(type, name, newPrices);
    }

    const getLabel = (name) => {
        const o = options.find(option => option.value === name);
        if (typeof o === "undefined") {
            return name;
        }
        return (<span>{o.label} <small>({name})</small></span>);
    }

    const getRecurringPrices = (name) => {
        const onOverridePriceUpdate = (index, value) => {
            onPriceUpdate(name, index, value);
        }
        const onOverrideCycleUpdate = (index, value) => {
            onCycleUpdate(name, index, value);
        }
        const onOverrideTariffRemove = () => {
            onTariffRemove(name);
        }
        const count = typeOverrides.get(name, Immutable.List()).size;
        return typeOverrides.get(name, Immutable.List()).map((price, i) => (
            <PlanPrice
                key={`subscription-${type}-price-override-${name}-${i}`}
                index={i}
                count={count}
                item={price}
                mode={editable ? 'create' : 'view'}
                isTrialExist={false}
                onPlanPriceUpdate={onOverridePriceUpdate}
                onPlanCycleUpdate={onOverrideCycleUpdate}
                onPlanTariffRemove={onOverrideTariffRemove}
            />
        ));
    }

    return (
        <Form horizontal>
            <Panel header={<h3>Override {titleCase(type)} Prices</h3>}>
                <FormGroup>
                    <Col componentClass={ControlLabel} sm={3} lg={2}>Select {titleCase(type)}:</Col>
                    <Col sm={8} lg={9}>
                        <Field
                            fieldType="select"
                            multi={true}
                            options={options}
                            value={selectedItems.join(',')}
                            onChange={onChangeItemSelect}
                            clearable={false}
                            editable={editable}
                        />
                    </Col>
                </FormGroup>
                <FormGroup>
                    {!selectedItems.isEmpty() && (
                        selectedItems.map((name) => {
                            const s = options.find((options) => options.value == name);
                            const onItemTariffAdd = () => {
                                onTariffAdd(name);
                            }
                            return (
                                <Col sm={12} key={`subscription-${type}-price-override-${name}`}>
                                    <Panel header={<h3>{getLabel(name)}</h3>}>
                                        { getRecurringPrices(name) }
                                        {s.isByCycles && editable && (
                                            <CreateButton onClick={onItemTariffAdd} label="Add New" />
                                        )}
                                    </Panel>
                                </Col>
                            )
                        })
                    )}
                </FormGroup>
            </Panel>
      </Form>
    );
};

SubscriptionOverridePrice.defaultProps = {
    overrides: Immutable.List(),
    options: [],
    editable: true,
};

SubscriptionOverridePrice.propTypes = {
    type: PropTypes.string.isRequired,
    overrides: PropTypes.instanceOf(Immutable.List),
    options: PropTypes.array,
    onChangePrice: PropTypes.func.isRequired,
    onChangeSelect: PropTypes.func.isRequired,
    editable: PropTypes.bool,
};

export default SubscriptionOverridePrice;