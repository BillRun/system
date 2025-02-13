import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import { Col, Panel } from 'react-bootstrap';
import Immutable from 'immutable';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import User from './User';
import { getUser, saveUser, clearUser, updateUserField, deleteUserField } from '@/actions/userActions';
import { clearItems } from '@/actions/entityListActions';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';


class UserSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    user: Immutable.Map(),
  };

  state = {
    username: '',
  };

  componentDidMount() {
    const { itemId, mode } = this.props;
    if (itemId) {
      this.props.dispatch(getUser(itemId));
    }
    if (mode === 'create') {
      this.props.dispatch(setPageTitle('Create New User'));
    } else {
      this.props.dispatch(setPageTitle('Edit user'));
    }
  }

  componentWillReceiveProps(nextProps) {
    const { username } = this.state;
    const { item, mode } = nextProps;
    if (username === '') {
      this.setState({ username: item.get('username', '') });
    }
    if (mode === 'update' && username !== '') {
      this.props.dispatch(setPageTitle(`Edit user - ${username}`));
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearUser());
  }

  onBack = () => {
    this.props.router.push('/users');
  }

  onSave = () => {
    const { item, mode } = this.props;
    if (this.validate()) {
      this.props.dispatch(saveUser(item, mode)).then(this.afterSave);
    }
  }

  afterSave = (response) => {
    const { mode } = this.props;
    if (response.status) {
      this.props.dispatch(clearItems('users')); // refetch items list because item was (changed in / added to) list
      const action = (mode === 'create') ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`User was ${action}`));
      this.onBack();
    }
  }

  onUpdateValue = (path, value = '') => {
    this.props.dispatch(updateUserField(path, value));
  }

  onDeleteValue = (path) => {
    this.props.dispatch(deleteUserField(path));
  }

  validate = () => {
    const { item, mode } = this.props;
    if (mode === 'create') {
      if (item.get('username', '').length === 0) {
        this.props.dispatch(showDanger('User name field is required'));
        return false;
      }
      if (item.get('password', '').length === 0) {
        this.props.dispatch(showDanger('Password field is required'));
        return false;
      }

      if (item.get('roles').length === 0) {
        this.props.dispatch(showDanger('Roles field is required'));
        return false;
      }
      return true;
    }

    if (item.has('username') && item.get('username', '').length === 0) {
      this.props.dispatch(showDanger('User name field is required'));
      return false;
    }
    if (item.has('password') && item.get('password', '').length === 0) {
      this.props.dispatch(showDanger('Password field is required'));
      return false;
    }

    if (item.has('roles') && item.get('roles').length === 0) {
      this.props.dispatch(showDanger('Roles field is required'));
      return false;
    }
    return true;
  }

  render() {
    const { item, mode } = this.props;

    // in update mode wait for item before render edit screen
    if (mode === 'update' && typeof item.getIn(['_id', '$id']) === 'undefined') {
      return (<LoadingItemPlaceholder onClick={this.onBack} />);
    }

    return (
      <Col lg={12}>
        <Panel>
          <User
            action={mode}
            user={item}
            onUpdateValue={this.onUpdateValue}
            onDeleteValue={this.onDeleteValue}
          />
        </Panel>

        <ActionButtons onClickSave={this.onSave} onClickCancel={this.onBack} />

      </Col>
    );
  }

}


const mapStateToProps = (state, props) => {
  const { params: { itemId }, location: { query: { action } } } = props;
  const mode = action || ((itemId) ? 'update' : 'create');
  const item = state.entity.get('users', Immutable.Map());
  return ({ mode, item, itemId });
};

export default withRouter(connect(mapStateToProps)(UserSetup));
