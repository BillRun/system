import { connect } from 'react-redux';
import { List, Map } from 'immutable';
import { SortableElement } from 'react-sortable-hoc';
import CustomFieldsListRow from './CustomFieldsListRow';
import {
  isFieldSortable,
  isFieldEditable,
} from '../../selectors/customFieldsSelectors';
import {
  isEditableFiledProperty,
  getConfig,
} from '../../common/Util';


const mapStateToProps = (state, { field, fieldsConfig }) => {
  const editable = isFieldEditable(field, fieldsConfig);
  const fieldType = field.get('type', 'text');
  const fieldTypeLabel = getConfig(['customFields', 'fields'], List())
    .find(config => config.get('id', '') === fieldType, null, Map())
    .get('title', fieldType);

  return ({
    fieldTypeLabel,
    isSortable: isFieldSortable(field, fieldsConfig),
    isEditable: isEditableFiledProperty(field, editable),
    isRemoveable: isEditableFiledProperty(field, editable, 'delete'),
  });
};


const mapDispatchToProps = (dispatch, props) => ({}); // eslint-disable-line no-unused-vars


const mergeProps = (stateProps, dispatchProps, ownProps) => {
  const { isEditable, isRemoveable, ...otherStateProps } = stateProps;
  const {
    entity, onEdit: propsOnEdit, onRemove: propsOnRemove, fieldsConfig, ...otherOwnProps
  } = ownProps;
  const onRemove = field => propsOnRemove(entity, field);
  const onEdit = field => propsOnEdit(entity, field);
  const actions = [
    { type: 'edit', onClick: onEdit, enable: isEditable },
    { type: 'remove', onClick: onRemove, enable: isRemoveable },
  ];
  return ({
    ...otherStateProps,
    ...dispatchProps,
    ...otherOwnProps,
    entity,
    actions,
    onRemove,
    onEdit,
  });
};


export default connect(
  mapStateToProps,
  mapDispatchToProps,
  mergeProps,
)(SortableElement(CustomFieldsListRow));
