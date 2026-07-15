import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import Field from '@/components/Field';
import Help from '@/components/Help';
import ProductSearchByUsagetype from '@/components/Plan/components/ProductSearchByUsagetype';
import { GroupsInclude } from '@/language/FieldDescriptions';
import {
  getFieldName,
} from '@/common/Util';
import {
  validateKey,
} from '@/common/Validators';

const ServiceCountersForm = ({item = Immutable.Map(), mode = 'create', usages = Immutable.List(['cost']), errors = Immutable.Map(), updateField, existingGroupsNames = [], setError}) => {

  // because redux fore convert all Immutable to JS
  existingGroupsNames = Immutable.List(existingGroupsNames);
  const rates = item.get('rates', '');
  const allProductsBased = rates === 'ALL_RATES';
  const selectProductsBased = Immutable.List.isList(rates);
  const regexpProductsBased = typeof rates === 'string' && rates !== 'ALL_RATES';
  
  const onChangeName = (e) => {
    const { value } = e.target;
    const name = value.toUpperCase();

    setError('group_key');
    if (name === '') {
      setError('group_key', 'Group name is required');
    }
    if (existingGroupsNames.includes(name)) {
      setError('group_key', 'Group name already exists');
    }
    if (!validateKey(name)) {
      setError('group_key', 'Group name contains illegal characters, name should contain only alphabets, numbers and underscores (A-Z, 0-9, _)');
    }
    updateField('group_key', name);
  }
  const onChangeShared = (e) => {
    const { value } = e.target;
    updateField('account_shared', value);
  }

  // const onChangePool = (e) => {
  //   const { value } = e.target;
  //   updateField('account_pool', value);
  // }

  // const onChangeQuantity = (e) => {
  //   const { value } = e.target;
  //   updateField('quantity_affected', value);
  // }

  const onChangeRegexp = (e) => {
    const { value } = e.target;
    setError('rates_regex');
    if (value === '') {
      setError('rates_regex', 'Regex of product key is required');
    }
    updateField('rates', value);
  }

  const onChangeGroupRates = (productKey) => {
    setError('rates_select');
    if (productKey.isEmpty()) {
      setError('rates_select', 'At least one product is required');
    }
    updateField('rates', productKey);
  }

  const onChangeProductType = (e) => {
    const { value } = e.target;
    setError('rates_regex');
    setError('rates_select');
    switch (value) {
      case 'all':
        return updateField('rates', 'ALL_RATES');
      case 'select':
        return updateField('rates', Immutable.List());
      case 'regex':
        return updateField('rates', '');
      default:
        return;
    }
  }

  return (
    <Form className="form-horizontal pt10">

      {mode === 'create' && (
        <FormGroup key="group_name" validationState={errors.has('group_key') ? 'error' : null} className="mb10">
          <Col sm={3} as={ControlLabel}>
            {getFieldName('counter_group_name', "service")}
            <Help contents={GroupsInclude.name} />
          </Col>
          <Col sm={8}>
            <Field
              value={item.get('group_key',false)}
              onChange={onChangeName}
              disabled={mode === 'view'}
              className="mt5"
            />
            { errors.has('group_key') && <HelpBlock>{errors.get('group_key', '')}</HelpBlock>}
          </Col>
        </FormGroup>
      )}

      <FormGroup className="mb10">
        <Col sm={3} as={ControlLabel}></Col>
        <Col sm={8}>
            <Field
              fieldType="checkbox"
              value={item.get('account_shared',false)}
              onChange={onChangeShared}
              disabled={mode === 'view'}
              className="mt5 inline"
              label={getFieldName('account_shared', "service")}
            />
            <Help contents={GroupsInclude.shared_desc} />
          </Col>
      </FormGroup>

      {/* <FormGroup className="mb0">
        <Col sm={3} as={ControlLabel}></Col>
        <Col sm={8}>
            <Field
              fieldType="checkbox"
              value={item.get('account_pool',false)}
              onChange={onChangePool}
              disabled={mode === 'view'}
              className="mt5 inline"
              label={getFieldName('account_pool', "service")}
            />
            <Help contents={GroupsInclude.pooled_desc} />
          </Col>
      </FormGroup> */}

      {/* <FormGroup className="mb0">
        <Col sm={3} as={ControlLabel}></Col>
        <Col sm={8}>
            <Field
              fieldType="checkbox"
              value={item.get('quantity_affected',false)}
              onChange={onChangeQuantity}
              disabled={mode === 'view'}
              className="mt5 inline"
              label={getFieldName('quantity_affected', "service")}
            />
            <Help contents={GroupsInclude.quantityAffected_desc} />
          </Col>
      </FormGroup> */}

      <FormGroup className="mb10">
        <Col sm={3} as={ControlLabel}>
            {getFieldName('counter_group_rates', "service")}
        </Col>
        <Col sm={8}>
          <Field
            fieldType="radio"
            value="all"
            onChange={onChangeProductType}
            checked={allProductsBased}
            label={getFieldName('counter_group_all_rates', "service")}
            className="inline mr10"
          />
          <Field
            fieldType="radio"
            value="select"
            checked={selectProductsBased}
            onChange={onChangeProductType}
            label={getFieldName('counter_group_selected_rates', "service")}
            className="inline mr10"
          />
          <Field
            fieldType="radio"
            value="regex"
            checked={regexpProductsBased}
            onChange={onChangeProductType}
            label={getFieldName('counter_group_regex_rates', "service")}
            className="inline"
          />
        </Col>
      </FormGroup>

      { regexpProductsBased && (
        <FormGroup key="rates_regex" validationState={errors.has('rates_regex') ? 'error' : null} className="mb10">
          <Col sm={3} as={ControlLabel}></Col>
          <Col sm={8}>
            <Field value={rates} onChange={onChangeRegexp} placeholder={getFieldName('counter_group_regex_rates_placeholder', "service")}/>
            { errors.has('rates_regex') && <HelpBlock>{errors.get('rates_regex', '')}</HelpBlock>}
          </Col>
        </FormGroup>
      )}

      { selectProductsBased && (
        <FormGroup key="rates_select" validationState={errors.has('rates_select') ? 'error' : null} className="mb10">
          <Col sm={3} as={ControlLabel}></Col>
          <Col sm={8}>
            <div>
              <ProductSearchByUsagetype
                products={rates}
                usages={usages}
                onChangeGroupRates={onChangeGroupRates}
                />
            </div>
           { errors.has('rates_select') && <HelpBlock>{errors.get('rates_select', '')}</HelpBlock>}
          </Col>
        </FormGroup>
      )}

    </Form>
  );
}

ServiceCountersForm.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  existingGroupsNames: PropTypes.array, // because redux fore convert all Immutable to JS
  mode: PropTypes.string,
  usages: PropTypes.instanceOf(Immutable.List),
};

export default ServiceCountersForm;
