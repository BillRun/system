import React from 'react';
import PropTypes from 'prop-types';
import { Modal, Button } from 'react-bootstrap';
const ConfirmModal = ({
  show,
  message,
  type = 'confirm',
  children,
  labelCancel = 'Cancel',
  labelOk = 'OK',
  onCancel,
  onOk,
}) => (
  <Modal show={show}>
    <Modal.Header closeButton={false}>
      <Modal.Title>{ message }</Modal.Title>
    </Modal.Header>
    { children &&
      <Modal.Body>
        { children }
      </Modal.Body>
    }
    <Modal.Footer>
      {type !== 'alert' && (
        <Button size="sm" variant="outline-secondary" style={{ minWidth: 90, marginRight: 5 }} onClick={onCancel} >{labelCancel}</Button>
      )}
      <Button size="sm" style={{ minWidth: 90 }} onClick={onOk} variant={type === 'delete' ? 'danger' : 'primary'} >{labelOk}</Button>
    </Modal.Footer>
  </Modal>
);

ConfirmModal.propTypes = {
  show: PropTypes.bool.isRequired,
  message: PropTypes.string.isRequired,
  type: PropTypes.oneOf(['confirm', 'delete', 'alert']),
  children: PropTypes.node,
  labelCancel: PropTypes.string,
  labelOk: PropTypes.string,
  onCancel: PropTypes.func,
  onOk: PropTypes.func.isRequired,
};

export default ConfirmModal;
