import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, Row, Col, HelpBlock } from 'react-bootstrap';
import { ReportDescription } from '../../../language/FieldDescriptions';
import { CreateButton, SortableFieldsContainer } from '@/components/Elements';
import Column from './Column';
import { reportTypes } from '@/actions/reportsActions';
import { getFieldName } from '@/common/Util';


class Columns extends Component {

  static propTypes = {
    columns: PropTypes.instanceOf(Immutable.List),
    fieldsConfig: PropTypes.instanceOf(Immutable.List),
    type: PropTypes.number,
    aggregateOperators: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onChangeLabel: PropTypes.func,
    onAdd: PropTypes.func,
    onRemove: PropTypes.func,
    onMove: PropTypes.func,
    onAddCount: PropTypes.func,
  }

  static defaultProps = {
    columns: Immutable.List(),
    fieldsConfig: Immutable.List(),
    aggregateOperators: Immutable.List(),
    mode: 'update',
    type: 0,
    onChangeField: () => {},
    onChangeOperator: () => {},
    onChangeLabel: () => {},
    onAdd: () => {},
    onRemove: () => {},
    onMove: () => {},
    onAddCount: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { mode, columns, fieldsConfig, aggregateOperators, type } = this.props;
    return (
      !Immutable.is(columns, nextProps.columns)
      || !Immutable.is(fieldsConfig, nextProps.fieldsConfig)
      || !Immutable.is(aggregateOperators, nextProps.aggregateOperators)
      || mode !== nextProps.mode
      || type !== nextProps.type
    );
  }

  onMoveEnd = ({ oldIndex, newIndex }) => {
    this.props.onMove(oldIndex, newIndex);
  };

  getFieldsOptions = fieldsConfig => fieldsConfig.filter(
    config => config.get('aggregatable', true),
  );

  getColumnFieldsOptions = () => {
    const key = this.getCountColumnKey();
    return Immutable.Map({
      aggregatable: true,
      searchable: true,
      id: key,
      title: getFieldName(key, 'report'),
    });
  }

  isCountColumn = column => column.get('key', '') === this.getCountColumnKey();

  getCountColumnKey = () => 'count_group';

  isCountColumnExist = columns => columns.findIndex(
    column => column.get('key', '') === this.getCountColumnKey(),
  ) > -1;

  onAddCount = () => {
    const key = this.getCountColumnKey();
    const column = Immutable.Map({
      key,
      field_name: key,
      label: getFieldName(key, 'report'),
      op: 'count',
    });
    this.props.onAdd(column);
  }

  onAdd = (e) => { // eslint-disable-line no-unused-vars
    this.props.onAdd();
  }

  renderRows = () => {
    const { mode, columns, fieldsConfig, aggregateOperators, type } = this.props;
    const disabled = mode === 'view';
    const fieldsOptions = this.getFieldsOptions(fieldsConfig);
    return columns.map((column, index) => {
      const isCountColumn = this.isCountColumn(column);
      const columOptions = isCountColumn
        ? fieldsOptions.push(this.getColumnFieldsOptions())
        : fieldsOptions;
      return (
        <Column
          key={column.get('key', index)}
          item={column}
          idx={index}
          index={index}
          disabled={disabled}
          type={type}
          isCountColumn={isCountColumn}
          fieldsConfig={columOptions}
          operators={aggregateOperators}
          onChangeField={this.props.onChangeField}
          onChangeOperator={this.props.onChangeOperator}
          onChangeLabel={this.props.onChangeLabel}
          onRemove={this.props.onRemove}
        />
      );
    });
  }

  render() {
    const { mode, type, fieldsConfig, columns } = this.props;
    const columnsRows = this.renderRows();
    const disableAdd = fieldsConfig.isEmpty();
    const emptyHelpText = (type === reportTypes.GROPED)
      ? ReportDescription.block_columns_grouped
      : ReportDescription.block_columns_simple;
    const disableCreateNewTitle = disableAdd ? ReportDescription.add_columns_disabled_no_entity : '';
    return (
      <Row>
        <Col sm={12}>
          { !columnsRows.isEmpty() ? (
            <FormGroup className="form-inner-edit-row">
              <Col sm={1} xsHidden>&nbsp;</Col>
              <Col sm={4} xsHidden><label htmlFor="field_field">Field</label></Col>
              <Col sm={3} xsHidden><label htmlFor="operator_field">{type !== reportTypes.SIMPLE && 'Function'}</label></Col>
              <Col sm={3} xsHidden><label htmlFor="value_field">Label</label></Col>
            </FormGroup>
          ) : (
            <HelpBlock>{ emptyHelpText }</HelpBlock>
          )}
        </Col>
        <Col sm={12}>
          <SortableFieldsContainer
            lockAxis="y"
            helperClass="draggable-row"
            useDragHandle={true}
            items={columnsRows.toArray()}
            onSortEnd={this.onMoveEnd}
          />
        </Col>
        { mode !== 'view' && (
          <Col sm={12}>
            <CreateButton
              onClick={this.onAdd}
              label="Add Column"
              disabled={disableAdd}
              title={disableCreateNewTitle}
            />
            { type !== reportTypes.SIMPLE && (
              <span style={{ marginLeft: 10 }}>
                <CreateButton
                  onClick={this.onAddCount}
                  label="Add Count Column"
                  disabled={this.isCountColumnExist(columns)}
                  title="Add Column to count group fields"
                />
              </span>
            )}
          </Col>
        )}
      </Row>
    );
  }
}

export default Columns;
