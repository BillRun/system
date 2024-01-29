import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import moment from 'moment';
import EntityList from '../EntityList';
import { ConfirmModal } from '@/components/Elements';
import { showSuccess } from '@/actions/alertsActions';
import { deleteUser } from '@/actions/userActions';

class UserList extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
  }

  state = {
    showConfirmDelete: false,
    itemToDelete: null,
    confirmDeleteMessage: '',
    refreshString: '',
  }

  getActions = () => ([
    { type: 'edit' },
    { type: 'remove', showIcon: true, onClick: this.onAskDelete, helpText: 'Remove' },
  ]);

  getFields = () => ([
    { id: 'username', placeholder: 'User Name', sort: true },
    { id: 'roles', placeholder: 'Roles', parser: this.parseRoles },
  ]);

  getProjectFields = () => ({
    username: 1,
    roles: 1,
  });

  onAskDelete = (item) => {
    this.setState({
      showConfirmDelete: true,
      itemToDelete: item,
      confirmDeleteMessage: `Are you sure you want to delete "${item.get('username', '')}" user ?`,
    });
  }

  onDeleteClose = () => {
    this.setState({
      showConfirmDelete: false,
      itemToDelete: null,
      confirmDeleteMessage: '',
    });
  }

  onDeleteOk = () => {
    const { itemToDelete } = this.state;
    this.props.dispatch(deleteUser(itemToDelete)).then(this.afterDelete);
  }

  afterDelete = (response) => {
    this.onDeleteClose();
    if (response.status) {
      this.setState({ refreshString: moment().format() });
      this.props.dispatch(showSuccess('User was deleted'));
    }
  }

  parseRoles = item => item.get('roles').join(', ');


  render() {
    const { showConfirmDelete, confirmDeleteMessage, refreshString } = this.state;
    const fields = this.getFields();
    const actions = this.getActions();
    const projectFields = this.getProjectFields();
    return (
      <div>
        <EntityList
          api="get"
          itemType="user"
          itemsType="users"
          filterFields={fields}
          tableFields={fields}
          projectFields={projectFields}
          actions={actions}
          refreshString={refreshString}
        />
        <ConfirmModal
          onOk={this.onDeleteOk}
          onCancel={this.onDeleteClose}
          show={showConfirmDelete}
          message={confirmDeleteMessage}
          labelOk="Yes"
        />
      </div>
    );
  }
}

export default connect()(UserList);
