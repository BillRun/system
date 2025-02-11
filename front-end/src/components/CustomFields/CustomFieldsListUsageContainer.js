import { connect } from 'react-redux';
import { Map, List } from 'immutable'; // eslint-disable-line no-unused-vars
import { titleCase } from 'change-case';
import CustomFieldsListUsage from './CustomFieldsListUsage';
import CustomFieldForeignFormContainer from './CustomFieldForeignFormContainer';
import {
  customFieldsEntityFieldsSelector,
} from '../../selectors/customFieldsSelectors';
import {
  showConfirmModal,
  showFormModal,
} from '../../actions/guiStateActions/pageActions';
import {
  addField,
  updateField,
  removeField,
  saveAndReloadFields,
  validateForeignField,
} from '../../actions/customFieldsActions';
import {
  getConfig,
} from '../../common/Util';


const defaultNewForeignField = Map({
  field_name: '',
  title: '',
  foreign: Map({
    entity: '',
    field: '',
  }),
});


const mapStateToProps = (state, props) => {
  const entitiesFieldsConfig = customFieldsEntityFieldsSelector(state, props) || Map();
  return ({
    fields: entitiesFieldsConfig.get('usage'),
    entitiesFieldsConfig,
  });
};


const mapDispatchToProps = (dispatch, props) => ({ // eslint-disable-line no-unused-vars
  onNew: (entity, existingField) => {
    const onOk = (newItem) => {
      if (!dispatch(validateForeignField(newItem, existingField))) {
        return false;
      }
      dispatch(addField(entity, newItem));
      return dispatch(saveAndReloadFields(entity));
    };
    const entityName = getConfig(['systemItems', entity, 'itemName'], entity);
    const mode = 'create';
    const title = titleCase(`${mode} new ${entityName} foreign field`);
    const config = { title, onOk, mode, entity };

    return dispatch(showFormModal(defaultNewForeignField, CustomFieldForeignFormContainer, config));
  },

  onEdit: (item) => {
    const onOk = (editedItem) => {
      if (!dispatch(validateForeignField(editedItem))) {
        return false;
      }
      dispatch(updateField('usage', editedItem));
      return dispatch(saveAndReloadFields('usage'));
    };
    const entityName = getConfig(['systemItems', 'usage', 'itemName'], 'usage');
    const mode = 'edit';
    const title = `${titleCase(`${mode} ${entityName} foreign field`)} - ${item.get('title', '')}`;
    const config = { title, onOk, mode, entity: 'usage' };
    return dispatch(showFormModal(item, CustomFieldForeignFormContainer, config));
  },

  onRemove: (item) => {
    const entity = 'usage';
    const onOk = () => {
      dispatch(removeField(entity, item));
      return dispatch(saveAndReloadFields(entity));
    };
    const confirm = {
      message: `Are you sure you want to delete "${item.get('title', item.get('field_name', ''))}" foreign field?`,
      onOk,
      labelOk: 'Delete',
      type: 'delete',
    };
    return dispatch(showConfirmModal(confirm));
  },
});


const mergeProps = (stateProps, dispatchProps, ownProps) => {
  const { fields } = stateProps;
  const { onEdit, onRemove, onNew, ...otherDispatchProps } = dispatchProps;
  const addAction = [
    { type: 'add', onClick: entity => onNew(entity, fields), label: 'Add new foreign field', actionStyle: 'primary', actionSize: 'xsmall' },
  ];
  const rowActions = [
    { type: 'edit', onClick: onEdit },
    { type: 'remove', onClick: onRemove },
  ];
  return ({
    ...stateProps,
    ...otherDispatchProps,
    ...ownProps,
    addAction,
    rowActions,
  });
};


export default connect(mapStateToProps, mapDispatchToProps, mergeProps)(CustomFieldsListUsage);
