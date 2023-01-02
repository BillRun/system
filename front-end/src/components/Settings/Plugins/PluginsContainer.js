import { connect } from 'react-redux';
import Plugins from './Plugins';
import { updateSetting } from '@/actions/settingsActions';


const mapStateToProps = null; // eslint-disable-line no-unused-vars

const mapDispatchToProps = (dispatch, props) => ({

  onChange: (path, value) => {
    dispatch(updateSetting('plugins', path, value));
  },

});

export default connect(mapStateToProps, mapDispatchToProps)(Plugins);
