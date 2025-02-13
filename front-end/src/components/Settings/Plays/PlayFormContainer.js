import { connect } from 'react-redux';
import { titleCase } from 'change-case';
import PlayForm from './PlayForm';
import { getConfig } from '@/common/Util';

const mapStateToProps = (state, props) => ({
  isAllowedDisableAction: !props.item.get('default', false),
  isAllowedEditName: props.mode === 'create',
  isAllowedEditDefault: props.mode === 'create',
  isNameHasError: (props.errors) && props.errors.get('name', false),
});


const mapDispatchToProps = (dispatch, props) => ({

  onChangeName: (e) => {
    const { item, updateField, setError, existingNames, errors } = props;
    const { value } = e.target;
    const cleanValue = value.toUpperCase().replace(getConfig('keyUppercaseCleanRegex', /./), '');
    updateField(['name'], cleanValue);
    if (existingNames.includes(cleanValue)) {
      setError('name', 'Name already exists');
    } else if (errors.get('name', false)) {
      setError('name');
    }
    if (item.get('label', '') === '' || item.get('label', '').toUpperCase() === item.get('name', '').toUpperCase()) {
      updateField('label', titleCase(cleanValue));
    }
  },

  onChangeLabel: (e) => {
    const { updateField } = props;
    const { value } = e.target;
    updateField('label', value);
  },

  onChangeDefault: (e) => {
    const { updateField } = props;
    const { value } = e.target;
    updateField('default', value);
    if (value) {
      updateField('enabled', true);
    }
  },

  onChangeEnabled: (e) => {
    const { updateField } = props;
    const { value } = e.target;
    updateField('enabled', value);
  },

});

export default connect(mapStateToProps, mapDispatchToProps)(PlayForm);
