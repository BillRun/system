
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import Field from '@/components/Field';
import { Col, FormGroup, ControlLabel } from 'react-bootstrap';
import { getConfig, getFieldName } from '@/common/Util'


class SenderFtp extends Component {

  static propTypes = {
    data: PropTypes.instanceOf(Immutable.Map),
    onChange: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
  };

  static defaultProps = {
    data: Immutable.Map(),
  };

  static basePath = ['senders', 'connections', 0];
  static namePath = [...SenderFtp.basePath, 'name'];
  static hostPath = [...SenderFtp.basePath, 'host'];
  static userPath = [...SenderFtp.basePath, 'user'];
  static passwordPath = [...SenderFtp.basePath, 'password'];
  static dirPath = [...SenderFtp.basePath, 'remote_directory'];
  static passivePath = [...SenderFtp.basePath, 'passive'];
  static senderTypePath = [...SenderFtp.basePath, 'connection_type'];
  
  static senderTypes = getConfig(['exportGenerator', 'senderTypes'], Immutable.List());

  onChangeName = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.namePath, value);
  }

  onChangeHost = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.hostPath, value);
  }

  onChangeUser = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.userPath, value);
  }

  onChangePassword = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.passwordPath, value);
  }

  onChangeDir = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.dirPath, value);
  }

  onChangePassive = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.passivePath, value);
  }

  onChangeSenderType = (e) => {
    const { value } = e.target;
    this.props.onChange(SenderFtp.senderTypePath, value);
  }

  renderSenderType = (name, type) => {
    const { data } = this.props; 
    return (
      <Col sm={3} key={type}>
        <Field
          fieldType="radio"
          onChange={this.onChangeSenderType}
          name={`sender-type-${type}`}
          value={type}
          label={name}
          checked={data.getIn(SenderFtp.senderTypePath, '') === type}
        />
      </Col>
    );
  }

  renderSenderTypes = () => SenderFtp.senderTypes
    .map(this.renderSenderType)
    .toList()
    .toArray();

  render() {
    const { data } = this.props;
    return (
      <>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('sender_type', 'export_generator', 'Sender Type')}
          </Col>
          <Col sm={8} lg={9}>
            {this.renderSenderTypes()}
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('name', 'export_generator', 'Name')}
          </Col>
          <Col sm={8} lg={9}>
            <Field value={data.getIn(SenderFtp.namePath, '')} onChange={this.onChangeName}/>
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('host', 'export_generator', 'Host')}
          </Col>
          <Col sm={8} lg={9}>
            <Field value={data.getIn(SenderFtp.hostPath, '')} onChange={this.onChangeHost}/>
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('user', 'export_generator', 'User')}
          </Col>
          <Col sm={8} lg={9}>
            <Field value={data.getIn(SenderFtp.userPath, '')} onChange={this.onChangeUser}/>
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('password', 'export_generator', 'Password')}
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="password"
              value={data.getIn(SenderFtp.passwordPath, '')}
              onChange={this.onChangePassword}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('dir', 'export_generator', 'Remote Directory')}
          </Col>
          <Col sm={8} lg={9}>
            <Field value={data.getIn(SenderFtp.dirPath, '')} onChange={this.onChangeDir}/>
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('passive', 'export_generator', 'Passive')}
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="checkbox"
              onChange={this.onChangePassive}
              value={data.getIn(SenderFtp.passivePath, '')}
              className="input-checkbox-fix-height"
            />
          </Col>
        </FormGroup>
      </>
    );
  }
}

export default SenderFtp;
