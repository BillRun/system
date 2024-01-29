import React from 'react';
import { connect } from 'react-redux';
import { Modal } from 'react-bootstrap';

const NotSupportedModal = (props) => (
  <Modal show={ props.show } onHide={ props.onClose }>
    <Modal.Header closeButton>
      <Modal.Title>Unsupported</Modal.Title>
    </Modal.Header>
    <Modal.Body>
      { props.gateway } is not yet supported, but will be added soon!
    </Modal.Body>
    <Modal.Footer>
      <button type="button" className="btn btn-default" onClick={ props.onClose }>Close</button>
    </Modal.Footer>
  </Modal>
);

export default connect()(NotSupportedModal);
