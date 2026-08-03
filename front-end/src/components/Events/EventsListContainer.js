import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import moment from 'moment';
import { noCase, sentenceCase } from 'change-case';
import EntityList from '../EntityList';
import { StateIcon } from '@/components/Elements';
import BalanceEvent from './Elements/BalanceEvent';
import FraudEvent from './Elements/FraudEvent';
import BalancePrepaidEvent from './Elements/BalancePrepaidEvent';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import { showSuccess } from '@/actions/alertsActions';
import {
  prepareEventForEdit,
  saveEvent,
  deleteEvent,
  setEventActive,
  validateEvent,
} from '@/actions/eventActions';
import { eventTresholdFieldsSelector } from '@/selectors/eventSelectors';
import { getConfig } from '@/common/Util';


const Components = {
  balance: BalanceEvent,
  balancePrepaid: BalancePrepaidEvent,
  fraud: FraudEvent,
};

const itemsTypeByEvent = {
  balance: 'balance_events',
  balancePrepaid: 'balance_prepaid_events',
  fraud: 'fraud_events',
};

const defaultNewEvent = {
  balance: Immutable.Map({
    active: false,
  }),
  fraud: Immutable.Map({
    active: false,
    date_range: Immutable.Map({ type: 'hourly' }),
    recurrence: Immutable.Map({ type: 'hourly' }),
    lines_overlap: true,
    notify_by_email: Immutable.Map({ notify: false }),
  }),
  balancePrepaid: Immutable.Map({
    active: false,
    prepaid: true,
    conditions: Immutable.List([Immutable.Map({
      type: '',
      value: '',
      unit: '',
      usaget: '',
      paths: Immutable.List([Immutable.Map({ path: '' })]),
    }), Immutable.Map({
      type: 'is',
      value: '',
      paths: Immutable.List([Immutable.Map({ path: 'pp_includes_external_id' })]),
    }), Immutable.Map({
      type: 'is',
      value: 'prepaid',
      paths: Immutable.List([Immutable.Map({ path: 'connection_type' })]),
    })]),
  }),
};

const getBaseFilter = (eventType) => {
  switch (eventType) {
    case 'fraud':
      return { type: 'fraud' };
    case 'balancePrepaid':
      return { type: 'balance', prepaid: true };
    case 'balance':
    default:
      return { type: 'balance', prepaid: { $ne: true } };
  }
};


const EventsListContainer = ({ eventType, thresholdFields, dispatch }) => {
  const [refreshString, setRefreshString] = useState('');
  const refresh = () => setRefreshString(moment().format());

  const parserStatus = item => (<StateIcon status={item.get('active', true) ? 'active' : 'expired'} />);

  const parserThreshold = item => item
    .getIn(['threshold_conditions', 0], Immutable.List())
    .map(threshold => threshold.get('field', ''))
    .map(name => thresholdFields
      .find(op => op.get('id', '') === name, null, Immutable.Map())
      .get('title', sentenceCase(name)),
    )
    .join(', ');

  const getTableFields = () => {
    const fields = [
      { id: 'active', title: 'Status', parser: parserStatus, cssClass: 'state' },
      { id: 'key', title: 'Key', sort: true },
      { id: 'event_code', title: 'Event Code', sort: true },
      { id: 'event_description', title: 'Description' },
    ];
    if (eventType === 'fraud') {
      fields.push({ id: 'threshold', title: 'Threshold', parser: parserThreshold });
    }
    return fields;
  };

  const getFilterFields = () => ([
    { id: 'key', placeholder: 'Filter by key', type: 'string' },
    { id: 'event_code', placeholder: 'Filter by event code', type: 'string' },
  ]);

  const openForm = (item, mode, title) => {
    const onOk = (editedItem) => {
      if (!dispatch(validateEvent(editedItem, eventType))) {
        return false;
      }
      return dispatch(saveEvent(eventType, editedItem))
        .then(success => (success.status ? true : Promise.reject()))
        .then(() => dispatch(showSuccess(`Event ${editedItem.get('key', '')} saved successfully`)))
        .then(() => refresh())
        .catch(() => Promise.reject());
    };
    dispatch(showFormModal(item, Components[eventType], { title, onOk, mode }));
  };

  const onNew = () => {
    const title = `Create new ${noCase(getConfig(['events', 'entities', eventType, 'title'], eventType))} event`;
    openForm(defaultNewEvent[eventType], 'create', title);
  };

  const onEdit = (item) => {
    const uiItem = dispatch(prepareEventForEdit(eventType, item));
    openForm(uiItem, 'edit', `Edit "${item.get('key')}" event`);
  };

  const onClone = (item) => {
    const uiItem = dispatch(prepareEventForEdit(eventType, item)).withMutations((clone) => {
      clone.deleteIn(['ui_flags', 'id']);
      clone.delete('_id');
      clone.set('active', false);
    });
    openForm(uiItem, 'clone', `Clone "${item.get('key')}" event`);
  };

  const onRemove = (item) => {
    const onOk = () => dispatch(deleteEvent(item))
      .then(success => (success && success.status ? dispatch(showSuccess(`Event ${item.get('key', '')} deleted successfully`)) : null))
      .then(() => refresh());
    dispatch(showConfirmModal({
      message: `Are you sure you want to delete "${item.get('key')}" event?`,
      onOk,
      labelOk: 'Delete',
      type: 'delete',
    }));
  };

  const onEnable = (item) => {
    const onOk = () => dispatch(setEventActive(item, true))
      .then(success => (success && success.status ? dispatch(showSuccess(`Event ${item.get('key', '')} enabled successfully`)) : null))
      .then(() => refresh());
    dispatch(showConfirmModal({
      message: `Are you sure you want to enable "${item.get('key')}" event?`,
      onOk,
      type: 'confirm',
      labelOk: 'Enable',
    }));
  };

  const onDisable = (item) => {
    const onOk = () => dispatch(setEventActive(item, false))
      .then(success => (success && success.status ? dispatch(showSuccess(`Event ${item.get('key', '')} disabled successfully`)) : null))
      .then(() => refresh());
    dispatch(showConfirmModal({
      message: `Are you sure you want to disable "${item.get('key')}" event?`,
      onOk,
      type: 'delete',
      labelOk: 'Disable',
    }));
  };

  const actions = [
    { type: 'enable', showIcon: true, helpText: 'Enable', onClick: onEnable, show: item => !item.get('active', true) },
    { type: 'disable', showIcon: true, helpText: 'Disable', onClick: onDisable, show: item => item.get('active', true) },
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: onEdit },
    { type: 'clone', showIcon: true, helpText: 'Clone', onClick: onClone },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: onRemove },
  ];

  const listActions = [
    { type: 'add', onClick: onNew },
    { type: 'refresh' },
  ];

  return (
    <EntityList
      collection="eventsettings"
      api="get"
      itemType="eventsettings"
      itemsType={itemsTypeByEvent[eventType]}
      baseFilter={getBaseFilter(eventType)}
      tableFields={getTableFields()}
      filterFields={getFilterFields()}
      actions={actions}
      listActions={listActions}
      refreshString={refreshString}
    />
  );
};

EventsListContainer.propTypes = {
  eventType: PropTypes.string.isRequired,
  thresholdFields: PropTypes.instanceOf(Immutable.List),
  dispatch: PropTypes.func.isRequired,
};

EventsListContainer.defaultProps = {
  thresholdFields: Immutable.List(),
};

const mapStateToProps = () => ({
  thresholdFields: eventTresholdFieldsSelector(null, { eventType: 'fraud' }),
});

export default connect(mapStateToProps)(EventsListContainer);
