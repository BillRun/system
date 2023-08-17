import React from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable'; // eslint-disable-line no-unused-vars
import { Col, Row, ControlLabel } from 'react-bootstrap';
import { Actions, DragHandle } from '../Elements';
import { EntityField } from '../Entity';


const CustomFieldsListRow = ({
  field, entity, actions, fieldTypeLabel, isSortable, isReordable,
}) => (
  <Row className={`CustomFieldsListRow ${entity} table-row withHover`}>
    <Col sm={1} className="text-center">
      { isReordable && (
        <DragHandle disabled={!isSortable} />
      )}
    </Col>

    <Col sm={2} style={{ wordBreak: 'break-all' }}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Field Name:&nbsp;</ControlLabel>
      </Col>
      {field.get('field_name', '')}
    </Col>

    <Col sm={3}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Title:&nbsp;</ControlLabel>
      </Col>
      {field.get('title', '')}
    </Col>

    <Col sm={1}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Type:&nbsp;</ControlLabel>
      </Col>
      {fieldTypeLabel}
    </Col>

    <Col sm={3}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Default Value:&nbsp;</ControlLabel>
      </Col>
      <span className="text-center">
        <EntityField
          field={field.set('field_name', 'default_value')}
          entity={Map({ default_value: field.get('default_value') })}
          onlyInput={true}
          editable={false}
        />
      </span>
    </Col>
    <Col sm={2} className="actions">
      <Actions actions={actions} data={field} />
    </Col>
    <Col sm={12}>
      <hr style={{ marginTop: 0, marginBottom: 0 }} />
    </Col>
  </Row>
);


CustomFieldsListRow.propTypes = {
  field: PropTypes.instanceOf(Map),
  fieldType: PropTypes.string,
  fieldTypeLabel: PropTypes.string,
  entity: PropTypes.string,
  actions: PropTypes.array,
  isSortable: PropTypes.bool,
  isReordable: PropTypes.bool,
};


CustomFieldsListRow.defaultProps = {
  field: Map(),
  fieldType: 'text',
  fieldTypeLabel: 'Text',
  entity: '',
  actions: [],
  isSortable: true,
  isEditable: true,
  isRemoveable: true,
  isReordable: true,
};


export default CustomFieldsListRow;
