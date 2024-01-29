import React from 'react';
import PropTypes from 'prop-types';
import { Modal, Button } from 'react-bootstrap';


const ConfirmModal = props => (
  <Modal show={props.show}>
    <Modal.Header closeButton={false}>
      <Modal.Title>{ props.message }</Modal.Title>
    </Modal.Header>
    { props.children &&
      <Modal.Body>
        { props.children }
      </Modal.Body>
    }
    <Modal.Footer>
      {props.type !== 'alert' && (
        <Button bsSize="small" style={{ minWidth: 90, marginRight: 5 }} onClick={props.onCancel} >{props.labelCancel}</Button>
      )}
      <Button bsSize="small" style={{ minWidth: 90 }} onClick={props.onOk} bsStyle={props.type === 'delete' ? 'danger' : 'primary'} >{props.labelOk}</Button>
    </Modal.Footer>
  </Modal>
);

ConfirmModal.defaultProps = {
  show: false,
  type: 'confirm',
  children: null,
  labelCancel: 'Cancel',
  labelOk: 'Ok',
  onCancel: null,
};

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
