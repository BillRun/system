import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Col, Panel, FormGroup, Button } from 'react-bootstrap';
import { Actions } from '@/components/Elements';
import Field from '@/components/Field';
import { buildRequestUrl } from '../../../common/Api';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import { removeReceiver } from '@/actions/inputProcessorActions';

class Connection extends Component {
  static propTypes = {
    index: PropTypes.number.isRequired,
    receiver: PropTypes.instanceOf(Immutable.Map).isRequired,
    receiverTypes: PropTypes.instanceOf(Immutable.Map),
    onSetReceiverField: PropTypes.func.isRequired,
    onSetReceiverCheckboxField: PropTypes.func.isRequired,
    OnChangeUploadingFile: PropTypes.func.isRequired,
    onCancelKeyAuth: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    settings: Immutable.Map(),
    receiverTypes: Immutable.Map({ ftp: 'FTP', ssh: 'SFTP' }),
  };

  constructor(props) {
    super(props);
    this.state = {
      isReceiverOpen: props.index === 0,
    };
  }

  componentDidMount() {
    const { receiver, index } = this.props;
    this.initDefaultValues(receiver, index);
  }

  initDefaultValues = (receiver, key) => {
    if (receiver.get('receiver_type', null) === null) {
      const receiverType = { target: { value: 'ftp', id: `receiver_type-${key}` } };
      this.props.onSetReceiverField(receiverType, key);
    }
    if (receiver.get('passive', null) === null) {
      const passive = { target: { checked: false, id: `passive-${key}` } };
      this.props.onSetReceiverCheckboxField(passive, key);
    }
    if (receiver.get('delete_received', null) === null) {
      const deleteReceived = { target: { checked: false, id: `delete_received-${key}` } };
      this.props.onSetReceiverCheckboxField(deleteReceived, key);
    }
  }

  onRemoveReceiver = () => {
    const { index } = this.props;
    this.props.dispatch(removeReceiver(index));
  }

  toggleShowDetails = () => {
    const { isReceiverOpen } = this.state;
    this.setState(() => ({ isReceiverOpen: !isReceiverOpen }));
  }

  afterUpload = (res, fileName) => {
    const { index } = this.props;

    if (res.desc === 'success') {
      this.props.dispatch(showSuccess(res.details.message));
      this.props.onSetReceiverField({ target: { value: res.details.path, id: `key-${index}` } }, index);
      this.props.onSetReceiverField({ target: { value: fileName, id: `key_label-${index}` } }, index);
      this.props.OnChangeUploadingFile();
    } else {
      this.props.dispatch(showDanger(res.details.message));
    }
  }

  onChangeFileSelect = (e) => {
    const { files } = e.target;
    const { fileType } = this.props;
    const currentFile = files[0];
    if (currentFile.size >= 1048576) {
      this.props.dispatch(showDanger('Please choose file smaller than 1MB'));
      return;
    }
    const formData = new FormData();
    formData.append('file', currentFile, currentFile.name);
    formData.append('category', 'key');
    formData.append('file_type', fileType);
    const xhr = new XMLHttpRequest();
    const query = { api: 'uploadedfile' };
    const uploadFileApiUrl = buildRequestUrl(query);
    this.props.OnChangeUploadingFile();
    xhr.open('POST', uploadFileApiUrl, true);
    xhr.withCredentials = true;
    xhr.addEventListener('load', () => {
      const res = JSON.parse(xhr.responseText);
      this.afterUpload(res, currentFile.name);
    });
    xhr.send(formData);
  }

  onClickFileSelect = (e) => {
    e.target.value = null;
  };

  onCancelKeyAuth = () => {
    const { index } = this.props;
    this.props.onCancelKeyAuth(index);
    this.props.dispatch(showSuccess('Key was removed successfuly'));
  }

  onChangeReceiverType = (e) => {
    const { value } = e.target;
    const { index } = this.props;

    this.props.onSetReceiverField({ target: { value, id: `receiver_type-${index}` } }, index);
  }

  renderReceiverType = (name, type) => {
    const { index, receiver } = this.props;
    return (
      <Col sm={3} key={type}>
        <Field
          fieldType="radio"
          onChange={this.onChangeReceiverType}
          name={`receiver_type-${index}`}
          value={type}
          label={name}
          checked={receiver.get('receiver_type', '') === type}
        />
      </Col>
    );
  }

  renderReceiverTypes = () => {
    const { receiverTypes } = this.props;
    return receiverTypes
      .map(this.renderReceiverType)
      .toList()
      .toArray();
  }

  renderPanelHeader = () => {
    const { receiver, fileType } = this.props;
    const keyLabel = receiver.get('key_label', fileType);
    return (
      <div style={{ fontSize: 12, fontWeight: 'bold' }}>
        {keyLabel}
        <div className="pull-right">
          <Button onClick={this.onCancelKeyAuth} bsSize="small" bsStyle="link" style={{ padding: 0 }} >
            <i className="fa fa-trash-o danger-red" />
          </Button>
        </div>
      </div>
    );
  }

  onChangeReceiverField = (e) => {
    const { index } = this.props;
    this.props.onSetReceiverField(e, index);
  }

  onSetReceiverCheckboxField = (e) => {
    const { index } = this.props;
    this.props.onSetReceiverCheckboxField(e, index);
  }

  renderReceiver = () => {
    const { index, receiver } = this.props;

    const periodOptions = [{ min: 1, label: '1 Minute' },
                            { min: 15, label: '15 Minutes' },
                            { min: 30, label: '30 Minutes' },
                            { min: 60, label: '1 Hour' },
                            { min: 360, label: '6 Hours' },
                            { min: 720, label: '12 Hours' },
                            { min: 1440, label: '24 Hours' }].map((opt, key) => (
                              <option value={opt.min} key={key}>{opt.label}</option>
                            ));

    return (
      <div>
        <div className="form-group">
          <label htmlFor="name" className="col-xs-3 control-label">Receiver Type</label>
          <div className="col-xs-6">
            {this.renderReceiverTypes()}
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="name" className="col-xs-3 control-label">Name</label>
          <div className="col-xs-6">
            <input className="form-control" id={`name-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('name', '')} />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="host" className="col-xs-3 control-label">Host</label>
          <div className="col-xs-6">
            <input className="form-control" id={`host-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('host', '')} />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="user" className="col-xs-3 control-label">User</label>
          <div className="col-xs-6">
            <input className="form-control" id={`user-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('user', '')} />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="password" className="col-xs-3 control-label">Password</label>
          <div className="col-xs-6">
            <input type="password" className="form-control" id={`password-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('password', '')} />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="remote_directory" className="col-xs-3 control-label">Directory</label>
          <div className="col-xs-6">
            <input className="form-control" id={`remote_directory-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('remote_directory', '')} />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="filename_regex" className="col-xs-3 control-label">Regex</label>
          <div className="col-xs-6">
            <input className="form-control" id={`filename_regex-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('filename_regex', '')} />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="period" className="col-xs-3 control-label">Period</label>
          <div className="col-xs-6">
            <select className="form-control" id={`period-${index}`} onChange={this.onChangeReceiverField} value={receiver.get('period', '')}>
              { periodOptions }
            </select>
          </div>
        </div>
        {receiver.get('receiver_type', '') !== 'ftp' &&
        <div className="form-group">
          <label htmlFor="uploadFile" className="col-xs-3 control-label">Key</label>
          <div className="col-xs-6">
            { receiver.get('key', false) === false && (
              <input name="file" type="file" onClick={this.onClickFileSelect} onChange={this.onChangeFileSelect} />
            )}
            { receiver.get('key', false) !== false && (
              <Panel header={this.renderPanelHeader()} className="mb0" />
            )}
          </div>
        </div>}
        <div className="form-group">
          <label htmlFor="delete_received" className="col-xs-3 control-label">Delete received files from remote</label>
          <div className="col-xs-6">
            <input
              type="checkbox"
              id={`delete_received-${index}`}
              style={{ marginTop: 12 }}
              onChange={this.onSetReceiverCheckboxField}
              checked={receiver.get('delete_received', false)}
              value="1"
            />
          </div>
        </div>
        <div className="form-group">
          <label htmlFor="passive" className="col-xs-3 control-label">Passive mode</label>
          <div className="col-xs-6">
            <input
              type="checkbox"
              id={`passive-${index}`}
              style={{ marginTop: 12 }}
              onChange={this.onSetReceiverCheckboxField}
              checked={receiver.get('passive', false)}
              value="1"
              disabled={receiver.get('receiver_type', '') !== 'ftp'}
            />
          </div>
        </div>
      </div>
    );
  }

  getReceiverActions = () => {
    const { index } = this.props;
    const { isReceiverOpen } = this.state;
    const showRemove = index !== 0;
    return ([
      { type: 'edit', onClick: this.toggleShowDetails, show: !isReceiverOpen },
      { type: 'collapse', onClick: this.toggleShowDetails, show: isReceiverOpen },
      { type: 'remove', onClick: this.onRemoveReceiver, enable: showRemove },
    ]);
  };

  render() {
    const { index } = this.props;
    const { isReceiverOpen } = this.state;
    return (
      <FormGroup key={`connection_${index}`} className="mb0">
        <Col sm={12}>
          <div style={{ paddingRight: 100, display: 'inline-block' }}>
            {`Receiver ${index + 1}`}
          </div>
          <span style={{ marginLeft: -100, paddingRight: 15 }} className="pull-right List row">
            <Actions actions={this.getReceiverActions()} />
          </span>
        </Col>
        <Col sm={12}>
          <Panel collapsible expanded={isReceiverOpen}>
            { this.renderReceiver() }
          </Panel>
        </Col>
      </FormGroup>
    );
  }
}


export default connect(null)(Connection);
