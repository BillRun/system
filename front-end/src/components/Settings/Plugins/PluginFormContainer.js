import { connect } from 'react-redux';
import { List, Map } from 'immutable';
import PluginForm from './PluginForm';
import { validateFieldByType } from '@/actions/entityActions';

const mapStateToProps = null;

const mapDispatchToProps = (dispatch, {
  item, updateField, removeField, setError, ...otherProps
}) => ({

  onChangeEnabled: (e) => {
    const { value } = e.target;
    const newState = value === 'yes';
    updateField('enabled', newState);
  },

  onChange: (key, value) => {
    const path = Array.isArray(key) ? key : [key];
    const pathString = path.join('.');
    const field_config = item
      .getIn(['configuration', 'fields'], List())
      .find(field => field.get('field_name', '') === pathString, null, Map());
    const hasError = validateFieldByType(value, field_config);
    if (hasError !== false) {
      setError(pathString, hasError);
    } else {
      setError(pathString);
    }
    updateField(['configuration', 'values', ...path], value)
  },

  onRemove: (key) => {
    const path = Array.isArray(key) ? key : [key];
    // remove error from the current path
    setError(path.join('.'));
    // remove all the tree if to prevent saving empty tree parts
    const minPathLevelToRemove = path.reduce((acc) => {
      const prevLevel = [...acc];
      prevLevel.pop();
      const levelValue = item.getIn(['configuration', 'values', ...prevLevel], Map());
      // check if prev tree level has only the removed value
      if (Map.isMap(levelValue) && levelValue.size > 1) {
        return acc;
      }
      // if no edition values exists, all levels above can be removed
      // remove mor error for this level if exists
      setError(prevLevel.join('.'));
      return prevLevel;
    }, path);
    removeField(['configuration', 'values', ...minPathLevelToRemove]);
  },

});

export default connect(mapStateToProps, mapDispatchToProps)(PluginForm);
