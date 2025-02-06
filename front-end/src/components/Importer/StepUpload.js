import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col, HelpBlock, Button } from 'react-bootstrap';
import Papa from 'papaparse';
import filesize from 'file-size';
import { sentenceCase } from 'change-case';
import Field from '@/components/Field';
import { getConfig, formatSelectOptions, getFieldName } from '@/common/Util';
import PlaysSelector from '../Plays/PlaysSelector';
import { Actions, AddFileButton } from '@/components/Elements';


class StepUpload extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    mapperOptions: PropTypes.instanceOf(Immutable.List),
    mapperName: PropTypes.string,
    delimiterOptions: PropTypes.arrayOf(
      PropTypes.shape({
        value: PropTypes.string,
        label: PropTypes.string,
      }),
    ),
    typeSelectOptions: PropTypes.arrayOf(
      PropTypes.shape({
        value: PropTypes.string,
        label: PropTypes.string,
      }),
    ),
    entityOptions: PropTypes.arrayOf(PropTypes.string),
    showPlay: PropTypes.bool,
    onChange: PropTypes.func,
    onDelete: PropTypes.func,
    onSelectMapping: PropTypes.func,
  }

  static defaultProps = {
    item: Immutable.Map(),
    mapperOptions: Immutable.List(),
    delimiterOptions: [
      { value: '	', label: 'Tab' }, // eslint-disable-line no-tabs
      { value: ' ', label: 'Space' },
      { value: ',', label: 'Comma' },
    ],
    entityOptions: [],
    typeSelectOptions: [],
    showPlay: false,
    mapperName: '',
    onChange: () => {},
    onDelete: () => {},
    onSelectMapping: () => {},
  }

  state = {
    fileError: null,
    predefinedFileError: null,
    delimiterError: null,
    operations: Immutable.List(),
  }

  componentDidMount() {
    const { item } = this.props;
    this.setoperation(item.get('entity', ''), item.get('operation', ''));
  }

  componentWillReceiveProps(nextProps) {
    const { item, typeSelectOptions } = nextProps;
    if (this.props.item.get('entity', '') !== item.get('entity', '')) {
      this.setoperation(item.get('entity', ''), item.get('operation', ''));
    }
    const importType = item.get('importType', '');
    if (typeSelectOptions && typeSelectOptions.length === 1 && typeSelectOptions[0].value !== importType) {
      this.onChangeImportType(typeSelectOptions[0].value);
    }
    const oldImportType = this.props.item.get('importType', '');
    // remove files if mapper was changed
    if (oldImportType !== '' && oldImportType !== importType) {
      this.props.onDelete('files');
    }
  }

  setoperation = (entity, curroperation) => {
    const operationsConfig = getConfig(['import', 'allowed_entities_actions'], Immutable.List());
    const operations = operationsConfig
      .filter(entities => entities.includes(entity))
      .keySeq()
      .map(option => Immutable.Map({
        value: option,
        label: sentenceCase(getFieldName(option, 'import')),
      }))
      .toList();
    if (operations.size > 0 && (curroperation === '' || operations.filter(op => op.get('value', '') === curroperation).size === 0)) {
      this.props.onChange('operation', operations.getIn([0, 'value'], ''));
    }
    this.setState({ operations });
  }

  resetFile = () => {
    this.props.onChange('fileContent', '');
    this.props.onChange('fileName', '');
  }

  onChangeOperation = (operation) => {
    this.props.onChange('operation', operation);
  }

  onParseCsvComplete = (results, file) => {
    if (results.errors.length === 0) {
      this.props.onChange('file', file);
      this.props.onChange('fileContent', results.data);
      this.props.onChange('fileName', file.name);
      this.setState({ fileError: null });
    } else {
      this.setState({ fileError: 'Error in CSV file' });
      this.resetFile();
    }
  }

  onParseScvError = (error, file) => { // eslint-disable-line no-unused-vars
    this.setState({ fileError: 'Error in CSV file' });
    this.resetFile();
  }

  onFileUpload = (e) => {
    const { item } = this.props;
    const { files } = e.target;
    if (this.isValidFile(files[0])) {
      const delimiter = item.get('fileDelimiter', ',');
      Papa.parse(files[0], {
        skipEmptyLines: true,
        delimiter,
        header: false,
        preview: 2,
        complete: this.onParseCsvComplete,
        error: this.onParseScvError,
      });
    } else {
      const maxBytesSize = getConfig('importMaxSize', 8) * 1024 * 1024;
      this.setState({ fileError: `Max file size is ${filesize(maxBytesSize).human()}` });
      e.target.value = null;
    }
  }

  onFileReset = (e) => {
    e.target.value = null;
    this.resetFile();
  }

  onUploadPredefinedFile = (e) => {
    const { item } = this.props;
    const { files } = e.target;
    let newFiles = [];
    if (files.length > 0) {
      for (let i = 0; i < files.length; i++) {
        newFiles.push(files[i]);
      }
    }
    this.props.onChange('files', [...item.get('files', []), ...newFiles]);
    e.target.value = null;
  }

  onRemovePredefinedFile = (idx = -1) => {
    const { item } = this.props;
    const removed = (idx === -1) ? [] : item.get('files', []).filter((file, index) => index !== idx);
    if (removed.length === 0) {
      this.props.onDelete('files');
    } else {
      this.props.onChange('files', removed);
    }
  }

  onChangeDelimiter = (value) => {
    if (value.length) {
      this.props.onChange('fileDelimiter', value);
      this.setState({ delimiterError: null });
      // this.resetFile(); can be called to programmatically remove file
    } else {
      this.props.onDelete('fileDelimiter');
      this.setState({ delimiterError: 'Delimiter is required' });
    }
  }

  onChangeEntity = (value) => {
    this.props.onDelete('map');
    this.props.onDelete('multiFieldAction');
    this.props.onDelete('play');
    this.props.onDelete('importType');
    if (value.length) {
      this.props.onChange('entity', value);
    } else {
      this.props.onDelete('entity');
    }
  }

  onChangeImportType = (value) => {
    if (value.length) {
      this.props.onChange('importType', value);
    } else {
      this.props.onDelete('importType');
    }
  }

  onSelectExistingMapper = (mapperName) => {
    this.props.onSelectMapping(mapperName);
  }

  onChangePlay = (play) => {
    this.props.onDelete('map');
    this.props.onDelete('multiFieldAction');
    if (play.length) {
      this.props.onChange('play', play);
    } else {
      this.props.onDelete('play');
    }
  }

  isValidFile = (file) => {
    const maxBytesSize = getConfig('importMaxSize', 8) * 1024 * 1024;
    return file.size <= maxBytesSize;
  };

  createEntityTypeOptions = entityOptions => entityOptions.map(entityKey => ({
    value: entityKey,
    label: sentenceCase(getConfig(['systemItems', entityKey, 'itemName'], entityKey)),
  }));

  createSavedMapperOptions = () => {
    const { mapperOptions } = this.props;
    return mapperOptions.map(mapper => ({
      value: mapper.get('label', ''),
      label: mapper.get('label', ''),
    }))
    .toList();
  }

  predefinedFileActions = () => [{
    type: 'remove',
    helpText: 'Remove file',
    onClick: this.onRemovePredefinedFile,
    actionClass: "pl0 pr0"
  }];

  render() {
    const { delimiterError, predefinedFileError, fileError, operations } = this.state;
    const { item,
      delimiterOptions,
      entityOptions,
      mapperName,
      mapperOptions,
      showPlay,
      typeSelectOptions,
    } = this.props;
    const delimiter = item.get('fileDelimiter', '');
    const operation = item.get('operation', '');
    const entity = item.get('entity', '');
    const fileName = item.get('fileName', '');
    const importType = item.get('importType', '');
    const isSingleEntity = (entityOptions && entityOptions.length === 1);
    const entitySeletOptions = this.createEntityTypeOptions(entityOptions);
    const fileParsed = fileName !== '';
    const operationSelectOptions = operations.map(formatSelectOptions).toJS();
    const mapperSelectOptions = this.createSavedMapperOptions().toJS();
    const predefinedFileActions = this.predefinedFileActions();
    const isTypePlugin = typeSelectOptions
      .filter(option => option.type === 'plugin')
      .map(option => option.value)
      .includes(importType);
    return (
      <div className="StepUpload">
        { !isSingleEntity && (
          <FormGroup>
            <Col sm={3} componentClass={ControlLabel}>Entity</Col>
            <Col sm={9}>
              <Field
                fieldType="select"
                onChange={this.onChangeEntity}
                options={entitySeletOptions}
                value={entity}
                placeholder="Select entity to import...."
                clearable={false}
              />
            </Col>
          </FormGroup>
        )}
        {(typeSelectOptions.length !== 1) && (
          <FormGroup>
            <Col sm={3} componentClass={ControlLabel}>Import option</Col>
            <Col sm={9}>
              <Field
                fieldType="select"
                onChange={this.onChangeImportType}
                options={typeSelectOptions}
                value={importType}
                placeholder="Select import option...."
                clearable={false}
              />
            </Col>
          </FormGroup>
        )}

        {(!['manual_mapping'].includes(importType)) && (
          <FormGroup validationState={predefinedFileError === null ? null : 'error'}>
            <Col sm={3} componentClass={ControlLabel}>Upload CSV</Col>
            <Col sm={9}>
              <dl>
                <dt>
                  <AddFileButton onClick={this.onUploadPredefinedFile} />
                  {predefinedFileError !== null && <HelpBlock>{predefinedFileError}</HelpBlock>}
                </dt>
                {item.get('files', []).map((file, idx) => (
                  <dd style={{ height: 28 }} key={`${idx}-${file.name}`}>
                    <span className="inline ml10 mr10">
                      <Actions actions={predefinedFileActions} data={idx} />
                    </span>
                    {file.name}
                  </dd>
                ))}
              </dl>
            </Col>
          </FormGroup>
        )}

        {['manual_mapping'].includes(importType) && (
          <FormGroup validationState={delimiterError === null ? null : 'error'}>
            <Col sm={3} componentClass={ControlLabel}>Delimiter</Col>
            <Col sm={9}>
              <Field
                fieldType="select"
                allowCreate={true}
                onChange={this.onChangeDelimiter}
                options={delimiterOptions}
                value={delimiter}
                placeholder="Select or add new"
                addLabelText="{label}"
                clearable={false}
                disabled={fileParsed}
              />
              { delimiterError !== null && <HelpBlock>{delimiterError}.</HelpBlock>}
              { fileParsed && <HelpBlock className="mb0">To change delimiter please remove CSV file.</HelpBlock>}
            </Col>
          </FormGroup>
        )}
        {['manual_mapping'].includes(importType) && (
          <FormGroup validationState={fileError === null ? null : 'error'}>
            <Col sm={3} componentClass={ControlLabel}>Upload CSV</Col>
            <Col sm={9}>
              <div style={{ paddingTop: 5 }} >
                {fileName !== ''
                  ? (<p style={{ margin: 0 }}>
                    {fileName}
                    <Button
                      bsStyle="link"
                      title="Remove file"
                      onClick={this.onFileReset}
                      style={{ padding: '0 0 0 10px', marginBottom: 1 }}
                    >
                      <i className="fa fa-minus-circle danger-red" />
                    </Button>
                  </p>)
                  : <input type="file" accept=".csv" onChange={this.onFileUpload} onClick={this.onFileReset} />
                }
                {fileError !== null && <HelpBlock>{fileError}.</HelpBlock>}
              </div>
            </Col>
          </FormGroup>
        )}
        {operations.size > 1 && isTypePlugin === false && (
          <FormGroup validationState={delimiterError === null ? null : 'error'}>
            <Col sm={3} componentClass={ControlLabel}>Import Action</Col>
            <Col sm={9}>
              <Field
                fieldType="select"
                onChange={this.onChangeOperation}
                options={operationSelectOptions}
                value={operation}
                placeholder="Select import action"
                addLabelText="{label}"
                clearable={false}
                disabled={operations.size === 1 || entity === ''}
              />
            </Col>
          </FormGroup>
        )}
        {showPlay && (
          <PlaysSelector
            entity={item}
            onChange={this.onChangePlay}
            labelStyle={{ sm:3, lg: 3 }}
            fieldStyle={{ sm:9, lg: 9 }}
          />
        )}
        {['manual_mapping'].includes(importType) && mapperOptions.size > 0 && (
        <FormGroup>
          <Col sm={3} componentClass={ControlLabel}>Saved Mapper</Col>
          <Col sm={9}>
            <Field
              fieldType="select"
              onChange={this.onSelectExistingMapper}
              options={mapperSelectOptions}
              value={mapperName}
              placeholder="Select saved mapper"
              clearable={true}
            />
          </Col>
        </FormGroup>
        )}
      </div>
    );
  }
}

export default StepUpload;
