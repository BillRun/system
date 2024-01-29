import React from 'react';
import PropTypes from 'prop-types';
import { List, Map } from 'immutable';
import { Col, ControlLabel } from 'react-bootstrap';
import CustomFieldsListUsageRow from './CustomFieldsListUsageRow';
import { Actions } from '../Elements';


const CustomFieldsListUsage = ({ fields, addAction, rowActions, entitiesFieldsConfig }) => (
  <div className="CustomFieldsList">
    <Col sm={12} xsHidden className="table-row row">
      <Col sm={2}><ControlLabel>Title</ControlLabel></Col>
      <Col sm={2}><ControlLabel>Entity</ControlLabel></Col>
      <Col sm={2}><ControlLabel>Entity Field</ControlLabel></Col>
      <Col sm={2} className="text-center"><ControlLabel>Translate</ControlLabel></Col>
      <Col sm={2} className="text-center"><ControlLabel>Condition</ControlLabel></Col>
      <Col sm={2}>&nbsp;</Col>
    </Col>
    <Col sm={12} xsHidden>
      <hr style={{ marginTop: 5, marginBottom: 15 }} />
    </Col>
    {!fields.isEmpty() && fields.map((field, idx) => (
      <CustomFieldsListUsageRow
        key={idx}
        field={field}
        actions={rowActions}
        entitiesFieldsConfig={entitiesFieldsConfig}
      />
    ))}
    {fields.isEmpty() && (
      <Col sm={12} className="text-center mb10">No foreign field</Col>
    )}
    <Col sm={12} className="mt10">
      <Actions actions={addAction} data="usage" />
    </Col>
  </div>
);


CustomFieldsListUsage.propTypes = {
  fields: PropTypes.instanceOf(List),
  entitiesFieldsConfig: PropTypes.instanceOf(Map),
  addAction: PropTypes.array,
  rowActions: PropTypes.array,
};


CustomFieldsListUsage.defaultProps = {
  fields: List(),
  entitiesFieldsConfig: Map(),
  addAction: [],
  rowActions: [],
};


export default CustomFieldsListUsage;
