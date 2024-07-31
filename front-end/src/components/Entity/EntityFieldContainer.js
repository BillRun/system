import { connect } from 'react-redux';
import EntityField from './EntityField';
import {
  isEditableFiledProperty,
} from '@/common/Util';

const monMultipleTypes = ['password'];

const mapStateToProps = (state, props) => ({
  isFieldTags: props.field && props.field.get('multiple', false) && !props.field.get('select_list', false) && !monMultipleTypes.includes(props.field.get('type', '')),
  isFieldSelect: props.field && props.field.get('select_list', false),
  isFieldBoolean: props.field && props.field.get('type', '') === 'boolean',
  isFieldRanges: props.field && props.field.get('type', '') === 'ranges',
  isFieldDate: props.field && props.field.get('type', '') === 'date',
  isFieldDateTime: props.field && props.field.get('type', '') === 'datetime',
  isFieldDateRange: props.field && props.field.get('type', '') === 'daterange',
  isFieldJson: props.field && props.field.get('type', '') === 'json',
  isRemoveField: props.field && (['params'].includes(props.field.get('field_name', '').split('.')[0]) || props.field.get('nullable', false)) && isEditableFiledProperty(props.field, true, 'delete'),
  fieldPath: props.field ? props.field.get('field_name', '').split('.') : [],
});


const mapDispatchToProps = (dispatch, props) => ({

});


export default connect(mapStateToProps, mapDispatchToProps)(EntityField);
