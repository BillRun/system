import React from 'react';
import PropTypes from 'prop-types';
import { Form, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup } from '@/common/BootstrapCompat';
import Field from '@/components/Field';


const Currency = (props) => {
  const { data, currencies } = props;
  const onChangeCurrency = (value) => {
    props.onChange('pricing', 'currency', value);
  };
  return (
    <div className="CurrencyTax">
    <Form className="form-horizontal">
        <FormGroup controlId="currency" key="currency">
          <Col as={ControlLabel} md={2}>
            Currency
          </Col>
          <Col sm={6}>
            <Field
              fieldType="select"
              options={currencies}
              value={data}
              onChange={onChangeCurrency}
            />
          </Col>
        </FormGroup>
      </Form>
    </div>
  );
};

Currency.propTypes = {
  onChange: PropTypes.func.isRequired,
  data: PropTypes.string,
  currencies: PropTypes.arrayOf(PropTypes.object),
};


export default Currency;
