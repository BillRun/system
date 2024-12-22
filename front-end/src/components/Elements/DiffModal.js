import React from 'react';
import PropTypes from 'prop-types';
import Diff from 'react-stylable-diff';
import { Modal, Button } from 'react-bootstrap';


const DiffModal = ({ inputNew, inputOld, title, show, onClose, diffType, closeLabel }) => (
  <Modal show={show} onHide={onClose}>
    <Modal.Header closeButton>
      <Modal.Title>{title}</Modal.Title>
    </Modal.Header>
    <Modal.Body>
      <pre>
        <Diff inputA={inputOld} inputB={inputNew} type={diffType} />
      </pre>
    </Modal.Body>
    <Modal.Footer>
      <div className="push-left" style={{ width: '50%', display: 'inline-block' }}>
        <p className="text-center" style={{ backgroundColor: 'salmon', marginBottom: 5 }}> Old value </p>
        <p className="text-center" style={{ backgroundColor: 'lightgreen', marginBottom: 5 }}> New value </p>
      </div>
      <div className="push-right" style={{ width: '50%', display: 'inline-block' }}>
        <Button onClick={onClose}>{closeLabel}</Button>
      </div>
    </Modal.Footer>
  </Modal>
);


DiffModal.defaultProps = {
  show: true,
  closeLabel: 'Close',
  diffType: 'json',
  title: 'Diff',
};

DiffModal.propTypes = {
  inputNew: PropTypes.any.isRequired,
  inputOld: PropTypes.any.isRequired,
  onClose: PropTypes.func.isRequired,
  show: PropTypes.bool,
  closeLabel: PropTypes.string,
  diffType: PropTypes.string,
  title: PropTypes.string,
};

export default DiffModal;
