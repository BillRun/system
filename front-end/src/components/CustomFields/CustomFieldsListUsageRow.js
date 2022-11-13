import React from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable';
import { Col, ControlLabel } from 'react-bootstrap';
import { Actions } from '../Elements';
import {
  getConfig,
} from '../../common/Util';
import {
  foreignEntityNameSelector,
  foreignEntityFieldNameSelector,
} from '../../selectors/customFieldsSelectors';


const CustomFieldsListUsageRow = ({ field, actions, entitiesFieldsConfig }) => (
  <Col sm={12} className="table-row row CustomFieldsListRow CustomFieldsListRow usage withHover">
    <Col sm={2} style={{ wordBreak: 'break-all' }}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Title:&nbsp;</ControlLabel>
      </Col>
      {field.get('title', '')}
    </Col>

    <Col sm={2}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Entity:&nbsp;</ControlLabel>
      </Col>
      {foreignEntityNameSelector(field.getIn(['foreign', 'entity'], ''))}
    </Col>

    <Col sm={2}>
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Entity Field:&nbsp;</ControlLabel>
      </Col>
      {foreignEntityFieldNameSelector(field, entitiesFieldsConfig)}
    </Col>

    <Col sm={2} className="text-center">
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Translate:&nbsp;</ControlLabel>
      </Col>
      {getConfig(['customFields', 'foreignFields', 'translate', field.getIn(['foreign', 'translate', 'type'], ''), 'title'], '')}
    </Col>

    <Col sm={2} className="text-center">
      <Col smHidden mdHidden lgHidden className="inline">
        <ControlLabel>Constition:&nbsp;</ControlLabel>
      </Col>
      {field.get('conditions', List()).isEmpty() ? 'No' : 'Yes'}
    </Col>

    <Col sm={2} className="actions">
      <Actions actions={actions} data={field} />
    </Col>

    <Col sm={12}>
      <hr style={{ marginTop: 0, marginBottom: 0 }} />
    </Col>
  </Col>
);


CustomFieldsListUsageRow.propTypes = {
  field: PropTypes.instanceOf(Map),
  entitiesFieldsConfig: PropTypes.instanceOf(Map),
  actions: PropTypes.arrayOf(PropTypes.object),
};


CustomFieldsListUsageRow.defaultProps = {
  field: Map(),
  entitiesFieldsConfig: Map(),
  actions: [],
};


export default CustomFieldsListUsageRow;
