import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import { LoginForm } from '../UserForms';


class LoginPage extends Component {

  static defaultProps = {
    auth: false,
  }

  static propTypes = {
    auth: PropTypes.bool,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
  }

  componentWillMount() {
    if (this.props.auth === true) {
      this.props.router.push('/');
    }
  }

  render() {
    return (
      <LoginForm />
    );
  }

}


const mapStateToProps = state => ({
  auth: state.user.get('auth'),
});

export default withRouter(connect(mapStateToProps)(LoginPage));
