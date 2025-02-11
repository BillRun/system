import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Form, Button, Col, Row, Panel } from 'react-bootstrap';
import Immutable from 'immutable';
import uuid from 'uuid';
import classNames from 'classnames';
import EditorDetails from './Editor/Details';
import EditorConditions from './Editor/Conditions';
import EditorColumns from './Editor/Columns';
import EditorSorts from './Editor/Sorts';
import EditorFormatters from './Editor/Formatters';
import { getConfig, createReportColumnLabel } from '@/common/Util';
import { reportTypes } from '@/actions/reportsActions';


class ReportEditor extends Component {

  static propTypes = {
    report: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string,
    taxType: PropTypes.string,
    progress: PropTypes.bool,
    reportFileds: PropTypes.instanceOf(Immutable.Map),
    aggregateOperators: PropTypes.instanceOf(Immutable.List),
    conditionsOperators: PropTypes.instanceOf(Immutable.List),
    entities: PropTypes.instanceOf(Immutable.List),
    sortOperators: PropTypes.instanceOf(Immutable.List),
    outputFormats: PropTypes.instanceOf(Immutable.List),
    onFilter: PropTypes.func,
    onUpdate: PropTypes.func,
  };

  static defaultProps = {
    report: Immutable.Map(),
    mode: 'update',
    taxType: 'vat',
    progress: false,
    reportFileds: Immutable.Map(),
    aggregateOperators: getConfig(['reports', 'aggregateOperators'], Immutable.List()),
    conditionsOperators: getConfig(['reports', 'conditionsOperators'], Immutable.List()),
    entities: Immutable.List(),
    sortOperators: Immutable.List([
      Immutable.Map({ value: 1, label: 'Ascending' }),
      Immutable.Map({ value: -1, label: 'Descending' }),
    ]),
    outputFormats: getConfig(['reports', 'outputFormats'], Immutable.List()),
    onFilter: () => {},
    onUpdate: () => {},
  };

  onPreview = () => {
    this.props.onFilter();
  }

  updateReport = (type, value, needRefetchData = true) => {
    this.props.onUpdate(type, value, needRefetchData);
  }

  onChangeReportEntity = (val) => {
    this.updateReport('entity', val);
    this.updateReport('conditions', Immutable.List());
    this.updateReport('columns', Immutable.List());
    this.updateReport('sorts', Immutable.List());
  }

  onChangeReportType = (value) => {
    this.updateReport('type', value);
    this.updateColumnsByReportType(value);
    this.updateSortByReportType(value);
  };

  onChangeReportKey = (value) => {
    this.updateReport('key', value);
  };

  /* Conditions */
  onChangeConditionField = (idx, value) => {
    const { report } = this.props;
    const entityField = this.getEntityFields().find(
      reportFiled => reportFiled.get('id', '') === value,
      null, Immutable.Map(),
    );
    const condition = Immutable.Map({
      field: value,
      op: '',
      value: '',
      type: entityField.get('type', 'string'),
      entity: entityField.get('entity', report.get('entity', '')),
    });
    const newFilters = report
      .get('conditions', Immutable.List())
      .set(idx, condition);
    this.updateReport('conditions', newFilters);
  }

  onChangeConditionOperator = (idx, value) => {
    const { report, conditionsOperators } = this.props;
    const oldValue = report.getIn(['conditions', idx, 'op'], '');
    const oldOp = conditionsOperators.find(cond => cond.get('id', '') === oldValue, null, Immutable.Map());
    const newOp = conditionsOperators.find(cond => cond.get('id', '') === value, null, Immutable.Map());
    const newFilters = report
      .get('conditions', Immutable.List())
      .update(idx, Immutable.Map(), (filter) => {
        // reset value if new operator type or options are not the same
        if (oldOp.get('type', '') !== newOp.get('type', '')
          || !Immutable.is(oldOp.get('options', Immutable.List()), newOp.get('options', Immutable.List()))
        ) {
          return filter
            .set('op', value)
            .set('value', '');
        }
        return filter.set('op', value);
      });
    this.updateReport('conditions', newFilters);
  }

  onChangeConditionValue = (idx, value) => {
    const { report } = this.props;
    const newFilters = report
      .get('conditions', Immutable.List())
      .setIn([idx, 'value'], value);
    this.updateReport('conditions', newFilters);
  }

  onRemoveCondition = (index) => {
    const { report } = this.props;
    const newFilters = report
      .get('conditions', Immutable.List())
      .delete(index);
    this.updateReport('conditions', newFilters);
  }

  onAddCondition = () => {
    const { report } = this.props;
    const newFilters = report
      .get('conditions', Immutable.List())
      .push(Immutable.Map({
        field: '',
        op: '',
        value: '',
      }));
    this.updateReport('conditions', newFilters);
  }
  /* ~ Conditions */

  /* Sort */
  onChangeSortOperator = (idx, value) => {
    const { report } = this.props;
    const sorts = report
      .get('sorts', Immutable.List())
      .setIn([idx, 'op'], value);
    this.updateReport('sorts', sorts);
  }

  onChangeSortField = (idx, value) => {
    const { report } = this.props;
    const sorts = report
      .get('sorts', Immutable.List())
      .setIn([idx, 'field'], value)
      .setIn([idx, 'op'], '');
    this.updateReport('sorts', sorts);
  }

  onMoveSort = (oldIndex, newIndex) => {
    const { report } = this.props;
    const curr = report.getIn(['sorts', oldIndex]);
    const sorts = report
      .get('sorts', Immutable.List())
      .delete(oldIndex)
      .insert(newIndex, curr);
    this.updateReport('sorts', sorts);
  }

  onRemoveSort = (index) => {
    const { report } = this.props;
    const sorts = report
      .get('sorts', Immutable.List())
      .delete(index);
    this.updateReport('sorts', sorts);
  }

  onRemoveSortByKey = (key) => {
    const { report } = this.props;
    const sorts = report.get('sorts', Immutable.List());
    const keyIndex = sorts.findIndex((sort => sort.get('field', '') === key));
    if (keyIndex > -1) {
      this.updateReport('sorts', sorts.delete(keyIndex));
    }
  }

  onAddSort = () => {
    const { report } = this.props;
    const sorts = report
      .get('sorts', Immutable.List())
      .push(Immutable.Map({
        field: '',
        op: '',
      }));
    this.updateReport('sorts', sorts);
  }
  /* ~Sort */

  /* Format  */
  onChangeFormatField = (idx, value) => {
    const { report } = this.props;
    const formats = report
      .get('formats', Immutable.List())
      .setIn([idx, 'field'], value)
      .setIn([idx, 'op'], '')
      .setIn([idx, 'value'], '');
    this.updateReport('formats', formats);
  }

  onChangeFormatOperator = (idx, value) => {
    const { report } = this.props;
    const formats = report
      .get('formats', Immutable.List())
      .setIn([idx, 'op'], value)
      .setIn([idx, 'value'], '');
    this.updateReport('formats', formats);
  }

  onChangeFormatValue = (idx, value) => {
    const { report } = this.props;
    const formats = report
      .get('formats', Immutable.List())
      .setIn([idx, 'value'], value);
    this.updateReport('formats', formats);
  }

  onChangeFormatValueType = (idx, value) => {
    const { report } = this.props;
    const formats = report
      .get('formats', Immutable.List())
      .setIn([idx, 'type'], value);
    this.updateReport('formats', formats);
  }

  onRemoveFormat = (index) => {
    const { report } = this.props;
    const formats = report
      .get('formats', Immutable.List())
      .delete(index);
    this.updateReport('formats', formats);
  }

  onRemoveFormatByKey = (key) => {
    const { report } = this.props;
    const formats = report.get('formats', Immutable.List());
    const formatsWithoutKey = formats.filter(format => format.get('field', '') !== key);
    if (!Immutable.is(formatsWithoutKey, formats)) {
      this.updateReport('formats', formatsWithoutKey);
    }
  }

  onAddFormat = () => {
    const { report } = this.props;
    const formats = report
      .get('formats', Immutable.List())
      .push(Immutable.Map({
        field: '',
        op: '',
        value: '',
      }));
    this.updateReport('formats', formats);
  }

  onMoveFormat = (oldIndex, newIndex) => {
    const { report } = this.props;
    const curr = report.getIn(['formats', oldIndex]);
    const formats = report
      .get('formats', Immutable.List())
      .delete(oldIndex)
      .insert(newIndex, curr);
    this.updateReport('formats', formats);
  }
  /* ~Format */

  /* Columns */
  onChangeColumnField = (index, value) => {
    const { report } = this.props;
    const entityField = this.getEntityFields().find(
      reportFiled => reportFiled.get('id', '') === value,
      null, Immutable.Map(),
    );
    const columns = report
      .get('columns', Immutable.List())
      .update(index, Immutable.Map(), (column) => {
        const label = column.get('label', '');
        const fieldName = column.get('field_name', '');
        const op = column.get('op', '');
        const newLabel = this.getColumnNewLabel(label, fieldName, op, value, op);
        return column.withMutations(columnWithMutations =>
          columnWithMutations
            .set('field_name', value)
            .set('label', newLabel)
            .set('entity', entityField.get('entity', report.get('entity', ''))),
        );
      });
    this.updateReport('columns', columns);
  }

  onChangeColumnOperator = (index, value) => {
    const { report } = this.props;
    const columns = report
      .get('columns', Immutable.List())
      .update(index, Immutable.Map(), (column) => {
        const label = column.get('label', '');
        const fieldName = column.get('field_name', '');
        const op = column.get('op', '');
        const newLabel = this.getColumnNewLabel(label, fieldName, op, fieldName, value);
        return column.withMutations(columnWithMutations =>
          columnWithMutations
            .set('op', value)
            .set('label', newLabel),
        );
      });
    this.updateReport('columns', columns);
  }

  onChangeColumnLabel = (index, value) => {
    const { report } = this.props;
    const columns = report
      .get('columns', Immutable.List())
      .setIn([index, 'label'], value);
    this.updateReport('columns', columns, false);
  }

  updateColumnsByReportType = (value) => {
    const { report } = this.props;
    const columns = report
      .get('columns', Immutable.List())
      .map((column) => {
        const label = column.get('label', '');
        const fieldName = column.get('field_name', '');
        const op = column.get('op', '');
        const oldOp = (value === reportTypes.GROPED) ? '' : op;
        const newOp = (value === reportTypes.GROPED) ? op : '';
        const newLabel = this.getColumnNewLabel(label, fieldName, oldOp, fieldName, newOp);
        return column.set('label', newLabel);
      })
      .update((cols) => {
        if (value === reportTypes.SIMPLE) {
          return cols.filter(column => column.get('key', '') !== 'count_group');
        }
        return cols;
      });
    this.updateReport('columns', columns);
  };

  updateSortByReportType = (value) => {
    const { report } = this.props;
    const sorts = report
      .get('sorts', Immutable.List())
      .update((cols) => {
        if (value === reportTypes.SIMPLE) {
          return cols.filter(column => column.get('field', '') !== 'count_group');
        }
        return cols;
      });
    this.updateReport('sorts', sorts);
  }

  onMoveColumn = (oldIndex, newIndex) => {
    const { report } = this.props;
    const curr = report.getIn(['columns', oldIndex]);
    const columns = report
      .get('columns', Immutable.List())
      .delete(oldIndex)
      .insert(newIndex, curr);
    this.updateReport('columns', columns);
  }

  onAddColumn = (column = null) => {
    const { report } = this.props;
    const newColumn = column || Immutable.Map({
      key: uuid.v4(),
      field_name: '',
      label: '',
      op: '',
    });
    const columns = report
      .get('columns', Immutable.List())
      .push(newColumn);
    this.updateReport('columns', columns);
  }

  onRemoveColumn = (index) => {
    const { report } = this.props;
    const keyToRemove = report.getIn(['columns', index, 'key'], '');
    this.onRemoveSortByKey(keyToRemove);
    this.onRemoveFormatByKey(keyToRemove);
    const columns = report
      .get('columns', Immutable.List())
      .delete(index);
    this.updateReport('columns', columns, false);
  }
  /* ~Columns */

  getColumnNewLabel = (label, oldfieldName, oldOp, newFieldName, newOp) => {
    const { aggregateOperators } = this.props;
    const fieldsConfig = this.getEntityFields();
    const newLabel = createReportColumnLabel(
      label, fieldsConfig, aggregateOperators, oldfieldName, oldOp, newFieldName, newOp,
    );
    return newLabel;
  }

  getEntityFields = () => {
    const { report, reportFileds } = this.props;
    return reportFileds.get(report.get('entity', ''), Immutable.List());
  }

  getOutputFormats = () => {
    const { taxType, outputFormats } = this.props;
    return taxType === 'vat' ? outputFormats : outputFormats.filter(format => format.get('id', '') !== 'vat_format');
  }

  render() {
    const {
      mode, report, aggregateOperators, conditionsOperators, sortOperators, entities, progress,
    } = this.props;
    const fieldsConfig = this.getEntityFields();
    const columns = report.get('columns', Immutable.List());
    const mandatory = <span className="danger-red"> *</span>;
    const outputFormats = this.getOutputFormats();
    const previewBtnClass = classNames('fa', {
      'fa-search': !progress,
      'fa-spinner fa-pulse': progress,
    });
    return (
      <div className="ReportEditor">
        <Form horizontal>
          <Panel header={<span>Basic Details</span>} collapsible={mode === 'update'} className="collapsible">
            <EditorDetails
              mode={mode}
              title={report.get('key', '')}
              entity={report.get('entity', '')}
              entities={entities}
              type={report.get('type', reportTypes.SIMPLE)}
              onChangeKey={this.onChangeReportKey}
              onChangeEntity={this.onChangeReportEntity}
              onChangeType={this.onChangeReportType}
            />
          </Panel>
          <Panel header={<span>Columns {mandatory}</span>}>
            <EditorColumns
              mode={mode}
              columns={columns}
              fieldsConfig={fieldsConfig}
              type={report.get('type', reportTypes.SIMPLE)}
              aggregateOperators={aggregateOperators}
              onChangeField={this.onChangeColumnField}
              onChangeOperator={this.onChangeColumnOperator}
              onChangeLabel={this.onChangeColumnLabel}
              onAdd={this.onAddColumn}
              onRemove={this.onRemoveColumn}
              onMove={this.onMoveColumn}
            />
          </Panel>
          <Panel header={<span>Conditions</span>} collapsible className="collapsible">
            <EditorConditions
              mode={mode}
              conditions={report.get('conditions', Immutable.List())}
              fieldsOptions={fieldsConfig}
              operators={conditionsOperators}
              onRemove={this.onRemoveCondition}
              onAdd={this.onAddCondition}
              onChangeField={this.onChangeConditionField}
              onChangeOperator={this.onChangeConditionOperator}
              onChangeValue={this.onChangeConditionValue}
            />
          </Panel>
          <Panel header={<span>Sort</span>} collapsible className="collapsible">
            <EditorSorts
              mode={mode}
              sorts={report.get('sorts', Immutable.List())}
              options={columns}
              sortOperators={sortOperators}
              onChangeField={this.onChangeSortField}
              onChangeOperator={this.onChangeSortOperator}
              onRemove={this.onRemoveSort}
              onAdd={this.onAddSort}
              onMove={this.onMoveSort}
            />
          </Panel>
          <Panel header={<span>Formatting Style</span>} collapsible className="collapsible">
            <EditorFormatters
              mode={mode}
              formats={report.get('formats', Immutable.List())}
              options={columns}
              formatOperators={outputFormats}
              onChangeField={this.onChangeFormatField}
              onChangeOperator={this.onChangeFormatOperator}
              onChangeValue={this.onChangeFormatValue}
              onChangeValueType={this.onChangeFormatValueType}
              onRemove={this.onRemoveFormat}
              onAdd={this.onAddFormat}
              onMove={this.onMoveFormat}
            />
          </Panel>
        </Form>
        <Row>
          <Col sm={12}>
            <Button bsStyle="primary" onClick={this.onPreview} block disabled={progress}>
              <i className={previewBtnClass} />&nbsp;Preview
            </Button>
          </Col>
          <Col sm={12}>&nbsp;</Col>
        </Row>
      </div>
    );
  }
}

export default ReportEditor;
