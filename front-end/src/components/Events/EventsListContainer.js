import { connect } from 'react-redux';
import Immutable from 'immutable';
import { noCase } from 'change-case';
import EventsList from './EventsList';
import BalanceEvent from './Elements/BalanceEvent';
import FraudEvent from './Elements/FraudEvent';
import BalancePrepaidEvent from './Elements/BalancePrepaidEvent';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import {
  removeEvent,
  updateEvent,
  saveEvents,
  saveEvent,
  getEvents,
  validateEvent,
} from '@/actions/eventActions';
import {
  showSuccess,
} from '@/actions/alertsActions';
import {
  eventsSelector,
} from '@/selectors/settingsSelector';
import {
  eventTresholdFieldsSelector,
} from '@/selectors/eventSelectors';
import {
  getConfig,
} from '@/common/Util';


const Components = {
  balance: BalanceEvent,
  balancePrepaid: BalancePrepaidEvent,
  fraud: FraudEvent,
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
      paths: Immutable.List([Immutable.Map({"path": ''})]),
    }), Immutable.Map({
      type: 'is',
      value: '',
      paths: Immutable.List([Immutable.Map({path: 'pp_includes_external_id'})]),
    }), Immutable.Map({
      type: 'is',
      value: 'prepaid',
      paths: Immutable.List([Immutable.Map({"path": "connection_type"})])
    })]),
  }),
};


const mapStateToProps = (state, props) => ({
  items: eventsSelector(state, props),
  thresholdFields: eventTresholdFieldsSelector(null, { eventType: 'fraud' }),
});

const mapDispatchToProps = (dispatch, props) => ({

  onRemove: (item) => {
    const onOk = () => {
      dispatch(removeEvent(props.eventType, item));
      return dispatch(saveEvents(props.eventType))
        .then(() => dispatch(getEvents(props.eventType)));
    };
    const confirm = {
      message: `Are you sure you want to delete "${item.get('event_code')}" event?`,
      onOk,
      labelOk: 'Delete',
      type: 'delete',
    };
    return dispatch(showConfirmModal(confirm));
  },

  onEdit: (item) => {
    const onOk = (editedItem) => {
      if (!dispatch(validateEvent(editedItem, props.eventType))) {
        return false;
      }
      dispatch(updateEvent(props.eventType, editedItem));
      return dispatch(saveEvents(props.eventType))
        .then(success => (success.status ? true : Promise.reject()))
        .then(() => dispatch(getEvents(props.eventType)))
        .catch(() => {
          dispatch(getEvents(props.eventType));
          return Promise.reject();
        });
    };
    const config = {
      title: `Edit "${item.get('event_code')}" event`,
      onOk,
      mode: 'edit',
    };
    return dispatch(showFormModal(item, Components[props.eventType], config));
  },

  onClone: (item) => {
    const clone = item.withMutations((itemWithMutations) => {
      itemWithMutations.deleteIn(['ui_flags', 'id']);
      itemWithMutations.set('active', false);
    });
    const onOk = (editedItem) => {
      if (!dispatch(validateEvent(editedItem, props.eventType))) {
        return false;
      }
      return dispatch(saveEvent(props.eventType, editedItem))
      .then(success => (success.status ? true : Promise.reject()))
      .then(() => dispatch(showSuccess(`New event ${editedItem.get('event_code', '')} saved successfully`)))
      .then(() => dispatch(getEvents(props.eventType)))
      .catch(() => Promise.reject());
    };
    const config = {
      title: `Clone "${item.get('event_code')}" event`,
      onOk,
      mode: 'clone',
    };
    return dispatch(showFormModal(clone, Components[props.eventType], config));
  },

  onNew: () => {
    const eventType = getConfig(['events', 'entities', props.eventType, 'title'], props.eventType);
    const onOk = (editedItem) => {
      if (!dispatch(validateEvent(editedItem, props.eventType))) {
        return false;
      }
      return dispatch(saveEvent(props.eventType, editedItem))
      .then(success => (success.status ? true : Promise.reject()))
      .then(() => dispatch(showSuccess(`New event ${editedItem.get('event_code', '')} saved successfully`)))
      .then(() => dispatch(getEvents(props.eventType)))
      .catch(() => Promise.reject());
    };
    const config = {
      title: `Create new ${noCase(eventType)} event`,
      onOk,
      mode: 'create',
    };
    const item = defaultNewEvent[props.eventType];
    return dispatch(showFormModal(item, Components[props.eventType], config));
  },

  onEnable: (item) => {
    const onOk = () => {
      const editedItem = item.set('active', true);
      dispatch(updateEvent(props.eventType, editedItem));
      return dispatch(saveEvents(props.eventType))
        .then(() => dispatch(getEvents(props.eventType)));
    };
    const confirm = {
      message: `Are you sure you want to enable "${item.get('event_code')}" event?`,
      onOk,
      type: 'confirm',
      labelOk: 'Enable',
    };
    dispatch(showConfirmModal(confirm));
  },

  onDisable: (item) => {
    const onOk = () => {
      const editedItem = item.set('active', false);
      dispatch(updateEvent(props.eventType, editedItem));
      return dispatch(saveEvents(props.eventType))
        .then(() => dispatch(getEvents(props.eventType)));
    };
    const confirm = {
      message: `Are you sure you want to disable "${item.get('event_code')}" event?`,
      onOk,
      type: 'delete',
      labelOk: 'Disable',
    };
    dispatch(showConfirmModal(confirm));
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(EventsList);
