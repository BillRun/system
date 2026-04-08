import React from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col, Row } from 'react-bootstrap';
import { Panel } from '@/common/BootstrapCompat';
import Help from '@/components/Help';
import List from '@/components/List';
import { ServiceDescription } from '@/language/FieldDescriptions';
import { Actions } from '@/components/Elements';
import { showFormModal, setFormModalError, showConfirmModal } from '@/actions/guiStateActions/pageActions';
import ServiceCountersForm from './ServiceCountersForm';
import {
  getFieldName,
} from '@/common/Util';

const ServiceCountersList = ({ groups, allowCreate, onCreate, onEdit, onDelete }) => {

  const parserName = (item) => {
    return item.get('group_key', '');
  }
  const parserShared = (item) => {
    return item.get('account_shared', false) ? 'Yes' : 'No';
  }
  // const parserPooled = (item) => {
  //   return item.get('account_pool', false) ? 'Yes' : 'No';
  // }
  // const parserQuantitative = (item) => {
  //   return item.get('quantity_affected', false) ? 'Yes' : 'No';
  // }
  const parserProducts = (item) => {
    const rates = item.get('rates', '');
    if (rates === 'ALL_RATES') {
      return 'All Products';
    }
    if (Immutable.List.isList(rates) || Array.isArray(rates)) {
      return rates.join(', ');
    }
    return `Regex: ${rates} of product key`;
  }

  const rowActions = [
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: onEdit, show: true},
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: onDelete },
  ];

  const listActions = [{
      type: 'add',
      label: getFieldName('counter_group_add', "service"),
      actionStyle: 'primary',
      showIcon: true,
      onClick: onCreate,
      actionSize: 'xsmall',
      show: allowCreate,
  }];

  const tableFields = [
    { id: "name", title: getFieldName('counter_group_name', "service"), parser: parserName, },
    { id: "products", title: getFieldName('counter_group_products', "service"), parser: parserProducts, },
    { id: "shared", title: getFieldName('counter_group_shared', "service"), parser: parserShared, },
    // { id: "pooled", title: getFieldName('counter_group_pooled', "service"), parser: parserPooled, },
    // { id: "quantitative", title: getFieldName('counter_group_quantitative', "service"), parser: parserQuantitative, },
  ];
  
  const header = (
    <div>
      Groups <Help contents={ServiceDescription.include_counters_groups}/>
      <div className="pull-right">
        <Actions actions={listActions} />
      </div>
    </div>
  );

  return (
    <Row className='service-counters-list'>
      <Col lg={12}>
        <Panel header={header}>
            <List
              items={groups}
              fields={tableFields}
              actions={rowActions}
            />
        </Panel>
      </Col>
    </Row>
  );
}

ServiceCountersList.propTypes = {
  mode: PropTypes.string,
  includeGroups: PropTypes.instanceOf(Immutable.Map),
};

const mapStateToProps = (state, props) => ({
  groups: props.includeGroups.reduce((acc, group, key) => acc.push(group.set('group_key', key)), Immutable.List()),
  allowCreate: props.mode !== 'view',
});

const mapDispatchToProps = (dispatch, props) => ({

  onCreate: () => {
    const onOk = (newItem) => {
      const group_key = newItem.get('group_key', '');
      if (group_key === '') {
        dispatch(setFormModalError('group_key', 'Group name is required'));
        return false;
      }
      const rates = newItem.get('rates', '');
      if ((typeof rates === 'string' || rates instanceof String) && rates.length == 0) {
        dispatch(setFormModalError('rates_regex', 'Regex of product key is required'));
        return false;
      } else if (Immutable.List.isList(rates) && rates.size == 0) {
        dispatch(setFormModalError('rates_select', 'At least one product is required'));
        return false;
      } else if (Array.isArray(rates) && rates.length == 0) {
        dispatch(setFormModalError('rates_select', 'At least one product is required'));
        return false;
      }
      props.onGroupAdd(newItem.get('group_key', ''), newItem.delete('group_key'));
    };
    const config = {
      title: getFieldName('counter_group_add', "service"),
      onOk,
      labelOk: 'OK',
      mode: 'create',
      existingGroupsNames: props.existingGroupsNames,
    };
    const newItem = Immutable.Map({
      'group_key': '',
      'counter_only': true,
      'cost': 0,
      'account_shared': false,
      'account_pool': false,
      'quantity_affected': false,
      'rates': 'ALL_RATES',
    });
    return dispatch(showFormModal(newItem, ServiceCountersForm, config));
  },

  onEdit: (item) => {
    const group_key = item.get('group_key', '');
    const onOk = (updatedItem) => {
      const rates = updatedItem.get('rates', '');
      if ((typeof rates === 'string' || rates instanceof String) && rates.length == 0) {
        dispatch(setFormModalError('rates_regex', 'Regex of product key is required'));
        return false;
      } else if (Immutable.List.isList(rates) && rates.size == 0) {
        dispatch(setFormModalError('rates_select', 'At least one product is required'));
        return false;
      } else if (Array.isArray(rates) && rates.length == 0) {
        dispatch(setFormModalError('rates_select', 'At least one product is required'));
        return false;
      }
      props.onGroupUpdate(['include', 'groups', group_key], updatedItem.delete('group_key'));
    };
    const config = {
      title: getFieldName('counter_group_edit', "service") + ' ' + group_key,
      onOk,
      labelOk: 'OK',
      mode: 'edit',
      existingGroupsNames: props.existingGroupsNames,
    };
    return dispatch(showFormModal(item, ServiceCountersForm, config));
  },

  onDelete: (item) => {
    const group_key = item.get('group_key', '');
    const onOk = () => {
      props.onGroupRemove(group_key);
    };
    const confirm = {
      message: `Are you sure you want to delete group "${group_key}"?`,
      onOk,
      type: 'delete',
      labelOk: 'Remove',

    };
    return dispatch(showConfirmModal(confirm));
  },

});

export default connect(mapStateToProps, mapDispatchToProps)(ServiceCountersList);

