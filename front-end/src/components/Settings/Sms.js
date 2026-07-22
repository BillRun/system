import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, Col, Button, InputGroup } from 'react-bootstrap';
import {
  ControlLabel,
  FormGroup,
  InputGroupButton,
} from '@/common/BootstrapCompat';
import Field from '@/components/Field';

const Sms = ({
  data = Immutable.Map(),
  onChange,
  onSendTestSms,
  isChanged = false,
}) => {
  const [recipient, setRecipient] = useState('');
  const [isConfigValid, setIsConfigValid] = useState(true);

  const onChangeConfig = (value) => {
    if (value === false) {
      setIsConfigValid(false);
      return;
    }

    setIsConfigValid(true);
    onChange('smser', [], Immutable.fromJS(value));
  };

  const onChangeRecipient = (event) => {
    setRecipient(event.target.value);
  };

  const onClickSendTestSms = () => {
    onSendTestSms(recipient);
  };

  const isSendDisabled = (
    isChanged
    || !isConfigValid
    || recipient.trim() === ''
  );

  return (
    <div className="Sms">
      <Form className="form-horizontal">
        <FormGroup controlId="smsConfiguration">
          <Col as={ControlLabel} md={2}>
            SMS Configuration
          </Col>

          <Col sm={6}>
            <Field
              fieldType="json"
              value={data}
              onChange={onChangeConfig}
            />
          </Col>
        </FormGroup>

        <hr />

        <FormGroup controlId="smsRecipient">
          <Col as={ControlLabel} md={2}>
            Test Phone Number
          </Col>

          <Col sm={6}>
            <InputGroup>
              <Field
                name="recipient"
                value={recipient}
                onChange={onChangeRecipient}
              />

              <InputGroupButton>
                <Button
                  type="button"
                  onClick={onClickSendTestSms}
                  disabled={isSendDisabled}
                >
                  Send Test SMS
                </Button>
              </InputGroupButton>
            </InputGroup>
          </Col>
        </FormGroup>
      </Form>
    </div>
  );
};

Sms.propTypes = {
  data: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func.isRequired,
  onSendTestSms: PropTypes.func.isRequired,
  isChanged: PropTypes.bool,
};

export default Sms;