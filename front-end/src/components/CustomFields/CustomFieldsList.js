import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { List, Map, OrderedMap } from 'immutable';
import { Col, Row, Button } from 'react-bootstrap';
import { ControlLabel, Panel } from '@/common/BootstrapCompat';
import CustomFieldsListRowContainer from './CustomFieldsListRowContainer';
import { CreateButton, SortableFieldsContainer } from '../Elements';
import {
  isFieldSortable,
} from '../../selectors/customFieldsSelectors';


class CustomFieldsList extends Component {

  static propTypes = {
    entity: PropTypes.string,
    fieldsConfig: PropTypes.instanceOf(Map),
    fields: PropTypes.instanceOf(List),
    reordering: PropTypes.bool,
    onNew: PropTypes.func.isRequired,
    onEdit: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
    onReorder: PropTypes.func.isRequired,
    onReorderStart: PropTypes.func.isRequired,
    onReorderSave: PropTypes.func.isRequired,
    onReorderCancel: PropTypes.func.isRequired,
  };

  static defaultProps = {
    entity: '',
    fields: List(),
    fieldsConfig: Map(),
    reordering: false,
  };

  componentWillUnmount() {
    const { onReorderCancel, reordering } = this.props;
    if (reordering) {
      onReorderCancel();
    }
  }

  getFieldsByCategory = () => {
    const { fields } = this.props;
  
    return fields.reduce(
      (acc, field) => {
        const category = field.get('category', '') || 'uncategorized';
        return acc.update(category, List(), cat => cat.push(field));
      }, OrderedMap()).sortBy((_, category) => (category === 'uncategorized' ? -1 : category));
  };
  

  renderFieldsByCategory = () => {
    const {
      entity,
      fieldsConfig,
      reordering,
      onReorder,
      onRemove,
      onEdit,
    } = this.props;

    const fieldsByCategory = this.getFieldsByCategory();

    return fieldsByCategory.map((fields, category) => {
      const rows = fields.map((field, index) => (
        <CustomFieldsListRowContainer
          key={`item-${entity}-${field.get('field_name', '')}-${index}`}
          index={index}
          disabled={!isFieldSortable(field, fieldsConfig) || (isFieldSortable(field, fieldsConfig) && !reordering)}
          collection={category}
          entity={entity}
          field={field}
          fieldsConfig={fieldsConfig}
          isReordable={reordering}
          onRemove={onRemove}
          onEdit={onEdit}
        />
      ))

      return category === 'uncategorized' ? (
        <SortableFieldsContainer
          key={`sortable-container-${category}`}
          lockAxis="y"
          helperClass="draggable-row"
          useDragHandle={true}
          collection={category}
          items={rows}
          onSortEnd={onReorder}
        >
          {rows}
        </SortableFieldsContainer>
      ) : (
        <Panel
          header={category}
          key={`panel-${category}`}
          collapsible
          className="collapsible"
        >
          <SortableFieldsContainer
            key={`sortable-container-${category}`}
            lockAxis="y"
            helperClass="draggable-row"
            useDragHandle={true}
            collection={category}
            items={rows}
            onSortEnd={onReorder}
          >
            {rows}
          </SortableFieldsContainer>
        </Panel>
      );
    }).toList();
  }

  render() {
    const {
      fields,
      reordering,
      onNew,
      onReorderStart,
      onReorderSave,
      onReorderCancel,
    } = this.props;
    return (
      <div className="CustomFieldsList">
        <Row>
          <Col sm={1} className="d-none d-sm-block">&nbsp;</Col>
          <Col sm={2} className="d-none d-sm-block"><ControlLabel>Key</ControlLabel></Col>
          <Col sm={3} className="d-none d-sm-block"><ControlLabel>Title</ControlLabel></Col>
          <Col sm={1} className="d-none d-sm-block"><ControlLabel>Type</ControlLabel></Col>
          <Col sm={3} className="d-none d-sm-block text-center"><ControlLabel>Default Value</ControlLabel></Col>
          <Col sm={2} className="d-none d-sm-block">&nbsp;</Col>
        </Row>
        <Row>
          <Col sm={12} className="d-none d-sm-block">
            <hr style={{ marginTop: 5, marginBottom: 0 }} />
          </Col>
        </Row>
        {!fields.isEmpty() && this.renderFieldsByCategory()}
        {fields.isEmpty() && (
          <Col sm={12} className="text-center mb10">No custom field</Col>
        )}
        { !reordering && (
          <Col sm={12} className="mt10">
            <CreateButton onClick={onNew} type="Field" action="Add" buttonStyle={{ marginTop: 0 }} buttonClass="btn-xs" />
            {!fields.isEmpty() && (
              <Button size="sm" variant="primary" className="btn-xs" onClick={onReorderStart} title="Change fields order" style={{ float: 'right', minWidth: 90 }}>
                <i className="fa fa-arrows-alt" /> Reorder
              </Button>
            )}
          </Col>
        )}
        { reordering && (
          <Col sm={12} className="text-right mt10">
            <Button size="sm" onClick={onReorderSave} title="Save new order" variant="primary" style={{ minWidth: 90, marginRight: 10 }}>
              Save order
            </Button>
            <Button size="sm" onClick={onReorderCancel} title="Cancel new order" style={{ minWidth: 90 }}>
              Cancel order
            </Button>
          </Col>
        )}
      </div>
    );
  }
}

export default CustomFieldsList;
