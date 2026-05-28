import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, FormGroup, Col, FormControl, ControlLabel } from 'react-bootstrap';
import filesize from 'file-size';
import { saveFile } from '@/actions/settingsActions';
import { showWarning } from '@/actions/alertsActions';
import { getConfig } from '@/common/Util';

class Tenant extends Component {

  static propTypes = {
    onChange: PropTypes.func.isRequired,
    data: PropTypes.instanceOf(Immutable.Map),
    logo: PropTypes.string.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    data: Immutable.Map(),
  };

  state = {
    logo: '',
  }

  onChangeField = (e) => {
    const { id, value } = e.target;
    this.props.onChange('tenant', id, value);
  }

  updateLogoPreview = (input) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      this.setState({ logo: e.target.result });
    };
    reader.readAsDataURL(input);
  }

  uploadFile = (e) => {
    const { files } = e.target;
    if (files.length > 0) {
      const maxBytesSize = getConfig('logoMaxSize', 2) * 1024 * 1024;
      if (files[0].size <= maxBytesSize) {
        saveFile(files[0], { billtype: 'logo' });
        this.props.onChange('tenant', 'logo', files[0].name);
        this.updateLogoPreview(files[0]);
      } else {
        this.props.dispatch(showWarning(`Max file size is ${filesize(maxBytesSize).human()}`));
      }
    }
  };

  render() {
    const { data } = this.props;
    const logo = this.state.logo.length > 0 ? this.state.logo : this.props.logo;
    return (
      <div className="Tenant">
        <Form horizontal>
          <FormGroup controlId="name" key="name">
            <Col componentClass={ControlLabel} md={2}>
              Name
            </Col>
            <Col sm={6}>
              <FormControl type="text" name="name" onChange={this.onChangeField} value={data.get('name', '')} />
            </Col>
          </FormGroup>

          <FormGroup controlId="address" key="address">
            <Col componentClass={ControlLabel} md={2}>
              Address
            </Col>
            <Col sm={6}>
              <FormControl componentClass="textarea" name="address" onChange={this.onChangeField} value={data.get('address', '')} />
            </Col>
          </FormGroup>

          <FormGroup controlId="phone" key="phone">
            <Col componentClass={ControlLabel} md={2}>
              Phone
            </Col>
            <Col sm={6}>
              <FormControl type="text" name="phone" onChange={this.onChangeField} value={data.get('phone', '')} />
            </Col>
          </FormGroup>

          <FormGroup controlId="email" key="email">
            <Col componentClass={ControlLabel} md={2}>
              Email
            </Col>
            <Col sm={6}>
              <FormControl type="email" name="email" onChange={this.onChangeField} value={data.get('email', '')} />
            </Col>
          </FormGroup>

          <FormGroup controlId="website" key="website">
            <Col componentClass={ControlLabel} md={2}>
              Website
            </Col>
            <Col sm={6}>
              <FormControl type="text" name="website" onChange={this.onChangeField} value={data.get('website', '')} />
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} md={2}>
              Logo
            </Col>
            <Col sm={6}>
              <FormControl type="file" name="logo" onChange={this.uploadFile} />
              { logo.length > 0 && <img src={logo} style={{ height: 100, marginTop: 20 }} alt="Logo" />}
            </Col>
          </FormGroup>
        </Form>
      </div>
    );
  }
}


const mapStateToProps = state => ({
  logo: state.settings.getIn(['files', 'logo']),
});

export default connect(mapStateToProps)(Tenant);
