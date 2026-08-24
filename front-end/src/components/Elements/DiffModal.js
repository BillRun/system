import React from 'react';
import PropTypes from 'prop-types';
import { diffLines } from 'diff';
import { Modal, Button } from 'react-bootstrap';

const stringifyInput = (value, type) => {
  if (type === 'json') {
    try {
      return JSON.stringify(value, null, 2);
    } catch (e) {
      return String(value);
    }
  }
  return String(value);
};

const DiffModal = ({ inputNew, inputOld, title = 'Diff', show = true, onClose, diffType = 'json', closeLabel = 'Close' }) => {
  const oldText = stringifyInput(inputOld, diffType);
  const newText = stringifyInput(inputNew, diffType);
  const parts = diffLines(oldText, newText);

  return (
    <Modal show={show} onHide={onClose}>
      <div className="modal-header">
        <button type="button" className="close" onClick={onClose} aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 className="modal-title">{title}</h4>
      </div>
      <Modal.Body>
        <pre style={{ maxHeight: 560, overflowY: 'auto', whiteSpace: 'pre-wrap' }}>
          {parts.map((part, index) => {
            let backgroundColor = 'transparent';
            if (part.added) backgroundColor = '#dff0d8';
            if (part.removed) backgroundColor = '#f2dede';
            return (
              <span key={index} style={{ backgroundColor }}>
                {part.value}
              </span>
            );
          })}
        </pre>
      </Modal.Body>
      <Modal.Footer>
        <div className="push-left" style={{ width: '50%', display: 'inline-block' }}>
          <p className="text-center" style={{ backgroundColor: 'salmon', marginBottom: 5 }}>Old value</p>
          <p className="text-center" style={{ backgroundColor: 'lightgreen', marginBottom: 5 }}>New value</p>
        </div>
        <div className="push-right" style={{ width: '50%', display: 'inline-block' }}>
          <Button onClick={onClose}>{closeLabel}</Button>
        </div>
      </Modal.Footer>
    </Modal>
  );
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
