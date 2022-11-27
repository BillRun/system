import { connect } from 'react-redux';
import Immutable from 'immutable';
import Plays from './Plays';
import PlayForm from './PlayFormContainer';
import { showFormModal, setFormModalError } from '@/actions/guiStateActions/pageActions';
import { saveSettings, getSettings, updateSetting, removeSettingField } from '@/actions/settingsActions';


const mapStateToProps = null; // eslint-disable-line no-unused-vars

const mapDispatchToProps = (dispatch, props) => ({

  onChange: (path, value) => {
    dispatch(updateSetting('plays', path, value));
  },

  onRemove: (index) => {
    dispatch(removeSettingField('plays', index));
  },

  onAdd: () => {
    const { data } = props;
    const newPlay = Immutable.Map({
      name: '',
      label: '',
      enabled: true,
      default: data.isEmpty(),
    });
    const onOk = (newItem) => {
      if (newItem.get('name', '') === '') {
        dispatch(setFormModalError('name', 'Name is required'));
        return false;
      }
      if (newItem.get('default', false)) {
        data.forEach((p, index) => {
          dispatch(updateSetting('plays', [index, 'default'], false));
        });
      }

      dispatch(updateSetting('plays', data.size, newItem));
      return dispatch(saveSettings(['plays']))
        .then(success => (success.status ? true : Promise.reject()))
        .then(() => dispatch(getSettings('plays')))
        .catch(() => {
          dispatch(getSettings('plays'));
          return Promise.reject();
        });
    };
    const config = {
      title: 'Create New Play',
      onOk,
      mode: 'create',
      existingNames: data.map(play => play.get('name', '')),
    };
    return dispatch(showFormModal(newPlay, PlayForm, config));
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(Plays);
