import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { List, Map } from 'immutable';
import { Col, Row, ControlLabel, Button } from 'react-bootstrap';
import CustomFieldsListRowContainer from './CustomFieldsListRowContainer';
import { CreateButton, SortableFieldsContainer } from '../Elements';
import {
  isFieldPrintable,
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
    onReorederStart: PropTypes.func.isRequired,
    onReorederSave: PropTypes.func.isRequired,
    onReorederCancel: PropTypes.func.isRequired,
  };

  static defaultProps = {
    entity: '',
    fields: List(),
    fieldsConfig: Map(),
    reordering: false,
  };

  componentWillUnmount() {
    const { onReorederCancel, reordering } = this.props;
    if (reordering) {
      onReorederCancel();
    }
  }

  getprintableFields = () => {
    const { entity, fields, fieldsConfig, onRemove, onEdit, reordering } = this.props;
    return fields.reduce((acc, field, index) => (
      (!isFieldPrintable(field, fieldsConfig)) ? acc : acc.push(
        <CustomFieldsListRowContainer
          key={`item-${entity}-${field.get('field_name', '')}-${index}`}
          index={index}
          disabled={!isFieldSortable(field, fieldsConfig) || (isFieldSortable(field, fieldsConfig) && !reordering)}
          collection={entity}
          entity={entity}
          field={field}
          fieldsConfig={fieldsConfig}
          isReordable={reordering}
          onRemove={onRemove}
          onEdit={onEdit}
        />
      )
    ), List());
  }

  render() {
    const {
      fields, reordering,
      onReorder, onNew, onReorederStart, onReorederSave, onReorederCancel,
    } = this.props;
    return (
      <div className="CustomFieldsList">
        <Row>
          <Col xsHidden sm={1}>&nbsp;</Col>
          <Col xsHidden sm={2}><ControlLabel>Key</ControlLabel></Col>
          <Col xsHidden sm={3}><ControlLabel>Title</ControlLabel></Col>
          <Col xsHidden sm={1}><ControlLabel>Type</ControlLabel></Col>
          <Col xsHidden sm={3} className="text-center"><ControlLabel>Default Value</ControlLabel></Col>
          <Col xsHidden sm={2}>&nbsp;</Col>
        </Row>
        <Row>
          <Col sm={12} xsHidden>
            <hr style={{ marginTop: 5, marginBottom: 0 }} />
          </Col>
        </Row>
        {!fields.isEmpty() && (
          <SortableFieldsContainer
            lockAxis="y"
            helperClass="draggable-row"
            useDragHandle={true}
            items={this.getprintableFields()}
            onSortEnd={onReorder}
          />
        )}
        {fields.isEmpty() && (
          <Col sm={12} className="text-center mb10">No custom field</Col>
        )}
        { !reordering && (
          <Col sm={12} className="mt10">
            <CreateButton onClick={onNew} type="Field" action="Add" buttonStyle={{ marginTop: 0 }} />
              {!fields.isEmpty() && (
                <Button bsSize="xsmall" className="btn-primary" onClick={onReorederStart} title="Change fields order" style={{ float: 'right', minWidth: 90 }}>
                  <i className="fa fa-arrows-alt" /> Reorder
                </Button>
              )}
          </Col>
        )}
        { reordering && (
          <Col sm={12} className="text-right mt10">
            <Button bsSize="xsmall" onClick={onReorederSave} title="Save new order" bsStyle="primary" style={{ minWidth: 90, marginRight: 10 }}>
              Save order
            </Button>
            <Button bsSize="xsmall" onClick={onReorederCancel} title="Cancel new order" style={{ minWidth: 90 }}>
              Cancel order
            </Button>
          </Col>
        )}
      </div>
    );
  }
}

export default CustomFieldsList;
