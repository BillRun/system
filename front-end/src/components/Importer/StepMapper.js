import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col, Label, FormGroup, ControlLabel, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import MapField from './MapField';


const StepMapper = (props) => {
  const { item, fields, ignoredHeaders, mapperPrefix, defaultFieldsValues } = props;
  const fileContent = item.get('fileContent', []) || [];
  const linkerField = item.getIn(['linker', 'field'], '') || '';
  const linkerValue = item.getIn(['linker', 'value'], '') || '';
  const updaterField = item.getIn(['updater', 'field'], '') || '';
  const updaterValue = item.getIn(['updater', 'value'], '') || '';
  const operation = item.get('operation', 'create');
  const headers = fileContent[0] || [];

  const onChangeLinkerField = (value) => {
    if (value !== '') {
      props.onChange(['linker', 'field'], value);
    } else {
      props.onDelete(['linker', 'field']);
    }
  };

  const onChangeLinkerValue = (value) => {
    if (value !== '') {
      props.onChange(['linker', 'value'], value);
    } else {
      props.onDelete(['linker', 'value']);
    }
  };

  const onChangeUpdaterField = (value) => {
    if (value !== '') {
      props.onChange(['updater', 'field'], value);
    } else {
      props.onDelete(['updater', 'field']);
    }
  };

  const onChangeUpdaterValue = (value) => {
    if (value !== '') {
      props.onChange(['updater', 'value'], value);
    } else {
      props.onDelete(['updater', 'value']);
    }
  };

  const onChangeEffectiveDate = (value) => {
    if (value !== '') {
      props.onChange(['map', 'effective_date'], value);
    } else {
      props.onDelete(['map', 'effective_date']);
    }
  };

  const soptFields = (f1, f2) => {
    // Sort by : mandatory -> unique -> other by ABC
    if (f1.mandatory && !f2.mandatory) {
      return -1;
    }
    if (!f1.mandatory && f2.mandatory) {
      return 1;
    }
    if (f1.unique && !f2.unique) {
      return -1;
    }
    if (!f1.unique && f2.unique) {
      return 1;
    }

    const indexf1 = fields.findIndex(field => field.value === f1.value);
    const indexf2 = fields.findIndex(field => field.value === f2.value);
    return indexf1 > indexf2 ? 1 : -1;
  };

  const filterFields = field => // filter : only Not generated and editabe fields
    (!field.generated
      && field.show !== false
      && field.value !== 'effective_date'
      && field.editable
      && (!field.hasOwnProperty('linker') || !field.linker)
      && props.customFilterFields(field)
    );

  const csvHeaders = headers
    .map((header, key) => ({
      label: header,
      value: `${mapperPrefix}${key}`,
    }))
    .filter(option => !ignoredHeaders.includes(option.label));

  const renderLinkers = () => {
    const linkersOptions = fields
      .filter(field => (field.hasOwnProperty('linker') && field.linker))
      .map(field => ({
        value: field.value,
        label: field.label,
      }));

    if (linkersOptions.length === 0) {
      return null;
    }

    return (
      <Panel header="Linker" className="mb0">
        <FormGroup>
          <Col sm={3} componentClass={ControlLabel}>Link to field<span className="danger-red"> *</span></Col>
          <Col sm={9}>
            <Field
              fieldType="select"
              onChange={onChangeLinkerField}
              options={linkersOptions}
              value={linkerField}
              placeholder="Select field to link..."
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} componentClass={ControlLabel}>Link by value from<span className="danger-red"> *</span></Col>
          <Col sm={9}>
            <Field
              fieldType="select"
              onChange={onChangeLinkerValue}
              options={csvHeaders}
              value={linkerValue}
              placeholder="Select CSV field to link..."
            />
          </Col>
        </FormGroup>
      </Panel>
    );
  };

  const renderUpdaters = () => {
    const updatersOptions = fields
      .filter(field => (field.hasOwnProperty('updater') && field.updater))
      .map(field => ({
        value: field.value,
        label: field.label,
      }));

    if (updatersOptions.length === 0) {
      return null;
    }

    return (
      <Panel header="Updater">
        <FormGroup>
          <Col sm={6} componentClass={ControlLabel} className="text-left">
            Unique field used for the update
            <span className="danger-red"> *</span>
            <Field
              fieldType="select"
              onChange={onChangeUpdaterField}
              options={updatersOptions}
              value={updaterField}
              placeholder="Select field to  update by..."
            />
          </Col>
          <Col sm={6} componentClass={ControlLabel} className="text-left">
            Match unique field to CSV column
            <span className="danger-red"> *</span>
            <Field
              fieldType="select"
              onChange={onChangeUpdaterValue}
              options={csvHeaders}
              value={updaterValue}
              placeholder="Select CSV field to update..."
            />
          </Col>
        </FormGroup>
        <hr className="mt0 mb10" />
        <FormGroup className="mb0">
          <Col sm={3} componentClass={ControlLabel}>
            Update Revision by Effective Date
            <span className="danger-red"> *</span>
          </Col>
          <Col sm={9}>
            <Field
              fieldType="select"
              onChange={onChangeEffectiveDate}
              options={csvHeaders}
              value={item.getIn(['map', 'effective_date'], '')}
              placeholder="Select CSV field to update..."
            />
          </Col>
        </FormGroup>
      </Panel>
    );
  };

  const renderFields = () => fields
    .filter(filterFields)
    .sort(soptFields)
    .map((field) => {
      const defaultValue = defaultFieldsValues.find((value, key) => key === field.value);
      return (
        <MapField
          key={`header_${field.value}`}
          defaultValue={defaultValue}
          mapFrom={field}
          mapTo={item.getIn(['map', field.value], '')}
          operation={operation}
          entity={item.get('entity')}
          options={csvHeaders}
          mapResult={item.get('map')}
          multiFieldAction={item.get('multiFieldAction')}
          mapperPrefix={mapperPrefix}
          onChange={props.onChange}
          onDelete={props.onDelete}
        />
      );
    });

  const renderContent = () => {
    if (fileContent.length === 0) {
      return (<Label bsStyle="default">Please upload a file.</Label>);
    }
    if (headers.length === 0) {
      return (<Label bsStyle="default">No CSV headers was found, please check your file.</Label>);
    }

    const mapfields = renderFields();
    const linkerfields = renderLinkers();
    const updaterfields = renderUpdaters();

    return (
      <div>
        { updaterfields !== null && operation === 'permanentchange' && updaterfields}
        <div>{mapfields}</div>
        {linkerfields !== null && linkerfields }
      </div>
    );
  };

  return (
    <Col md={12} className="StepMapper scrollbox">
      {renderContent()}
    </Col>
  );
};

StepMapper.defaultProps = {
  item: Immutable.Map(),
  fields: [],
  defaultFieldsValues: Immutable.Map(),
  ignoredHeaders: [],
  mapperPrefix: '',
  customFilterFields: () => true,
  onChange: () => {},
  onDelete: () => {},
};

StepMapper.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  fields: PropTypes.array,
  defaultFieldsValues: PropTypes.instanceOf(Immutable.Map),
  ignoredHeaders: PropTypes.array,
  mapperPrefix: PropTypes.string,
  customFilterFields: PropTypes.func,
  onChange: PropTypes.func,
  onDelete: PropTypes.func,
};

export default StepMapper;
