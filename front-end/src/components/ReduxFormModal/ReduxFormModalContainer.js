import { connect } from 'react-redux';
import ReduxFormModal from './ReduxFormModal';
import {
  formModalShowStateSelector,
  formModalItemSelector,
  formModalComponentSelector,
  formModalConfigSelector,
  formModalErrosSelector,
} from '@/selectors/guiSelectors';
import {
  hideFormModal,
  setFormModalItem,
  setFormModalError,
  updateFormModalItemField,
  removeFormModalItemField,
  showConfirmModal,
} from '@/actions/guiStateActions/pageActions';


const mapStateToProps = state => ({
  show: formModalShowStateSelector(state),
  item: formModalItemSelector(state),
  component: formModalComponentSelector(state),
  config: formModalConfigSelector(state),
  errors: formModalErrosSelector(state),
});

const mapDispatchToProps = dispatch => ({
  closeModal: () => {
    const onOk = () => dispatch(hideFormModal());
    const confirm = {
      message: 'Are you sure you want to close and discard all changes?',
      labelOk: 'Yes',
      type: 'delete',
      onOk,
    };
    return dispatch(showConfirmModal(confirm));
  },
  hideModal: callback => (params) => {
    if (callback && typeof callback === 'function') {
      const result = callback(params);
      if (result && result.then && typeof result.then === 'function') { // if Promise
        return result
          .then(() => { dispatch(hideFormModal()); })
          .catch(() => {});
      }
      if (result !== false) {
        return dispatch(hideFormModal());
      }
      return false;
    }
    return dispatch(hideFormModal());
  },
  setItem: (newItem) => {
    dispatch(setFormModalItem(newItem));
  },
  updateField: (path, value) => {
    dispatch(updateFormModalItemField(path, value));
  },
  removeField: (path) => {
    dispatch(removeFormModalItemField(path));
  },
  setError: (fieldId, message) => {
    dispatch(setFormModalError(fieldId, message));
  },
});

const mergeProps = (stateProps, dispatchProps, ownProps) => {
  let dispatchPropsOverrides = {};
  if (stateProps.config && stateProps.config.get('skipConfirmOnClose', false)) {
    dispatchPropsOverrides.closeModal = dispatchProps.hideModal();
  }  
  return ({
    ...stateProps,
    ...{...dispatchProps, ...dispatchPropsOverrides},
    ...ownProps,
  });
};

export default connect(mapStateToProps, mapDispatchToProps, mergeProps)(ReduxFormModal);
