import React, { useState } from 'react';
import PropTypes from 'prop-types';
import classNames from 'classnames';
import { Form, FormControl, FormGroup, Col, ControlLabel, HelpBlock, Button } from 'react-bootstrap';
import { ModalWrapper } from '@/components/Elements';


const ResetPassword = ({ show, sending, onCancel, updateSending, onResetPass }) => {

  const [email, setEmail] = useState('');
  const [error, setError] = useState('');

  const onChangeEmail = (e) => {
    setEmail(e.target.value);
    setError('');
  }

  const onSubmit = () => {
    if (email === '') {
      setError('Email is required')
      return false;
    }
    updateSending(true);
    onResetPass(email);
  }
  const label = sending ? 'Sending' : 'Reset';

  const actionClass = classNames('fa', {
    'fa-envelope-o': !sending,
    'fa-spinner fa-pulse': sending,
  });

  return (
    <ModalWrapper title={'Reset Password'} show={show} closeButton onHide={onCancel}>
      <Form horizontal>
        <FormGroup validationState={error !== '' ? 'error' : null}>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Email
          </Col>
          <Col sm={8} lg={9}>
            <FormControl type="text" name="mail" onChange={onChangeEmail} value={email} disabled={sending} />
            <HelpBlock>The email you registered as username</HelpBlock>
            {error !== '' && (
              <HelpBlock>{error}</HelpBlock>
            )}
          </Col>
        </FormGroup>
        <Button bsStyle="success" bsSize="lg" block onClick={onSubmit} disabled={sending}>
          <i className={actionClass} />&nbsp;
          {label}
        </Button>
      </Form>
    </ModalWrapper>
  );
}


ResetPassword.defaultProps = {
  show: true,
  sending: false,
};

ResetPassword.propTypes = {
  show: PropTypes.bool,
  sending: PropTypes.bool,
  onCancel: PropTypes.func.isRequired,
  updateSending: PropTypes.func.isRequired,
  onResetPass: PropTypes.func.isRequired,
};

export default ResetPassword;
