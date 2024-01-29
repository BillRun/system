import { connect } from 'react-redux';
import Play from './Play';
import PlayForm from './PlayFormContainer';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import { saveSettings, getSettings } from '@/actions/settingsActions';
import { showWarning } from '@/actions/alertsActions';


const mapStateToProps = (state, props) => ({
  isDefaultDisabled: !props.play.get('enabled', true),
  isDisableAllowd: !props.play.get('default', false),
  showEnableAction: !props.play.get('enabled', true),
});

const mapDispatchToProps = (dispatch, { index, play, plays, onChange, onRemove, ...otherProps }) => ({ // eslint-disable-line no-unused-vars

  onChangeDefault: () => {
    if (play.get('enabled', true)) {
      plays.forEach((p, i) => {
        onChange([i, 'default'], false);
      });
      onChange([index, 'default'], true);
      return dispatch(saveSettings(['plays']))
        .then(success => (success.status ? true : Promise.reject()))
        .then(() => dispatch(getSettings('plays')))
        .catch(() => {
          dispatch(getSettings('plays'));
          return Promise.reject();
        });
    }
    return dispatch(showWarning('Disabled play can not be default'));
  },

  onEdit: (item) => {
    const onOk = (editedItem) => {
      onChange([index], editedItem);
      return dispatch(saveSettings(['plays']))
        .then(success => (success.status ? true : Promise.reject()))
        .then(() => dispatch(getSettings('plays')))
        .catch(() => {
          dispatch(getSettings('plays'));
          return Promise.reject();
        });
    };
    const config = {
      title: `Edit Play ${item.get('name', '')}`,
      onOk,
      mode: 'edit',
    };
    return dispatch(showFormModal(item, PlayForm, config));
  },

  onRemove: (item) => {
    if (item.get('default', false)) {
      return dispatch(showWarning('Default play can not be removed'));
    }
    const onOk = () => {
      onRemove(index);
      return dispatch(saveSettings(['plays']))
        .then(() => dispatch(getSettings('plays')));
    };
    const confirm = {
      message: `Are you sure you want to remove Play "${item.get('name', '')}"?`,
      onOk,
      labelOk: 'Delete',
      type: 'delete',
    };
    return dispatch(showConfirmModal(confirm));
  },

  onEnable: () => {
    onChange([index, 'enabled'], true);
    return dispatch(saveSettings('plays'))
      .then(() => dispatch(getSettings('plays')));
  },

  onDisable: (item) => {
    if (item.get('default', false)) {
      return dispatch(showWarning('Default play can not be disabled'));
    }
    const onOk = () => {
      onChange([index, 'enabled'], false);
      return dispatch(saveSettings(['plays']))
        .then(() => dispatch(getSettings('plays')));
    };
    const confirm = {
      message: `Are you sure you want to disable Play "${item.get('name', '')}"?`,
      onOk,
      type: 'delete',
      labelOk: 'Disable',
    };
    return dispatch(showConfirmModal(confirm));
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(Play);
