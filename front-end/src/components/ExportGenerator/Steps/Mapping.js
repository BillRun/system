import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import pluralize from 'pluralize';
import { Actions } from '@/components/Elements';
import { Col } from 'react-bootstrap';
import { FormGroup, Panel, PanelGroup } from '@/common/BootstrapCompat';
import MapRow from './Elements/MapRow';


class Mapping extends Component {
  
  static propTypes = {
    data: PropTypes.instanceOf(Immutable.Map),
    onChange: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
  };

  static defaultProps = {
    data: Immutable.Map(),
  };

  static defaultField = Immutable.Map({
    name: '',
    type: 'string',
    hard_coded_value: '',
  });

  onAddField = (path) => {
    const { data } = this.props;
    const mappingFields = data.getIn(path, Immutable.List());
    const isCsv = ['separator', 'fixed'].includes (data.getIn(['generator', 'type'], ''));
    if (isCsv) {
      const nextPath = mappingFields.isEmpty() ? 0 : mappingFields.map(field => field.get('path', 0)).max() + 1;
      this.props.onChange(path, mappingFields.push(Mapping.defaultField.set('path', nextPath)));
    } else {
      this.props.onChange(path, mappingFields.push(Mapping.defaultField));
    }
  }

  onRemoveField = (path) => (index) => {
    const { data } = this.props;
    const deletePath = Array.isArray(index) ? index : [index];
    const isCsv = ['separator', 'fixed'].includes (data.getIn(['generator', 'type'], ''));
    if (isCsv) {
      const mappingFields = data
        .getIn(path, Immutable.List())
        .deleteIn(deletePath)
        .sortBy(field => field.get('path', ''))
        .map((field, idx) => field.set('path', idx + 1))
        this.props.onChange(path, mappingFields);
    } else {
      this.props.onRemove([...path, ...deletePath]);
    }
  }

  onUpdateField = (path) => (index, value) => {
    const updatePath = Array.isArray(index) ? index : [index];
    this.props.onChange([...path, ...updatePath], value);
  }

  getRecordsTypes = () => {
    const { data } = this.props;
    return data
      .getIn(['generator', 'record_type_mapping'], Immutable.List())
      .reduce((acc, recordType) => {
        return acc.push(recordType.get('record_type', ''));
      }, Immutable.List())
      .filter(value => value !== '');
  }

  renderRecordTypeBody = (type, idx) => {
    const { data } = this.props;
    const rows = data.getIn(['generator', 'data_structure', type], Immutable.List());
    const actions = [{
      type: 'add',
      actionStyle: 'primary',
      actionSize: 'xsmall',
      label: `Add new field`,
      onClick: this.onAddField,
    }];
    const isFixed = data.getIn(['generator', 'type'], '') === 'fixed';
    const exportType = ['separator', 'fixed'].includes (data.getIn(['generator', 'type'], '')) ? 'csv' : 'xml';
    const params = data.getIn(['filename_params'], Immutable.List());
    const rowsCount = pluralize('field', Number(rows.size), true);
    return (
      <Panel className="collapsible mt10" key={`type_block_${idx}`} collapsible header={`Record Type - ${type} | ${rowsCount}`} defaultExpanded={false}>
        { !rows.isEmpty() && this.renderRowsHeader()}
        {rows.map((row, rowIdx) => (
          <MapRow
            key={`row_${idx}_${rowIdx}`}
            item={row}
            index={rowIdx}
            isFixed={isFixed}
            exportType={exportType}
            paramsOptions={params}
            onRemove={this.onRemoveField(['generator', 'data_structure', type])}
            onChange={this.onUpdateField(['generator', 'data_structure', type])}
          />
        ))}
        <Actions actions={actions} data={['generator', 'data_structure', type]}/>
      </Panel>
    );
  }

  renderBody = () => {
    const types = this.getRecordsTypes();
    if (types.size > 0) {
      return (
        <PanelGroup className="mb0">
          {types.map(this.renderRecordTypeBody)}
        </PanelGroup>
      );
    }
    return (<p><small>Please add at least one 'Record Type' to add fields</small></p>);
  }

  renderRows = (path, name) => {
    const { data } = this.props;
    const rows = data.getIn(['generator', path], Immutable.List());
    const actions = [{
      type: 'add',
      actionStyle: 'primary',
      actionSize: 'xsmall',
      label: `Add new ${name}`,
      onClick: this.onAddField,
    }];
    const isFixed = data.getIn(['generator', 'type'], '') === 'fixed';
    const exportType = ['separator', 'fixed'].includes (data.getIn(['generator', 'type'], '')) ? 'csv' : 'xml';
    const params = data.getIn(['filename_params'], Immutable.List());
    const renderedRows = rows.map((row, rowIdx) => (
      <MapRow
        key={`row_${name}_${rowIdx}`}
        item={row}
        index={rowIdx}
        isFixed={isFixed}
        exportType={exportType}
        paramsOptions={params}
        onRemove={this.onRemoveField(['generator', path])}
        onChange={this.onUpdateField(['generator', path])}
      />
    ));
    return (
      <>
        {!rows.isEmpty() && this.renderRowsHeader()}
        {renderedRows}
        <Actions actions={actions} data={['generator', path]}/>
      </>
    );
  }

  renderRowsHeader = () => {
    return (
      <Col sm={12} className="form-inner-edit-rows">
        <FormGroup className="form-inner-edit-row">
          <Col sm={2} className="d-none d-sm-block"><label htmlFor="field">Field</label></Col>
          <Col sm={2} className="d-none d-sm-block"><label htmlFor="type">Type</label></Col>
          <Col sm={2} className="d-none d-sm-block"><label htmlFor="operator">Operator</label></Col>
          <Col sm={3} className="d-none d-sm-block"><label htmlFor="value">Value</label></Col>
          <Col sm={2} className="d-none d-sm-block"><label htmlFor="value">Format</label></Col>
        </FormGroup>
      </Col>
    );
  }

  render() {
    const { data } = this.props;
    const headRows = data.getIn(['generator', 'header_structure'], Immutable.List());
    const headRowsCount = pluralize('field', Number(headRows.size), true);
    const tailRows = data.getIn(['generator', 'trailer_structure'], Immutable.List());
    const tailRowsCount = pluralize('field', Number(tailRows.size), true);

    return (
      <PanelGroup className="mb0">
        <Panel collapsible className="collapsible mb10" header={`Header | ${headRowsCount}`} defaultExpanded={false}>
          {this.renderRows('header_structure', 'header')}
        </Panel>
        <Panel collapsible className="collapsible mb10" defaultExpanded={true} header="Body">
          {this.renderBody()}
        </Panel>
        <Panel collapsible className="collapsible" header={`Footer | ${tailRowsCount}`} defaultExpanded={false}>
          {this.renderRows('trailer_structure', 'footer')}
        </Panel>
      </PanelGroup>
    );
  }

}


export default Mapping;
