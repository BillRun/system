import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Row, Col, FormGroup, HelpBlock, Label } from 'react-bootstrap';
import { ReportDescription } from '../../../language/FieldDescriptions';
import Formatter from './Formatter';
import { CreateButton, SortableFieldsContainer } from '@/components/Elements';

class Formatters extends Component {

  static propTypes = {
    formats: PropTypes.instanceOf(Immutable.List),
    options: PropTypes.instanceOf(Immutable.List),
    formatOperators: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onChangeValue: PropTypes.func,
    onChangeValueType: PropTypes.func,
    onMove: PropTypes.func,
    onRemove: PropTypes.func,
    onAdd: PropTypes.func,
  }

  static defaultProps = {
    formats: Immutable.List(),
    options: Immutable.List(),
    formatOperators: Immutable.List(),
    mode: 'update',
    onChangeField: () => {},
    onChangeOperator: () => {},
    onChangeValue: () => {},
    onChangeValueType: () => {},
    onMove: () => {},
    onRemove: () => {},
    onAdd: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { formats, options, mode, formatOperators } = this.props;
    return (
      !Immutable.is(formats, nextProps.formats)
      || !Immutable.is(options, nextProps.options)
      || !Immutable.is(formatOperators, nextProps.formatOperators)
      || mode !== nextProps.mode
    );
  }

  getUsedOptions = () => {
    const { formats } = this.props;
    return formats;
    // return formats
    //   .filter(formatRow => formatRow.get('field', '') !== '')
    //   .map(formatRow => formatRow.get('field', ''));
  }

  renderformaterRow = (formatRow, index) => {
    const { options, mode, formatOperators } = this.props;
    const disabled = mode === 'view';
    const usedOptions = this.getUsedOptions();
    const fieldOptions = options.filter(option => !usedOptions.includes(option.get('key', '')) || formatRow.get('field', '') === option.get('key', ''));
    return (
      <Formatter
        key={index}
        item={formatRow}
        index={index}
        idx={index}
        disabled={disabled}
        options={fieldOptions}
        operators={formatOperators}
        onChangeField={this.props.onChangeField}
        onChangeOperator={this.props.onChangeOperator}
        onChangeValue={this.props.onChangeValue}
        onChangeValueType={this.props.onChangeValueType}
        onRemove={this.props.onRemove}
      />
    );
  }

  onMoveEnd = ({ oldIndex, newIndex }) => {
    this.props.onMove(oldIndex, newIndex);
  };

  render() {
    const { formats, options, mode } = this.props;
    const disabled = mode === 'view';
    const disableCreateNew = disabled || options.isEmpty();
    const disableCreateNewtitle = disableCreateNew && options.isEmpty() ? ReportDescription.add_formatter_disabled_no_fields : '';
    const formatRows = formats.map(this.renderformaterRow);
    const showDateOverrideWarning = formats.findIndex(format => format.get('op', '') === 'date_override') > -1;
    return (
      <Row>
        <Col sm={12}>
          { !formatRows.isEmpty() ? (
            <FormGroup className="form-inner-edit-row">
              <Col sm={1}>&nbsp;</Col>
              <Col sm={4}><label htmlFor="field_field">Field</label></Col>
              <Col sm={3}><label htmlFor="operator_field">Type / Operator</label></Col>
              <Col sm={3}><label htmlFor="value_field">Format / Value</label></Col>
            </FormGroup>
          ) : (
            <HelpBlock>{ReportDescription.block_formatter}</HelpBlock>
          )}
        </Col>
        <Col sm={12}>
          <SortableFieldsContainer
            lockAxis="y"
            helperClass="draggable-row"
            useDragHandle={true}
            items={formatRows.toArray()}
            onSortEnd={this.onMoveEnd}
          />
        </Col>
        { mode !== 'view' && (
          <Col sm={12}>
            <CreateButton
              onClick={this.props.onAdd}
              label="Add Formatting Style"
              disabled={disableCreateNew}
              title={disableCreateNewtitle}
            />
          </Col>
        )}
        { showDateOverrideWarning && (
          <Col sm={12}>
            <Label bsStyle="warning">
              * Subtract / Add time styling should be applied before other format
            </Label>
          </Col>
        )}
      </Row>
    );
  }

}

export default Formatters;
