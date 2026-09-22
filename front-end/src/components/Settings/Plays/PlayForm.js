import React from 'react';
import PropTypes from 'prop-types';
import { Form, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import Immutable from 'immutable';
import Field from '@/components/Field';

const PlayForm = ({
  item = Immutable.Map(),
  isNameHasError = false,
  isAllowedDisableAction = true,
  isAllowedEditName = true,
  isAllowedEditDefault = true,
  onChangeName,
  onChangeLabel,
  onChangeDefault,
  onChangeEnabled,
}) => (
    <Form className="form-horizontal">
    {isAllowedEditName && (
      <FormGroup validationState={isNameHasError ? 'error' : null} >
        <Col as={ControlLabel} sm={3}>
          Name <span className="danger-red"> *</span>
        </Col>
        <Col sm={7}>
          <Field
            onChange={onChangeName}
            value={item.get('name', '')}
          />
          { isNameHasError && (
            <HelpBlock>{isNameHasError}</HelpBlock>
          )}
        </Col>
      </FormGroup>
    )}
    <FormGroup>
      <Col as={ControlLabel} sm={3}>
        Description
      </Col>
      <Col sm={7}>
        <Field
          onChange={onChangeLabel}
          value={item.get('label', '')}
        />
      </Col>
    </FormGroup>
    {isAllowedEditDefault && (
      <FormGroup>
        <Col sm={7} className="col-sm-offset-3" >
          <Field
            fieldType="checkbox"
            label="Default"
            value={item.get('default', false)}
            onChange={onChangeDefault}
          />
        </Col>
      </FormGroup>
    )}
    <FormGroup>
      <Col sm={7} className="col-sm-offset-3" >
        <Field
          fieldType="checkbox"
          label="Enabled"
          value={item.get('enabled', false)}
          onChange={onChangeEnabled}
          disabled={!isAllowedDisableAction}
        />
      </Col>
    </FormGroup>
  </Form>
);

PlayForm.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  isNameHasError: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
  isAllowedDisableAction: PropTypes.bool,
  isAllowedEditName: PropTypes.bool,
  isAllowedEditDefault: PropTypes.bool,
  onChangeName: PropTypes.func.isRequired,
  onChangeLabel: PropTypes.func.isRequired,
  onChangeDefault: PropTypes.func.isRequired,
  onChangeEnabled: PropTypes.func.isRequired,
};

export default PlayForm;
