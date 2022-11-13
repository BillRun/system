import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Map } from 'immutable';
import { Panel, Col, FormGroup, Form, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import { Actions } from '@/components/Elements';

const ChargingPlanInclude = (props) => {
  const { include, index, editable } = props;

  const onUpdateField = (e) => {
    const { id, value } = e.target;
    props.onUpdateField(index, id, value);
  };

  const onUpdateOperation = (value) => {
    props.onUpdateField(index, 'operation', value);
  };

  const onUpdatePeriodField = (e) => {
    const { id, value } = e.target;
    props.onUpdatePeriodField(index, id, value);
  };

  const onSelectPeriodUnit = (value) => {
    props.onUpdatePeriodField(index, 'unit', value);
  };

  const unitOptions = [
    { value: 'days', label: 'Days' },
    { value: 'months', label: 'Months' },
  ];

  const onRemoveClick = () => {
    props.onRemove(index);
  };

  const actions = [
    { type: 'remove', showIcon: true, onClick: onRemoveClick },
  ];

  const operationOptions = [
    { value: 'default', label: 'Default' },
    { value: 'new', label: 'New' },
    { value: 'inc', label: 'Increment' },
    { value: 'set', label: 'Set' },
  ];

  const header = (
    <div>
      { include.get('pp_includes_name', '') }
      <div className="pull-right" style={{ marginTop: -5 }}>
        <Actions actions={actions} />
      </div>
    </div>
  );

  return (
    <div className="ChargingPlanInclude">
      <Form horizontal>
        <Panel header={header}>

          <FormGroup>
            <Col componentClass={ControlLabel} md={2}> Operation </Col>
            <Col sm={4}>
              <Field
                fieldType="select"
                options={operationOptions}
                value={include.get('operation', '')}
                onChange={onUpdateOperation}
                editable={editable}
              />
            </Col>
          </FormGroup>

          <FormGroup>
            <Col componentClass={ControlLabel} md={2}>{include.get('unit_label', 'Value')}</Col>
            <Col md={9}>
              <Field id="usagev" value={include.get('usagev', 0)} onChange={onUpdateField} fieldType="number" editable={props.editable} />
            </Col>
          </FormGroup>

          <FormGroup>
            <Col componentClass={ControlLabel} md={2}> Duration </Col>
            <Col md={9}>
              <Col md={6} style={{ paddingLeft: 0 }}>
                <Field
                  fieldType="number"
                  id="duration"
                  value={include.getIn(['period', 'duration'], 0)}
                  onChange={onUpdatePeriodField}
                  editable={props.editable}
                />
              { !editable && (
                <div className="non-editable-field">{include.getIn(['period', 'unit'], '')}</div>
              )}
              </Col>
              <Col md={6}>
                <Field
                  fieldType="select"
                  options={unitOptions}
                  value={include.getIn(['period', 'unit'], '')}
                  onChange={onSelectPeriodUnit}
                  editable={editable}
                />
              </Col>
            </Col>
          </FormGroup>

        </Panel>
      </Form>
    </div>
  );
};

ChargingPlanInclude.defaultProps = {
  include: Map(),
  index: 0,
  editable: true,
};

ChargingPlanInclude.propTypes = {
  include: PropTypes.instanceOf(Map),
  index: PropTypes.number,
  onUpdatePeriodField: PropTypes.func.isRequired,
  onUpdateField: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  editable: PropTypes.bool,
};

export default connect()(ChargingPlanInclude);
