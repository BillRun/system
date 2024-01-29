import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, Panel } from 'react-bootstrap';
import Field from '@/components/Field';


const Allowances = ({ data, onChange }) => {

  const onChangeValue = (key, value) => {
    onChange('billrun', ['allowances', key], value);
  }

  const onToggleAllowances = (e) => {
    const { value } = e.target;
    onChangeValue('enabled', value);
    if (value === false) {
      onChangeValue('included_in_allowance', false);
      onChangeValue('taxable_paid_first', false);
    }
  }

  const onToggleIncludedInAllowance = (e) => {
    const { value } = e.target;
    onChangeValue('included_in_allowance', value);
  }

  const onToggleTaxablePaidFirst = (e) => {
    const { value } = e.target;
    onChangeValue('taxable_paid_first', value);
  }

  const isEnabled = data.getIn(['allowances', 'enabled'], '') === true;

  return (
    <Panel header="Allowances">
      <Form horizontal>
        <FormGroup>
          <Col sm={10} smOffset={2} className="mt10">
            <Field
              fieldType="checkbox"
              label="Enable allowances"
              value={data.getIn(['allowances', 'enabled'], '')}
              onChange={onToggleAllowances}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={10} smOffset={2} className="mt10">
            <Field
              fieldType="checkbox"
              label="Tax included in allowances"
              value={data.getIn(['allowances', 'included_in_allowance'], '')}
              onChange={onToggleIncludedInAllowance}
              disabled={!isEnabled}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={10} smOffset={2} className="mt10">
            <Field
              fieldType="checkbox"
              label="Taxable amounts paid first"
              value={data.getIn(['allowances', 'taxable_paid_first'], '')}
              onChange={onToggleTaxablePaidFirst}
              disabled={!isEnabled}
            />
          </Col>
        </FormGroup>
      </Form>
    </Panel>
  );
}

Allowances.defaultProps = {
  data: Immutable.Map(),
};

Allowances.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func.isRequired,
};

export default Allowances;
