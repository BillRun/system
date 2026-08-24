import React from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable'; // eslint-disable-line no-unused-vars
import { Col, Row } from 'react-bootstrap';
import { Actions, DragHandle } from '../Elements';
import { EntityField } from '../Entity';

const CustomFieldsListRow = ({
  field = Map(), entity = '', actions = [], fieldTypeLabel = 'Text', isSortable = true, isReordable = true,
}) => (
  <Row className={`CustomFieldsListRow ${entity} table-row withHover`}>
    <Col sm={1} className="text-center">
      { isReordable && (
        <DragHandle disabled={!isSortable} />
      )}
    </Col>

    <Col sm={2} style={{ wordBreak: 'break-all' }}>
      {field.get('field_name', '')}
    </Col>

    <Col sm={3}>
      {field.get('title', '')}
    </Col>

    <Col sm={1}>
      {fieldTypeLabel}
    </Col>

    <Col sm={3}>
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
      {!isReordable && (
        <Actions actions={actions} data={field} />
      )}
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

export default CustomFieldsListRow;
