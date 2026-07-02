import React from 'react';
import PropTypes from 'prop-types';
import { Modal, Button } from 'react-bootstrap';

// Map Bootstrap 3 size names to Bootstrap 5 equivalents accepted by react-bootstrap v2
const mapModalSize = size => {
  if (size === 'large') return 'lg';
  if (size === 'small') return 'sm';
  return size; // 'lg', 'sm', 'xl', undefined — pass through
};

const ModalWrapper = ({
  show,
  title,
  children,
  onOk,
  onCancel,
  onHide,
  modalSize,
  animation,
  enforceFocus,
  labelOk = 'Save',
  labelCancel = 'Cancel',
  showOnOk = true,
  progress = false,
  labelProgress = null,
  /** Cancel defaults to outline-secondary (white bg, grey border — BS3 btn-default look via index.css compat rule) */
  styleCancel = 'outline-secondary',
  /** OK defaults to primary (blue) */
  styleOk = 'primary',
}) => {
  // onHide controls both backdrop/Escape closing AND the visible ×-button.
  // onCancel is only for the footer Cancel button — it does NOT add a × close button.
  // This matches react-bootstrap 0.31 behaviour: closeButton={props.onHide !== null}.
  const handleHide = onHide || null;
  return (
    <Modal
      show={show}
      size={mapModalSize(modalSize)}
      onHide={handleHide || onCancel || (() => {})}
      enforceFocus={typeof enforceFocus === 'boolean' ? enforceFocus : true}
      animation={animation !== false}
    >
      <div className="modal-header">
        { handleHide && (
          <button type="button" className="close" onClick={handleHide} aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        ) }
        <h4 className="modal-title">{ title }</h4>
      </div>
      <Modal.Body>
        { children }
      </Modal.Body>
      { (onCancel || (onOk && showOnOk)) &&
        <Modal.Footer>
          { onCancel && (
            <Button size="sm" style={{ minWidth: 90 }} onClick={onCancel} variant={styleCancel}>
              { labelCancel }
            </Button>
          ) }
          { onOk && showOnOk && (
            <Button size="sm" style={{ minWidth: 90 }} onClick={onOk} variant={styleOk} disabled={progress}>
              { progress && (<span><i className="fa fa-spinner fa-pulse" />&nbsp;&nbsp;</span>) }
              { (progress && labelProgress !== null)
                ? labelProgress
                : labelOk
              }
            </Button>
          ) }
        </Modal.Footer>
      }
    </Modal>
  );
};

ModalWrapper.propTypes = {
  children: PropTypes.node,
  labelOk: PropTypes.string,
  labelCancel: PropTypes.string,
  onOk: PropTypes.func,
  showOnOk: PropTypes.bool,
  onCancel: PropTypes.func,
  show: PropTypes.bool.isRequired,
  progress: PropTypes.bool,
  labelProgress: PropTypes.string,
  modalSize: PropTypes.oneOf(['large', 'small', 'lg', 'sm', 'xl', undefined]),
  title: PropTypes.node,
  onHide: PropTypes.func,
  /** When false, disables Fade transition (avoids React 18+/19 issues with modal transitions). */
  animation: PropTypes.bool,
  enforceFocus: PropTypes.bool,
  styleOk: PropTypes.string,
  styleCancel: PropTypes.string,
};


export default ModalWrapper;
