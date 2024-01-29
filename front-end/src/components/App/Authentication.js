import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { LoginForm } from '../UserForms';
import { Forbidden403 } from '../StaticPages';
import { permissionsSelector } from '@/selectors/guiSelectors';
import { actionSelector } from '@/selectors/entitySelector';


export default function (ComposedComponent) {
  class Authenticate extends Component {

    static defaultProps = {
      auth: null,
      roles: null,
      permissions: Immutable.Map(),
      action: 'view',
    };

    static propTypes = {
      auth: PropTypes.bool,
      roles: PropTypes.array,
      permissions: PropTypes.instanceOf(Immutable.Map),
      action: PropTypes.string,
      location: PropTypes.shape({
        pathname: PropTypes.string,
        query: PropTypes.object,
      }).isRequired,
    };

    render() {
      const { auth, roles, permissions, action, location, ...composedComponentProps } = this.props;
      // If user is not authorized -> return login
      if (!roles || !auth) {
        return (<LoginForm />);
      }
      // Waiting for permission load
      if (permissions.size === 0) {
        return null;
      }
      // If user admin -> return true
      if (roles.includes('admin')) {
        return (<ComposedComponent {...composedComponentProps} location={location} />);
      }
      const pageRoute = location.pathname.substr(1);
      const perms = permissions.getIn([pageRoute, action], Immutable.List());
      // If no permissions required -> return true
      if (perms.size === 0) {
        return (<ComposedComponent {...composedComponentProps} location={location} />);
      }
      // Check if user has permissions
      const permissionDenied = perms.toSet().intersect(roles).size === 0;
      if (permissionDenied) {
        return (<Forbidden403 location={location} />);
      }
      return (<ComposedComponent {...composedComponentProps} location={location} />);
    }
  }

  const mapStateToProps = (state, props) => ({
    auth: state.user.get('auth'),
    roles: state.user.get('roles'),
    permissions: permissionsSelector(state),
    action: actionSelector(state, props),
  });

  return connect(mapStateToProps)(Authenticate);
}
