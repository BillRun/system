import React from 'react';
import PropTypes from 'prop-types';
import { Form, FormGroup, Col, ControlLabel, HelpBlock } from 'react-bootstrap';
import Immutable from 'immutable';
import Field from '@/components/Field';


const PlayForm = ({
  item,
  isNameHasError,
  isAllowedDisableAction,
  isAllowedEditName,
  isAllowedEditDefault,
  onChangeName,
  onChangeLabel,
  onChangeDefault,
  onChangeEnabled,
}) => (
  <Form horizontal>
    {isAllowedEditName && (
      <FormGroup validationState={isNameHasError ? 'error' : null} >
        <Col componentClass={ControlLabel} sm={3}>
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
      <Col componentClass={ControlLabel} sm={3}>
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
        <Col sm={7} smOffset={3}>
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
      <Col sm={7} smOffset={3}>
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

PlayForm.defaultProps = {
  item: Immutable.Map(),
  isNameHasError: false,
  isAllowedDisableAction: true,
  isAllowedEditName: true,
  isAllowedEditDefault: true,
};

export default PlayForm;
