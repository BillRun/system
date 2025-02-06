import { connect } from 'react-redux';
import {
  getEventSettings,
  saveEventSettings,
  updateEventSettings,
} from '@/actions/eventActions';
import {
  showConfirmModal,
} from '@/actions/guiStateActions/pageActions';
import { eventsSettingsSelector } from '@/selectors/settingsSelector';
import EventSettings from './EventSettings';

const mapStateToProps = (state, props) => ({
  eventsSettings: eventsSettingsSelector(state, props),
});

const mapDispatchToProps = dispatch => ({
  onCancel: () => {
    const onOk = () => {
      dispatch(getEventSettings());
    };
    const confirm = {
      message: 'Are you sure you want to cancel event settings changes?',
      onOk,
      labelOk: 'Yes',
      labelCancel: 'No',
      type: 'delete',
    };
    return dispatch(showConfirmModal(confirm));
  },
  onEdit: (eventNotifier, field, value) => {
    dispatch(updateEventSettings([eventNotifier, field], value));
  },
  onSave: () => {
    dispatch(saveEventSettings());
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(EventSettings);
