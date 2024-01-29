import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import Csi from './Tax/Csi';


const Tax = ({ data, csiOptions, taxRateOptions, onChange }) => {

  const isCSI = data.get('tax_type', '') === 'CSI';

  const onChangeTaxType = (e) => {
    const { value } = e.target;
    onChange('taxation', 'tax_type', value);
  }

  const onChangeCsi = (csi) => {
    onChange('taxation', 'CSI', csi);
  }

  const onChangeDefaultTaxRate = (key) => {
    onChange('taxation', ['default', 'key'], key);
  }

  const taxRateSelectOptions = taxRateOptions
    .map(option => ({label: option.get('description', ''), value: option.get('key', '')}))
    .toArray();

  return (
    <div className="tax">
      <Form horizontal>
        <FormGroup controlId="tax_type">
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Tax Type
          </Col>
          <Col sm={8} lg={9}>
            <span style={{ display: 'inline-block', marginRight: 20 }}>
              <Field fieldType="radio" onChange={onChangeTaxType} name="tax_type" value="usage" label="Custom" checked={!isCSI} />
            </span>
            <span style={{ display: 'inline-block' }}>
                <Field fieldType="radio" onChange={onChangeTaxType} name="tax_type" value="CSI" label="CSI" checked={isCSI} />
            </span>
          </Col>
        </FormGroup>
        <hr />
        {isCSI && (
          <Csi
            csi={data.get('CSI', Immutable.Map())}
            onChange={onChangeCsi}
            fileTypes={csiOptions}
          />
        )}
        {!isCSI && (
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3} lg={2}>
              Default Tax Rate
            </Col>
            <Col sm={8} lg={9}>
              <Field
                fieldType="select"
                value={data.getIn(['default', 'key'], '')}
                onChange={onChangeDefaultTaxRate}
                options={taxRateSelectOptions}
              />
            </Col>
          </FormGroup>
        )}
      </Form>
    </div>
  );
};

Tax.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  csiOptions: PropTypes.instanceOf(Immutable.Iterable),
  taxRateOptions: PropTypes.instanceOf(Immutable.Iterable),
  onChange: PropTypes.func.isRequired,
};

Tax.defaultProps = {
  data: Immutable.Map(),
  csiOptions: Immutable.List(),
  taxRateOptions: Immutable.List(),
};

export default Tax;
