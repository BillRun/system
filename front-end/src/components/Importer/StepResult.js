import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Label, Panel } from 'react-bootstrap';
import { CSVLink } from 'react-csv';
import pluralize from 'pluralize';
import isNumber from 'is-number';
import { getConfig } from '@/common/Util';


const StepResult = (props) => {
  const { item } = props;
  const fileDelimiter = item.get('fileDelimiter', ',');
  const fileContent = item.get('fileContent', []) || [];
  const fileName = item.get('fileName', 'errors');
  const entity = item.get('entity', '');
  const result = item.get('importType', '') === 'manual_mapping'
    ? item.getIn(['result'], Immutable.Map())
    : (item.getIn(['result', 'imported_entities'], Immutable.List()) || Immutable.List());

  const getErrorCsvHeaders = () => (
    [...fileContent[0], 'import_error_message', 'import_error_row']
  );

  const getErrorCsvData = () => {
    const rows = [];
    result.forEach((status, rowIndex) => {
      if (status !== true) {
        if (fileContent[rowIndex - 1]) {
          rows.push([...fileContent[rowIndex - 1], status, rowIndex]);
        }
      }
    });
    return rows;
  };

  const rendeDetails = () => result
    .sortBy((status, key) => parseInt(key))
    .map((status, key) => (
      <dl className="mb5" key={`status_${key}`}>
        <dt>
          {isNumber(key) ? `row ${key}` : key}
          {status === true && <Label bsStyle="success" className="ml10">Success</Label>}
          {status === false && <Label bsStyle="info" className="ml10">No errors</Label>}
          {status !== false && status !== true && !Immutable.Iterable.isIterable(status) && <Label bsStyle="danger" className="ml10">{status}</Label>}
        </dt>
        { Immutable.Iterable.isIterable(status) && status.map((message, type) => {
          let messageStyle = 'default';
          if (type === 'warning') {
            messageStyle = 'warning';
          } else if (type === 'error') {
            messageStyle = 'danger';
          }
          return (
            <dd className="ml10" key={`status_error_${key}_${type}`}>
              - <Label bsStyle={messageStyle}>{message}</Label>
            </dd>
          )})
          .toList()
          .toArray()
        }
      </dl>
    ))
    .toList()
    .toArray()

  const renderStatus = () => {
    // New format that support Created and updated counters
    if (item.hasIn(['result','created']) || item.hasIn(['result','updated'])) {
      const created = item.getIn(['result', 'created'], 0);
      const updated = item.getIn(['result', 'updated'], 0);
      const itemName = getConfig(['systemItems', entity, 'itemName'], '');
      const nameCreated = pluralize(itemName, Number(created));
      const nameUpdated = pluralize(itemName, Number(updated));
      const errors = item.getIn(['result', 'general_errors'], Immutable.List());
      const errorsMessages = (
        <div className="mb5">
          <Label bsStyle="danger">Errors :</Label>
          <ol className="pt0 pb0">
            {errors.map((error, idx) => (<li key={`error_${idx}`}>{error}</li>)).toArray()}
          </ol>
        </div>
      );
      const warnings = item.getIn(['result', 'general_warnings'], Immutable.List());
      const warningMessages = (
        <div className="mb5">
          <Label bsStyle="warning">Warnings :</Label>
          <ol className="pt0 pb0">
            {warnings.map((warning, idx) => (<li key={`warning_${idx}`}>{warning}</li>)).toArray()}
          </ol>
        </div>
      );
      return (
        <div className="ml10">
          <Label bsStyle="success">Success :</Label>
          <ul className="pt0 pb0" >
            <li>Created {created} {nameCreated}</li>
            <li>Updated {updated} {nameUpdated}</li>
          </ul>
          {errors.size > 0 && errorsMessages}
          {warnings.size > 0 && warningMessages}
        </div>
      );
    }


    const result = item.getIn(['result'], Immutable.Map());
    // No resolts -> no imports
    if (result.size === 0) {
      return (
        <div className="ml10">
          <Label bsStyle="default">No records were imported</Label>
        </div>
      );
    }
    // All rows was successfully imported
    const allSuccess = result.every(status => status === true);
    if (allSuccess) {
      return (
        <div className="ml10">
          <Label bsStyle="success">{result.size} records were successfully imported</Label>
        </div>
      );
    }
    // All rows was faild imported
    const allFails = result.every(status => status !== true);
    if (allFails) {
      return (
        <div className="ml10">
          <Label bsStyle="danger">No records were imported. please fix the errors and try again.</Label>
        </div>
      );
    }
    // Mixed, some pased some fails
    const success = result.filter(status => status === true);
    let downlodCsvWithErrors = null;
    if (item.get('importType', ',') === 'manual_mapping') {
      const errorCsvData = getErrorCsvData();
      const errorCsvHeaders = getErrorCsvHeaders();
      downlodCsvWithErrors = (
        <CSVLink
          data={errorCsvData}
          headers={errorCsvHeaders}
          separator={fileDelimiter}
          filename={`errors_${fileName}`}
        >
          Click here to download CSV file with errors
        </CSVLink>
      );
    }
    return (
      <div className="ml10">
        <p>
          <Label bsStyle="success">{success.size}</Label> rows were successfully imported.<br />
          <Label bsStyle="danger">{result.size - success.size}</Label> rows failed to import.<br />
          Please remove successfully imported rows from the file, fix the errors and try again.
        </p>
        {downlodCsvWithErrors}
      </div>
    );
  };

  return (
    <div className="StepResult">
      <h4>Import status</h4>
      {renderStatus()}
      <br />
      <Panel header={<span>Details</span>} collapsible className="collapsible">
        {rendeDetails()}
      </Panel>
    </div>
  );
};

StepResult.defaultProps = {
  item: Immutable.Map(),
};

StepResult.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
};

export default StepResult;
