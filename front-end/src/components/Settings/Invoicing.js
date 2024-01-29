import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import { SettingsDescription } from '../../language/FieldDescriptions';


const Invoicing = ({ data, chargingDayOptions, onChange }) => {

  const onChangeValue = (key, value) => {
    onChange('billrun', key, value);
  }

  const onChangeChargingDay = (value) => {
    onChangeValue('charging_day', value);
  }

  const onToggleDetailedInvoices = (e) => {
    const { value } = e.target;
    onChangeValue('detailed_invoices', value);
  }

  const onToggleEmailAfterConfirmation = (e) => {
    const { value } = e.target;
    onChangeValue('email_after_confirmation', value);
  }

  const onToggleGeneratePdf = (e) => {
    const { value } = e.target;
    onChangeValue('generate_pdf', value);
    if (value === false) {
      onChangeValue('email_after_confirmation', false);
      onChangeValue('detailed_invoices', false);
    }
  }

  return (
    <div className="Invoicing">
      <Form horizontal>
        <FormGroup controlId="charging_day" key="charging_day">
          <Col componentClass={ControlLabel} sm={2}>
            Charging Day
          </Col>
          <Col sm={6}>
            <Field
              fieldType="select"
              value={data.get('charging_day', '')}
              onChange={onChangeChargingDay}
              options={chargingDayOptions}
              placeholder="Select charging day..."
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={10} smOffset={2} className="mt10">
            <Field
              fieldType="checkbox"
              label="Billing cycle generates PDF invoices"
              value={data.get('generate_pdf', true)}
              onChange={onToggleGeneratePdf}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={10} smOffset={2} className="mt10">
            <Field
              fieldType="checkbox"
              label="Detailed Invoices"
              value={data.get('detailed_invoices', false)}
              onChange={onToggleDetailedInvoices}
              disabled={!data.get('generate_pdf', true)}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={10} smOffset={2} className="mt10">
            <Field
              fieldType="checkbox"
              label="Send invoices to customers by email"
              value={data.get('email_after_confirmation', false)}
              onChange={onToggleEmailAfterConfirmation}
              disabled={!data.get('generate_pdf', true)}
            />
          <HelpBlock style={{ marginLeft: 20, marginTop: 0 }}>
            {SettingsDescription.email_after_confirmation}
          </HelpBlock>
          </Col>
        </FormGroup>
      </Form>
    </div>
  );
}

Invoicing.defaultProps = {
  data: Immutable.Map(),
  chargingDayOptions: [...Array(28)].map((_, i) => ({value: i + 1, label: i + 1}))
};

Invoicing.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  chargingDayOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.number,
      label: PropTypes.oneOfType([
        PropTypes.string,
        PropTypes.number,
      ]),
    }),
  ),
  onChange: PropTypes.func.isRequired,
};

export default Invoicing;
