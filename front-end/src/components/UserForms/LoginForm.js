import React, { Component } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import { Form, FormGroup, FormControl, InputGroup, Button, Alert, Panel, Col, Row } from 'react-bootstrap';
import { Conflict409 } from '../StaticPages';
import { userDoLogin, sendResetMail, getAuthOptions} from '@/actions/userActions';
import ResetPassword from './ResetPassword';

class LoginForm extends Component {

  static propTypes = {
    auth: PropTypes.bool,
    error: PropTypes.string, // eslint-disable-line react/no-unused-prop-types
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    auth: false,
    error: '',
  };

  state = {
    username: '',
    password: '',
    error: '',
    progress: false,
    resetPassword: false,
    sending: false,
    authOptions: [],
  };

  componentDidMount() {
    this.props.dispatch(getAuthOptions())
      .then((response) => {
        const options = response.data[0]?.data?.details?.protocols || [];

        this.setState({ authOptions: options });
      })
      .catch((err) => {
        console.error('Failed to load auth protocols', err);
        this.setState({ authOptions: [] });
      });
  }

  componentWillReceiveProps(nextProps) {
    if (this.state.error !== nextProps.error) {
      this.setState({ error: nextProps.error });
    }
  }

  componentWillUnmount() {
    this.unmounted = true;
  }


  clickLogin = (e) => {
    const { username, password } = this.state;
    this.setState({ progress: true });
    e.preventDefault();
    this.props.dispatch(userDoLogin(username, password))
      .then(() => {
        if (this.unmounted !== true) {
          this.setState({ progress: false });
        }
      });
  }

  clickExternalProtocol = (protocol, providerName) => {
    this.props.dispatch(userDoLogin(null, null, protocol, providerName));
  }

  clickResetPassword = () => {
    this.setState({ resetPassword: true });
  }

  onChangeUsername = (e) => {
    const { value } = e.target;
    this.setState({ username: value, error: '' });
  }

  onChangePassword = (e) => {
    const { value } = e.target;
    this.setState({ password: value, error: '' });
  }

  onCancel = () => {
    this.setState({ resetPassword: false });
  }

  updateSending = (sending) => {
    this.setState({ sending });
  }

  onResetPass = (email) => {
    this.props.dispatch(sendResetMail(email)).then(this.afterSendingMail);
  }

  afterSendingMail = () => {
    this.setState({ sending: false });
    this.setState({ resetPassword: false });
  }

  renderLoginForm = () => {
    const { error, progress, resetPassword, sending, authOptions } = this.state;
    const externalOptions = authOptions.filter(opt => opt.type && opt.type.toLowerCase() !== 'internal');
    return (
      <Col md={4} mdOffset={4}>
        <Panel header="Please Sign In" className="login-panel">
          <Form onSubmit={this.clickLogin}>
            <fieldset>
              <FormGroup validationState={error.length > 0 ? 'error' : null}>
                <InputGroup>
                  <InputGroup.Addon><i className="fa fa-user fa-fw" /></InputGroup.Addon>
                  <FormControl
                    autoFocus
                    type="text"
                    placeholder="Email address"
                    value={this.state.username}
                    onChange={this.onChangeUsername}
                  />
                </InputGroup>
              </FormGroup>

              <FormGroup validationState={error.length > 0 ? 'error' : null}>
                <InputGroup>
                  <InputGroup.Addon><i className="fa fa-key fa-fw" /></InputGroup.Addon>
                  <FormControl
                    type="password"
                    placeholder="Password"
                    value={this.state.password}
                    onChange={this.onChangePassword}
                  />
                </InputGroup>
              </FormGroup>
              <Button type="submit" bsStyle="success" bsSize="large" block onClick={this.clickLogin} disabled={progress}>
                { progress && (<span><i className="fa fa-spinner fa-pulse" /> &nbsp;&nbsp;&nbsp;</span>) }
                Login
              </Button>
            </fieldset>
          </Form>

          {externalOptions.length > 0 && (
             <div style={{ margin: '15px 0', textAlign: 'center' }}>
                <span className="text-muted">- OR -</span>
             </div>
          )}
          {externalOptions.map((option, index) => {
            return (
              <div key={option.name || index}>
                {index > 0 && (
                  <div style={{ margin: '10px 0', textAlign: 'center' }}>
                    <span className="text-muted" style={{ fontSize: '0.9em' }}>- OR -</span>
                  </div>
                )}

                <div style={{ marginBottom: '10px' }}>
                  <Button
                    type="button"
                    bsStyle="primary"
                    bsSize="large"
                    block
                    onClick={() => this.clickExternalProtocol(option.type, option.name)}
                    disabled={progress}
                  >
                    <i className="fa fa-key fa-fw" /> &nbsp; Login with {option.label}
                  </Button>
                </div>
              </div>
            );
          })}
          {(error.length > 0) && <Alert bsStyle="danger" style={{ marginTop: 15 }} className="mb0">{error}</Alert>}
          <div style={{ borderTop: '1px solid #eee', marginTop: '15px' }}></div>
          <Button type="button" bsStyle="link" bsSize="small" block onClick={this.clickResetPassword} disabled={progress}>
            Forgot Your Password?
          </Button>
        </Panel>
        {resetPassword && (
          <ResetPassword
            sending={sending}
            updateSending={this.updateSending}
            onCancel={this.onCancel}
            onResetPass={this.onResetPass}
          />
        )}
      </Col>
    );
  }

  render() {
    const { auth } = this.props;
    if (auth) {
      return (
        <Conflict409 />
      );
    }
    return (
      <Row>{this.renderLoginForm()}</Row>
    );
  }

}


const mapStateToProps = state => ({
  auth: state.user.get('auth'),
  error: state.user.get('error'),
});

export default connect(mapStateToProps)(LoginForm);
