import { connect } from 'react-redux';
import Plugin from './Plugin';
import PluginForm from './PluginFormContainer';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import { savePlugin, savePluginByName, validatePlugin } from '@/actions/pluginActions';

const mapStateToProps = (state, props) => ({
  showEnableAction: !props.plugin.get('enabled', true),
});

const mapDispatchToProps = (dispatch, { index, plugin, plugins, onChange, onRemove, ...otherProps }) => ({ // eslint-disable-line no-unused-vars

  onEdit: (item) => {
    const onOk = (editedItem) => {
      if (!dispatch(validatePlugin(editedItem))) {
        return false;
      }
      return dispatch(savePlugin(editedItem));
    };
    const config = {
      title: `Edit Plugin ${item.get('label', '')}`,
      onOk,
      mode: 'edit',
    };
    return dispatch(showFormModal(item, PluginForm, config));
  },


  onEnable: (item) => {
    onChange([index, 'enabled'], true);
    const success = `Plugin ${item.get('label','')} was successfuly enabled`
    const error = `Plugin ${item.get('label','')} can not be enabled`
    return dispatch(savePluginByName(item.get('name', ''), {success, error}));
  },

  onDisable: (item) => {
    const onOk = () => {
      onChange([index, 'enabled'], false);
      const success = `Plugin ${item.get('label','')} was successfuly disabled`
      const error = `Plugin ${item.get('label','')} can not be disabled`
      return dispatch(savePluginByName(item.get('name', ''), {success, error}));
    };
    const confirm = {
      message: `Are you sure you want to disable plugin "${item.get('label', '')}"?`,
      onOk,
      type: 'delete',
      labelOk: 'Disable',
    };
    return dispatch(showConfirmModal(confirm));
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(Plugin);
