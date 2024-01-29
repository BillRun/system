import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import moment from 'moment';
import Immutable from 'immutable';
import { titleCase } from 'change-case';
import EntityList from '../EntityList';
import { getConfig, getItemId, getFieldName } from '@/common/Util';
import { showSuccess } from '@/actions/alertsActions';
import { deleteReport } from '@/actions/reportsActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';


class ReportsList extends Component {

  static propTypes = {
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {}

  state = {
    itemToDelete: null,
    refreshString: '',
  }

  parseEntityName = item => titleCase(getConfig(['systemItems', item.get('entity', ''), 'itemName'], 'entity'));

  parseEntityType = item => getFieldName(`report_type_${item.get('type')}`, 'report');

  parseUserName = item => titleCase(item.get('user', '-'));

  getFilterFields = () => ([
    { id: 'key', placeholder: 'Name' },
    { id: 'user', placeholder: 'User' },
  ]);

  getTableFields = () => ([
    { id: 'key', title: 'Name', sort: true },
    { id: 'entity', title: 'Entity', sort: true, parser: this.parseEntityName },
    { id: 'type', title: 'Type', sort: true, parser: this.parseEntityType },
    { id: 'user', title: 'User', sort: true, parser: this.parseUserName },
    { id: 'from', title: 'Modified', type: 'date', cssClass: 'short-date', sort: true },
  ]);

  getProjectFields = () => ({
    key: 1,
    entity: 1,
    user: 1,
    from: 1,
    type: 1,
  });

  onAskDelete = (item) => {
    const confirm = {
      message: `Are you sure you want to delete "${item.get('key', '')}" report ?`,
      onOk: this.onDeleteOk,
      labelOk: 'Delete',
      type: 'delete',
      onCancel: this.onDeleteClose,
    };
    this.props.dispatch(showConfirmModal(confirm));
    this.setState({
      itemToDelete: item,
    });
  }

  onDeleteClose = () => {
    this.setState({ itemToDelete: null });
  }

  onDeleteOk = () => {
    const { itemToDelete } = this.state;
    this.props.dispatch(deleteReport(itemToDelete)).then(this.afterDelete);
  }

  afterDelete = (response) => {
    this.onDeleteClose();
    if (response.status) {
      this.props.dispatch(showSuccess('The report was deleted'));
      this.setState({
        refreshString: moment().format(), //refetch list items after import
      });
    }
  }

  onClone = (item) => {
    const itemId = getItemId(item, '');
    const itemType = getConfig(['systemItems', 'report', 'itemType'], '');
    const itemsType = getConfig(['systemItems', 'report', 'itemsType'], '');
    this.props.router.push({
      pathname: `${itemsType}/${itemType}/${itemId}`,
      query: {
        action: 'clone',
      },
    });
  }

  getActions = () => ([
    { type: 'view', onClickColumn: null },
    { type: 'edit' },
    { type: 'clone', showIcon: true, onClick: this.onClone, helpText: 'Clone' },
    { type: 'remove', showIcon: true, onClick: this.onAskDelete, helpText: 'Remove' },
  ]);

  getDefaultSort = () => Immutable.Map({
    creation_time: -1,
  });

  render() {
    const { refreshString } = this.state;
    const filterFields = this.getFilterFields();
    const tableFields = this.getTableFields();
    const actions = this.getActions();
    const projectFields = this.getProjectFields();
    const defaultSort = this.getDefaultSort();
    return (
      <EntityList
        collection="reports"
        itemType="report"
        itemsType="reports"
        api="get"
        filterFields={filterFields}
        tableFields={tableFields}
        projectFields={projectFields}
        actions={actions}
        refreshString={refreshString}
        defaultSort={defaultSort}
      />
    );
  }

}

export default withRouter(connect()(ReportsList));
