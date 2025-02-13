import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Row, Col, FormGroup, HelpBlock } from 'react-bootstrap';
import { ReportDescription } from '../../../language/FieldDescriptions';
import Sort from './Sort';
import { CreateButton, SortableFieldsContainer } from '@/components/Elements';

class Sorts extends Component {

  static propTypes = {
    sorts: PropTypes.instanceOf(Immutable.List),
    options: PropTypes.instanceOf(Immutable.List),
    sortOperators: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onMove: PropTypes.func,
    onRemove: PropTypes.func,
    onAdd: PropTypes.func,
  }

  static defaultProps = {
    sorts: Immutable.List(),
    options: Immutable.List(),
    sortOperators: Immutable.List(),
    mode: 'update',
    onChangeField: () => {},
    onChangeOperator: () => {},
    onMove: () => {},
    onRemove: () => {},
    onAdd: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { sorts, options, mode, sortOperators } = this.props;
    return (
      !Immutable.is(sorts, nextProps.sorts)
      || !Immutable.is(options, nextProps.options)
      || !Immutable.is(sortOperators, nextProps.sortOperators)
      || mode !== nextProps.mode
    );
  }

  getUsedOptions = () => {
    const { sorts } = this.props;
    return sorts
      .filter(sortRow => sortRow.get('field', '') !== '')
      .map(sortRow => sortRow.get('field', ''));
  }

  renderSortRow = (sortRow, index) => {
    const { options, mode, sortOperators } = this.props;
    const disabled = mode === 'view';
    const usedOptions = this.getUsedOptions();
    const fieldOptions = options
      .filter(option => !usedOptions.includes(option.get('key', '')) || sortRow.get('field', '') === option.get('key', ''))
      .map(option => Immutable.Map({
        value: option.get('key', ''),
        label: option.get('label', ''),
      }));
    return (
      <Sort
        key={index}
        item={sortRow}
        index={index}
        idx={index}
        disabled={disabled}
        options={fieldOptions}
        sortOperators={sortOperators}
        onChangeField={this.props.onChangeField}
        onChangeOperator={this.props.onChangeOperator}
        onRemove={this.props.onRemove}
      />
    );
  }

  onMoveEnd = ({ oldIndex, newIndex }) => {
    this.props.onMove(oldIndex, newIndex);
  };

  render() {
    const { sorts, options, mode } = this.props;
    const disabled = mode === 'view';
    const disableCreateNew = disabled || options.isEmpty();
    const disableCreateNewtitle = disableCreateNew && options.isEmpty() ? ReportDescription.add_sort_disabled_no_fields : '';
    const sortRows = sorts.map(this.renderSortRow);
    return (
      <Row>
        <Col sm={12}>
          { !sortRows.isEmpty() ? (
            <FormGroup className="form-inner-edit-row">
              <Col sm={1} xsHidden>&nbsp;</Col>
              <Col sm={5} xsHidden><label htmlFor="field_field">Field</label></Col>
              <Col sm={5} xsHidden><label htmlFor="order_field">Order</label></Col>
            </FormGroup>
          ) : (
            <HelpBlock>{ReportDescription.block_sort}</HelpBlock>
          )}
        </Col>
        <Col sm={12}>
          <SortableFieldsContainer
            lockAxis="y"
            helperClass="draggable-row"
            useDragHandle={true}
            items={sortRows.toArray()}
            onSortEnd={this.onMoveEnd}
          />
        </Col>
        { mode !== 'view' && (
          <Col sm={12}>
            <CreateButton
              onClick={this.props.onAdd}
              label="Add Sort"
              disabled={disableCreateNew}
              title={disableCreateNewtitle}
            />
          </Col>
        )}
      </Row>
    );
  }

}

export default Sorts;
