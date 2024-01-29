import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { ConfirmModal } from '@/components/Elements';
import { hideConfirmModal } from '@/actions/guiStateActions/pageActions';
import { confirmSelector } from '@/selectors/guiSelectors';


const ReduxConfirmModal = ({ confirm, dispatch }) => {
  if (confirm.get('show', false) === false) {
    return null;
  }

  const hideConfirm = callback => () => {
    if (callback && typeof callback === 'function') {
      callback();
    }
    dispatch(hideConfirmModal());
  };
  const labelOk = confirm.get('labelOk');
  const onOk = hideConfirm(confirm.get('onOk'));
  const labelCancel = confirm.get('labelCancel');
  const onCancel = hideConfirm(confirm.get('onCancel'));
  const message = confirm.get('message');
  const type = confirm.get('type');
  const children = confirm.get('children');
  return (
    <ConfirmModal
      show={true}
      labelOk={labelOk}
      onOk={onOk}
      labelCancel={labelCancel}
      onCancel={onCancel}
      message={message}
      type={type}
    >
      {children}
    </ConfirmModal>
  );
};

ReduxConfirmModal.defaultProps = {
  confirm: Immutable.Map(),
};

ReduxConfirmModal.propTypes = {
  confirm: PropTypes.instanceOf(Immutable.Map),
  dispatch: PropTypes.func.isRequired,
};


const mapStateToProps = state => ({
  confirm: confirmSelector(state),
});

export default connect(mapStateToProps)(ReduxConfirmModal);
