import React, { Component } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import { withRouter } from 'react-router';
import { Col, FormGroup, HelpBlock, InputGroup, Form, Panel, Button, FormControl } from 'react-bootstrap';
import { savePassword } from '@/actions/userActions';
import { idSelector, sigSelector, timestampSelector, usernameSelector } from '@/selectors/entitySelector';
import Field from '@/components/Field';


class ChangePassword extends Component {

  static propTypes = {
    auth: PropTypes.bool,
    itemId: PropTypes.string,
    signature: PropTypes.string,
    timestamp: PropTypes.string,
    username: PropTypes.string,
    dispatch: PropTypes.func.isRequired,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
  };

  static defaultProps = {
    auth: false,
    itemId: '',
    signature: '',
    timestamp: '',
    username: '',
  };

  state = {
    password: '',
    password1: '',
    error: '',
    sending: false,
  }

  componentWillMount() {
    if (this.props.auth === true) {
      this.props.router.push('/');
    }
  }

  validate = () => {
    const { password, password1 } = this.state;
    return (password === password1 && password !== '');
  }

  onPasswordChange = (e) => {
    const { password1 } = this.state;
    const value = e.target.value.trim();
    let errorMessage = '';
    if (value.length < 1) {
      errorMessage = 'Password is required';
    } else if (password1.length > 0 && value !== password1) {
      errorMessage = 'Passwords do not match';
    }
    this.setState({
      error: errorMessage,
      password: value,
    });
  };

  onPassword1Change = (e) => {
    const { password } = this.state;
    const value = e.target.value.trim();
    let errorMessage = '';
    if (value.length < 1) {
      errorMessage = 'Please fill confirm password';
    } else if (value !== password) {
      errorMessage = 'Passwords do not match';
    }
    this.setState({
      error: errorMessage,
      password1: value,
    });
  };

  onSavePassword = (e) => {
    e.preventDefault();
    const { itemId, signature, timestamp } = this.props;
    const { password } = this.state;

    if (this.validate()) {
      this.setState({ sending: true });
      this.props.dispatch(savePassword(itemId, signature, timestamp, password))
        .then(this.afterSave);
    }
  }

  afterSave = (response) => {
    this.setState({ sending: false });
    if (response.status === 1) {
      this.props.router.push('/login');
    }
  }

  render() {
    const { password, password1, error, sending } = this.state;
    const { username } = this.props;
    const hasError = error.length > 0 || error.length > 0;

    return (
      <Col md={4} mdOffset={4}>
        <Panel header="Reset Password" className="login-panel">
          <Form>
            <fieldset>
              <FormGroup>
                <InputGroup>
                  <InputGroup.Addon><i className="fa fa-user fa-fw" /></InputGroup.Addon>
                  <Field value={username} disabled={true} />
                </InputGroup>
              </FormGroup>
              <FormGroup validationState={hasError ? 'error' : null} >
                <InputGroup>
                  <InputGroup.Addon><i className="fa fa-key fa-fw" /></InputGroup.Addon>
                  <FormControl
                    autoFocus
                    type="password"
                    placeholder="New password"
                    value={password}
                    onChange={this.onPasswordChange}
                    disabled={sending}
                    style={{ borderBottom: 0 }}
                  />
                  <FormControl
                    type="password"
                    placeholder="Confirm new password"
                    value={password1}
                    onChange={this.onPassword1Change}
                    disabled={sending}
                  />
                </InputGroup>
                { error.length > 0 && <HelpBlock>{error}</HelpBlock> }
              </FormGroup>
            </fieldset>
            <Button type="submit" bsStyle="primary" bsSize="lg" block onClick={this.onSavePassword} disabled={sending}>
              { sending && (<span><i className="fa fa-spinner fa-pulse" /> &nbsp;</span>) }
              Save
            </Button>
          </Form>
        </Panel>
      </Col>
    );
  }

}

const mapStateToProps = (state, props) => ({
  itemId: idSelector(state, props),
  signature: sigSelector(state, props),
  timestamp: timestampSelector(state, props),
  username: usernameSelector(state, props),
  auth: state.user.get('auth'),
});

export default withRouter(connect(mapStateToProps)(ChangePassword));
