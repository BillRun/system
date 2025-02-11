import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { titleCase } from 'change-case';
import { Form, FormGroup, ControlLabel, Col, Row, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';


export default class User extends Component {

  static propTypes = {
    user: PropTypes.instanceOf(Immutable.Map),
    action: PropTypes.string,
    availableRoles: PropTypes.arrayOf(PropTypes.string),
    onUpdateValue: PropTypes.func.isRequired,
    onDeleteValue: PropTypes.func.isRequired,
  }

  static defaultProps = {
    user: Immutable.Map(),
    availableRoles: ['admin', 'read', 'write', 'reports'],
    action: 'create',
  };

  state = {
    enableChangePassword: this.props.action === 'create',
    password: '',
    password1: '',
    errors: {
      password: '',
      password1: '',
    },
  }

  componentDidMount() {
    const { action } = this.props;
    if (action === 'create') {
      this.initDefaultValues();
    } else {
      this.props.onDeleteValue('password'); // remove user hashed password, to not send it back to BE on save
    }
  }

  initDefaultValues = () => {
    this.props.onUpdateValue('roles', ['read']);
  }

  onPasswordChange = (e) => {
    const { errors } = this.state;
    const value = e.target.value.trim();
    let errorMessage = '';
    if (value.length === 0) {
      errorMessage = 'Password is required';
    }
    this.props.onUpdateValue('password', '');
    this.setState({
      errors: Object.assign({}, errors, { password: errorMessage }),
      password: value,
      password1: '',
    });
  };

  onPassword1Change = (e) => {
    const { errors, password } = this.state;
    const value = e.target.value.trim();

    let errorMessage = '';
    if (value.length === 0) {
      errorMessage = 'Please fill confirm password';
    }
    if (value !== password) {
      errorMessage = 'Passwords do not match';
    }

    if (errorMessage.length === 0) {
      this.props.onUpdateValue('password', value);
    } else {
      this.props.onUpdateValue('password', '');
    }
    this.setState({
      errors: Object.assign({}, errors, { password1: errorMessage }),
      password1: value,
    });
  };

  onUserNameChange = (e) => {
    const { value } = e.target;
    this.props.onUpdateValue('username', value);
  }

  onChangeRoles = (roles) => {
    const userRoles = roles.length > 0 ? roles.split(',') : [];
    this.props.onUpdateValue('roles', userRoles);
  }

  onChangeRole = (role) => {
    this.props.onUpdateValue('role', role);
  }

  onEnableChangePassword = (e) => {
    const { errors } = this.state;
    const { checked } = e.target;
    if (!checked) {
      this.props.onDeleteValue('password');
      this.setState({
        enableChangePassword: checked,
        errors: Object.assign({}, errors, { password1: '', password: '' }),
        password: '',
        password1: '',
      });
    } else {
      this.props.onUpdateValue('password', '');
      this.setState({ enableChangePassword: checked });
    }
  }

  renderChangePassword = () => {
    const { action } = this.props;
    const { password, password1, enableChangePassword, errors } = this.state;
    const hasError = errors.password.length > 0 || errors.password1.length > 0;

    return (
      <span>
        { action !== 'create' &&
        <FormGroup validationState={hasError ? 'error' : null} >
          <Col componentClass={ControlLabel} sm={3} lg={2}>&nbsp;</Col>
          <Col sm={8} lg={9}>
            <label htmlFor="enable-change-password">
              <input id="enable-change-password" type="checkbox" checked={enableChangePassword} onChange={this.onEnableChangePassword} style={{ verticalAlign: 'text-bottom' }} />
              &nbsp;Enable Password Change
            </label>
          </Col>
        </FormGroup>
      }

        <FormGroup validationState={hasError ? 'error' : null} >
          <Col componentClass={ControlLabel} sm={3} lg={2}>Password</Col>
          <Col sm={8} lg={9}>
            <input onChange={this.onPasswordChange} value={password} className="form-control" id="password" type="password" disabled={!enableChangePassword} />
            { errors.password.length > 0 && <HelpBlock>{errors.password}</HelpBlock> }
          </Col>
        </FormGroup>
        <FormGroup validationState={errors.password1.length > 0 ? 'error' : null} >
          <Col componentClass={ControlLabel} sm={3} lg={2}>Confirm Password</Col>
          <Col sm={8} lg={9}>
            <input onChange={this.onPassword1Change} value={password1} className="form-control" id="password1" type="password" disabled={!enableChangePassword} />
            { errors.password1.length > 0 && <HelpBlock>{errors.password1}</HelpBlock> }
          </Col>
        </FormGroup>
      </span>
    );
  }

  render() {
    const { user, availableRoles } = this.props;
    const rolesOptions = availableRoles.map(role => ({
      value: role,
      label: titleCase(role),
    }));
    return (
      <Row>
        <Col lg={12}>
          <Form horizontal>
            <FormGroup>
              <Col componentClass={ControlLabel} sm={3} lg={2}>User Name (email)</Col>
              <Col sm={8} lg={9}>
                <Field onChange={this.onUserNameChange} value={user.get('username', '')} />
              </Col>
            </FormGroup>

            <FormGroup>
              <Col componentClass={ControlLabel} sm={3} lg={2}>Roles</Col>
              <Col sm={8} lg={9}>
                <Field
                  fieldType="select"
                  multi={true}
                  value={user.get('roles', []).join(',')}
                  options={rolesOptions}
                  onChange={this.onChangeRoles}
                  placeholder="Add role..."
                />
              </Col>
            </FormGroup>

            {this.renderChangePassword()}

          </Form>
        </Col>
      </Row>
    );
  }
}
