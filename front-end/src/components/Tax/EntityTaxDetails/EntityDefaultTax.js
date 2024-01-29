import React, { useCallback, useMemo } from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable';
import { sentenceCase, titleCase } from 'change-case';
import { FormGroup, ControlLabel, Col } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  getFieldName,
  getFieldNameType,
  parseConfigSelectOptions,
  getConfig,
} from '@/common/Util';


const EntityDefaultTax = ({tax, disabled, itemName, typeOptions, taxRateOptions, onUpdate}) => {

  const onChengeType = useCallback((value) => {
    onUpdate(['custom_tax'], value)
  }, [onUpdate]);

  const onChengeTaxation = useCallback((e) => {
    const { value } = e.target;
    // if Taxation set to custom and custom_logic is not set, set the default
    if (value === 'custom' && tax.get('custom_logic', '') === '') {
      onUpdate(['custom_logic'], 'override');
    }
    onUpdate(['taxation'], value);
  }, [onUpdate, tax]);

  const onChengeCustomLogic = useCallback((e) => {
    const { value } = e.target;
    onUpdate(['custom_logic'], value);
  }, [onUpdate]);

  const typeSelecOptions = useMemo(() => typeOptions
    .map(parseConfigSelectOptions)
    .toArray()
  ,[typeOptions]);

  const taxRateSelectOptions = useMemo(() => taxRateOptions
    .map(option => ({label: option.get('description', ''), value: option.get('key', '')}))
    .toArray()
  ,[taxRateOptions]);

  const entityLabel = useMemo(() => getConfig(['systemItems', itemName, 'itemName'], ''), [itemName]);

  return (
    <>
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3} lg={2}>
          { getFieldName('type', getFieldNameType(itemName), sentenceCase('type'))}
        </Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            value={tax.get('type', '')}
            onChange={onChengeType}
            options={typeSelecOptions}
            disabled={disabled}
            editable={false}
          />
        </Col>
      </FormGroup>

      <FormGroup>
        <Col componentClass={ControlLabel} sm={3} lg={2}>

        </Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="radio"
            onChange={onChengeTaxation}
            checked={tax.get('taxation', '') === 'no'}
            value="no"
            label={`${titleCase(entityLabel)} isn't subject to taxation`}
            disabled={disabled}
          />
          <Field
            fieldType="radio"
            onChange={onChengeTaxation}
            checked={tax.get('taxation', '') === 'global'}
            value="global"
            label="Use global mapping rules"
            disabled={disabled}
          />

          <div className="clearfix">
            <Field
              fieldType="radio"
              onChange={onChengeTaxation}
              checked={tax.get('taxation', '') === 'custom'}
              value="custom"
              label={`${titleCase(entityLabel)} implies a specific tax rate${tax.get('taxation', '') === 'custom' || tax.get('custom_tax', '') !== '' ? ': ' : ''}`}
              className="pull-left mr5"
              disabled={disabled}
            />
            <Field
              fieldType="select"
              value={tax.get('custom_tax', '')}
              onChange={onChengeType}
              options={taxRateSelectOptions}
              editable={tax.get('taxation', '') === 'custom' && !disabled}
            />
          </div>

          {tax.get('taxation', '') === 'custom' && (
            <FormGroup className="mb0">
              <Col smOffset={2} xsOffset={1}>
                <Field
                  fieldType="radio"
                  onChange={onChengeCustomLogic}
                  checked={tax.get('custom_logic', '') === 'override'}
                  value="override"
                  label="Override global mapping rules"
                  disabled={disabled}
                />
                <Field
                  fieldType="radio"
                  onChange={onChengeCustomLogic}
                  checked={tax.get('custom_logic', '') === 'fallback'}
                  value="fallback"
                  label={`Apply if tax rate not found via global mapping rules`}
                  disabled={disabled}
                />
              </Col>
            </FormGroup>
          )}

          <Field
            fieldType="radio"
            onChange={onChengeTaxation}
            checked={tax.get('taxation', '') === 'default'}
            value="default"
            label={`Use default tax rate`}
            disabled={disabled}
          />
        </Col>
      </FormGroup>
    </>
  );
}

EntityDefaultTax.propTypes = {
  tax: PropTypes.instanceOf(Map),
  disabled: PropTypes.bool,
  itemName: PropTypes.string,
  typeOptions: PropTypes.instanceOf(List),
  taxRateOptions: PropTypes.instanceOf(List),
  onUpdate: PropTypes.func.isRequired,
}

EntityDefaultTax.defaultProps = {
  tax: Map(),
  disabled: false,
  itemName: '',
  typeOptions: List([Map({ id:'vat', title: 'Vat'})]),
  taxRateOptions: List(),
};

export default EntityDefaultTax;
