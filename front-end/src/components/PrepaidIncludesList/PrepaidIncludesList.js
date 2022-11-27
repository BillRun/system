import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import moment from 'moment';
import EntityList from '../EntityList';
import { ConfirmModal } from '@/components/Elements';
import {
  deletePrepaidInclude,
} from '@/actions/prepaidIncludeActions';

class PrepaidIncludesList extends Component {
  static propTypes = {
    dispatch: PropTypes.func.isRequired,
  };

  state = {
    showConfirmRemove: false,
    prepaidIncludes: null,
    refreshString: '',
  }

  fields = [
    { id: 'name', sort: true },
    { id: 'charging_by', showFilter: false },
    { id: 'charging_by_usaget', showFilter: false },
    { id: 'priority', showFilter: false, sort: true },
  ];

  projectFields = {
    charging_by_usaget: 1,
    charging_by: 1,
    priority: 1,
    name: 1,
  };

  onClickRemoveItem = (prepaidIncludes) => {
    this.setState({
      showConfirmRemove: true,
      prepaidIncludes,
    });
  }

  onClickRemoveCancel = () => {
    this.setState({
      showConfirmRemove: false,
      prepaidIncludes: null,
    });
  }

  onClickRemoveOk = () => {
    const { prepaidIncludes } = this.state;
    this.setState({
      showConfirmRemove: false,
      prepaidIncludes: null,
    });

    this.props.dispatch(deletePrepaidInclude(prepaidIncludes))
    .then(
      (response) => {
        if (response.status) {
          this.setState({ refreshString: moment().format() });
        }
      }
    );
  }

  actions = [
    { type: 'edit' },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: this.onClickRemoveItem, show: true },
  ];

  render() {
    const { showConfirmRemove, prepaidIncludes, refreshString } = this.state;
    const removeConfirmMessage = prepaidIncludes ? `Are you sure you want to remove ${prepaidIncludes.get('name', '')}?` : '';
    return (
      <div className="prepaid-includes-list">
        <EntityList
          collection="prepaidincludes"
          itemType="prepaid_include"
          itemsType="prepaid_includes"
          filterFields={this.fields}
          tableFields={this.fields}
          projectFields={this.projectFields}
          showRevisionBy="name"
          actions={this.actions}
          refreshString={refreshString}
        />
        <ConfirmModal onOk={this.onClickRemoveOk} onCancel={this.onClickRemoveCancel} show={showConfirmRemove} message={removeConfirmMessage} labelOk="Yes" />
      </div>
    );
  }
}


export default connect()(PrepaidIncludesList);
