import React from "react";
import PropTypes from "prop-types";
import Immutable from "immutable";
import isNumber from "is-number";
import { FormGroup, Col, Form } from "react-bootstrap";
import { ControlLabel, Panel } from "@/common/BootstrapCompat";
import Field from "@/components/Field";
import { CreateButton } from "@/components/Elements";
import PlanPrice from "../Plan/components/PlanPrice";
import { getConfig, getFieldName, reCalculateCycles } from "@/common/Util";

const OverridePrice = ({ type, overrides = Immutable.List(), options = [], onChange, editable = true }) => {

  const unlimitedKey = type === "service" ? "serviceCycleUnlimitedValue" : "planCycleUnlimitedValue";
  const unlimitedValue = getConfig(unlimitedKey, "UNLIMITED");
  const defaultTariff = Immutable.Map({
    price: "",
    from: 0,
    to: unlimitedValue,
  });

  const getDefaultOverrides = (type, key) => Immutable.Map({
    key,
    type,
    value: Immutable.Map({
      price: Immutable.List([defaultTariff]),
    }),
  });

  const overridesNamesOfType = overrides
    .filter((override) => override.get("type", "") === type)
    .reduce((acc, data) => acc.set(data.get("key", ""), data.getIn(["value", "price"], Immutable.Map())),
      Immutable.Map()
    );

  const selectedItems = overridesNamesOfType.keySeq().toList();

  const onChangeSelect = (itemsNames) => {
    const newOverridesNames = Immutable.List(itemsNames.split(",").filter((name) => name.trim().length > 0));
    let updatedOverrides = Immutable.List(overrides); // clone
    // Remove removed
    overrides.forEach((item, index) => {
      if (item.get('type', '') === type && !newOverridesNames.includes(item.get('key', ''))) {
        updatedOverrides = updatedOverrides.delete(index);
      }
    });
    // Add new
    newOverridesNames.forEach((itemName) => {
      if (!selectedItems.includes(itemName)) {
        updatedOverrides = updatedOverrides.push(getDefaultOverrides(type, itemName));
      }
    });
    return onChange(["overrides"], updatedOverrides);
  };

  const onChangePrice = (overrideName, prices) => {
    const index = overrides.findIndex((override) => override.get("key", "") === overrideName && override.get("type", "") === type);
    if (index !== -1) {
      const updatedOverrides = overrides.setIn([index, "value", "price"], prices);
      return onChange(["overrides"], updatedOverrides);
    }
  };

  const onTariffAdd = (name) => {
    const prices = overridesNamesOfType.get(name, Immutable.List());
    if (prices.isEmpty()) {
      const newPrices = prices.push(defaultTariff);
      return onChangePrice(name, newPrices);
    }
    const newPrices = prices.update(prices.size - 1, Immutable.Map(), (item) => item.set("to", "")).push(defaultTariff.set("from", ""));
    return onChangePrice(name, newPrices);
  };

  const onTariffRemove = (name) => {
    const prices = overridesNamesOfType.get(name, Immutable.List());
    const size = prices.size;
    if (size < 2) {
      const newPrices = Immutable.List([defaultTariff]);
      return onChangePrice(name, newPrices);
    }
    const newPrices = prices.setIn([size - 2, "to"], "UNLIMITED").pop();
    return onChangePrice(name, newPrices);
  };

  const onPriceUpdate = (name, index, value) => {
    const newValue = isNumber(value) ? parseFloat(value) : value;
    const newPrices = overridesNamesOfType.get(name, Immutable.List()).setIn([index, "price"], newValue);
    return onChangePrice(name, newPrices);
  };

  const onCycleUpdate = (name, index, value) => {
    const newValue = isNumber(value) ? parseFloat(value) : value;
    const prices = overridesNamesOfType.get(name, Immutable.List());
    const newPrices = reCalculateCycles(prices, index, newValue, unlimitedValue);
    return onChangePrice(name, newPrices);
  };

  const getLabel = (name) => {
    const o = options.find((option) => option.value === name);
    return (typeof o === "undefined") ? name : (<span>{o.label} <small>({name})</small></span>);
  };

  const getRecurringPrices = (name) => {
    const onOverridePriceUpdate = (index, value) => onPriceUpdate(name, index, value);
    const onOverrideCycleUpdate = (index, value) => onCycleUpdate(name, index, value);
    const onOverrideTariffRemove = () => onTariffRemove(name);
    const count = overridesNamesOfType.get(name, Immutable.List()).size;
    return overridesNamesOfType
      .get(name, Immutable.List())
      .map((price, i) => (
        <PlanPrice
          key={`subscription-${type}-price-override-${name}-${i}`}
          index={i}
          count={count}
          item={price}
          mode={editable ? "create" : "view"}
          isTrialExist={false}
          onPlanPriceUpdate={onOverridePriceUpdate}
          onPlanCycleUpdate={onOverrideCycleUpdate}
          onPlanTariffRemove={onOverrideTariffRemove}
        />
      ));
  };

  return (
    <Form className="form-horizontal">
      <Panel header={<h3>{getFieldName("subscriber_price_override_panel_title", type)}</h3>}>
        <FormGroup>
          <Col as={ControlLabel} sm={3} lg={2}>
            {getFieldName("subscriber_price_override_panel_select", type)}:
          </Col>
          <Col sm={8} lg={9}>
            <Field fieldType='select' multi={true} options={options} value={selectedItems.join(",")} onChange={onChangeSelect} clearable={false} editable={editable} />
          </Col>
        </FormGroup>
        <FormGroup>
          {!selectedItems.isEmpty() && selectedItems.map((name) => {
              const selectedItem = options.find((options) => options.value === name);
              const onItemTariffAdd = () => onTariffAdd(name);
              return (
                <Col sm={12} key={`subscription-${type}-price-override-${name}`}>
                  <Panel header={<h3>{getLabel(name)}</h3>}>
                    {getRecurringPrices(name)}
                    {!!selectedItem?.isByCycles && editable && (
                      <CreateButton onClick={onItemTariffAdd} label='Add New' />
                    )}
                  </Panel>
                </Col>
              );
            })}
        </FormGroup>
      </Panel>
    </Form>
  );
};

OverridePrice.propTypes = {
  type: PropTypes.string.isRequired,
  overrides: PropTypes.instanceOf(Immutable.List),
  options: PropTypes.array,
  onChange: PropTypes.func.isRequired,
  editable: PropTypes.bool,
};

export default OverridePrice;