import React from 'react';
import PropTypes from 'prop-types';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Immutable from 'immutable';
import Field from '@/components/Field';
import { EntityFields } from '@/components/Entity';


const PluginForm = ({
  item,
  onChangeEnabled,
  errors,
  onChange,
  onRemove,
}) => (
  <Form horizontal>

    <FormGroup>
      <Col sm={3} lg={2} componentClass={ControlLabel}>Status</Col>
      <Col sm={8} lg={9}>
        <span>
          <span className="inline mr10">
            <Field fieldType="radio" onChange={onChangeEnabled} name="type" value="yes" label="Enable" checked={item.get('enabled', false)} />
          </span>
          <span className="inline">
            <Field fieldType="radio" onChange={onChangeEnabled} name="type" value="no" label="Disable" checked={!item.get('enabled', false)} />
          </span>
        </span>
      </Col>
    </FormGroup>

    <EntityFields
      entityName="plugins"
      entity={item.getIn(['configuration', 'values'], Immutable.Map())}
      errors={errors}
      fields={item.getIn(['configuration', 'fields'], Immutable.List())}
      onChangeField={onChange}
      onRemoveField={onRemove}
    />

  </Form>
);

PluginForm.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  onChangeEnabled: PropTypes.func.isRequired,
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};

PluginForm.defaultProps = {
  item: Immutable.Map(),
};

export default PluginForm;
