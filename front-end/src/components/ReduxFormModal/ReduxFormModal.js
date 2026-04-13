import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { ModalWrapper } from '@/components/Elements';

const form = (WrappedComponent, {
  item, setItem, updateField, removeField, setError, ...otherProps
}) => (
  <WrappedComponent
    {...otherProps}
    item={item}
    setItem={setItem}
    setError={setError}
    updateField={updateField}
    removeField={removeField}
  />
);

const ReduxFormModal = (props) => {
  const {
    show, item, component, config, hideModal, closeModal, errors,
    ...otherProps
  } = props;
  if (!show) {
    return null;
  }
  if (show && !component) {
    throw new Error('ReduxFormModal require component parameter');
  }
  const {
    title, labelOk = 'Save', onOk, labelCancel = 'Cancel', onCancel, modalSize = 'large',
    allowSubmitWithError = false, showOnOk = true, ...configOtherProps
  } = config.toJS();
  const onOkWithHide = () => {
    if (!allowSubmitWithError) {
      const hasError = errors.some(error => !error || error.length > 0);
      if (hasError) {
        return false;
      }
    }
    const callback = hideModal(onOk);
    return callback(item);
  };
  const onCancelWithHide = hideModal(onCancel);
  return (
    <ModalWrapper
      show={true}
      title={title}
      onOk={onOkWithHide}
      labelOk={labelOk}
      onCancel={onCancelWithHide}
      labelCancel={labelCancel}
      onHide={closeModal}
      modalSize={modalSize}
      showOnOk={showOnOk}
    >
      { form(component, {
        item,
        errors,
        ...otherProps,
        ...configOtherProps,
      })}
    </ModalWrapper>
  );
};

ReduxFormModal.defaultProps = {
  show: false,
  item: undefined,
  component: undefined,
  config: Immutable.Map(),
  errors: Immutable.Map(),
};

ReduxFormModal.propTypes = {
  show: PropTypes.bool,
  item: PropTypes.instanceOf(Immutable.Map),
  component: PropTypes.oneOfType([
    PropTypes.object,
    PropTypes.element,
    PropTypes.func,
  ]),
  config: PropTypes.instanceOf(Immutable.Map),
  errors: PropTypes.instanceOf(Immutable.Map),
  hideModal: PropTypes.func.isRequired,
  closeModal: PropTypes.func.isRequired,
  setItem: PropTypes.func.isRequired,
  setError: PropTypes.func.isRequired,
  updateField: PropTypes.func.isRequired,
  removeField: PropTypes.func.isRequired,
};

export default ReduxFormModal;
