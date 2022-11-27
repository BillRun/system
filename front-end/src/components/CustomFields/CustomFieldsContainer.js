import { connect } from 'react-redux';
import { Map, List } from 'immutable';
import { titleCase, lowerCase } from 'change-case';
import CustomFields from './CustomFields';
import CustomFieldFormContainer from './CustomFieldFormContainer';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import {
  showWarning,
} from '@/actions/alertsActions';

import {
  addField,
  updateField,
  removeField,
  getFields,
  saveAndReloadFields,
  setFlag,
  removeFlag,
  removeFlags,
  validateField,
} from '@/actions/customFieldsActions';
import {
  setFieldPosition,
} from '@/actions/settingsActions';
import {
  customFieldsEntityFieldsSelector,
} from '@/selectors/customFieldsSelectors';
import {
  pageFlagSelector,
} from '@/selectors/guiSelectors';
import {
  getConfig,
  getSettingsPath,
  inConfigOptionBlackList,
  inConfigOptionWhiteList,
} from '@/common/Util';


const defaultNewField = Map({
  field_name: '',
  title: '',
  editable: true,
  display: true,
});


const mapStateToProps = (state, props) => {
  const tabs = getConfig(['customFields', 'entities'], Map()).keySeq().toList();
  const fieldsSettings = tabs.reduce((acc, entity) => acc.set(entity, Map({
    fields: customFieldsEntityFieldsSelector(state, props, entity) || List(),
    hiddenFields: getConfig(['customFields', 'defaultHiddenFields', entity], List()),
    disabledFields: getConfig(['customFields', 'defaultDisabledFields', entity], List()),
    unReorderFields: getConfig(['customFields', 'unReorderFields', entity], List()),
  })), Map());
  const orderChanged = tabs.reduce((acc, entity) =>
    acc.set(entity, pageFlagSelector(state, props, 'customFields', `orderChanged.${entity}`),
  ), Map());
  return ({ tabs, fieldsSettings, orderChanged });
};


const mapDispatchToProps = (dispatch, props) => ({ // eslint-disable-line no-unused-vars

  loadFields: entity => dispatch(getFields(entity)),

  clearFlags: () => dispatch(removeFlags()),

  onRemove: (entity, item) => {
    const onOk = () => {
      dispatch(removeField(entity, item));
      return dispatch(saveAndReloadFields(entity));
    };
    const confirm = {
      message: `Are you sure you want to delete "${item.get('title', item.get('field_name', ''))}" field?`,
      onOk,
      labelOk: 'Delete',
      type: 'delete',
    };
    return dispatch(showConfirmModal(confirm));
  },

  onEdit: (entity, item) => {
    const onOk = (editedItem) => {
      dispatch(updateField(entity, editedItem));
      return dispatch(saveAndReloadFields(entity));
    };
    const entityName = getConfig(['systemItems', entity, 'itemName'], entity);
    const mode = 'edit';
    const title = `${titleCase(`${mode} ${entityName} field`)} - ${item.get('title', '')}`;
    const config = { title, onOk, mode, entity };
    return dispatch(showFormModal(item, CustomFieldFormContainer, config));
  },

  onNew: (entity, existingFields = List()) => {
    const fieldTypesOptions = getConfig(['customFields', 'fields'], List())
      .filter(option => (
        !inConfigOptionBlackList(option, entity, 'excludeEntity')
        && inConfigOptionWhiteList(option, entity, 'includeEntity')
      ))
      .reduce((acc, option) => acc.push(option.get('id', '')), List());
    if (fieldTypesOptions.isEmpty()) {
      return dispatch(showWarning('No available field types '));
    }
    const onOk = (newItem) => {
      // set new field as params for entities in saveAsParams list
      const saveAsParams = getConfig(['customFields', 'saveAsParams'], List())
      const newField = !saveAsParams.includes(entity) ? newItem : newItem
        .withMutations((newItemWithMutations) => {
          const fieldName = newItem.get('field_name');
          if (!fieldName.startsWith('params.')) {
            newItemWithMutations.set('field_name', `params.${fieldName}`)
          }
        })
      if (!dispatch(validateField(newField, existingFields))) {
        return false;
      }
      dispatch(addField(entity, newField));
      return dispatch(saveAndReloadFields(entity));
    };
    const entityName = getConfig(['systemItems', entity, 'itemName'], entity);
    const mode = 'create';
    const title = titleCase(`${mode} new ${entityName} field`);
    const config = { title, onOk, mode, entity, existingFields };
    const newField = fieldTypesOptions.includes('text') ? defaultNewField : defaultNewField.set('type', fieldTypesOptions.first());
    return dispatch(showFormModal(newField, CustomFieldFormContainer, config));
  },

  onReorder: (entity, { oldIndex, newIndex }) => {
    if (oldIndex === newIndex) {
      return true;
    }
    dispatch(setFlag(`orderChanged.${entity}`, true));
    const path = getSettingsPath(entity, true, ['fields']);
    return dispatch(setFieldPosition(oldIndex, newIndex, path));
  },

  onCancel: (entity, save = false) => {
    dispatch(removeFlag(`reorder.${entity}`));
    dispatch(removeFlag(`orderChanged.${entity}`));
    return (save) ? dispatch(getFields(entity)) : true;
  },

  onSave: (entity, save = false) => {
    dispatch(removeFlag(`reorder.${entity}`));
    dispatch(removeFlag(`orderChanged.${entity}`));
    return (save) ? dispatch(saveAndReloadFields(entity)) : true;
  },
});


const mergeProps = (stateProps, dispatchProps, ownProps) => {
  const {
    children, dispatch, history, params, route, routeParams, routes, ...otherOwnProps
  } = ownProps;
  const { fieldsSettings } = stateProps;
  const { onNew: onNewFromDispatchProps, ...otherDispatchProps } = dispatchProps;
  // Override onNew() from dispatchProps to pass existingFields from stateProps
  const onNew = (entity) => {
    const existingFields = fieldsSettings
      .getIn([entity, 'fields'], List())
      .map(field => lowerCase(field.get('field_name', '')));
    return onNewFromDispatchProps(entity, existingFields);
  };
  return {
    ...otherOwnProps,
    ...stateProps,
    ...otherDispatchProps,
    onNew,
  };
};

export default connect(mapStateToProps, mapDispatchToProps, mergeProps)(CustomFields);
