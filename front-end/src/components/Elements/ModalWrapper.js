import React from 'react';
import PropTypes from 'prop-types';
import { Modal, Button } from 'react-bootstrap';

// Map Bootstrap 3 size names to Bootstrap 5 equivalents accepted by react-bootstrap v2
const mapModalSize = size => {
  if (size === 'large') return 'lg';
  if (size === 'small') return 'sm';
  return size; // 'lg', 'sm', 'xl', undefined — pass through
};

const ModalWrapper = props => {
  // onHide controls both backdrop/Escape closing AND the visible ×-button.
  // onCancel is only for the footer Cancel button — it does NOT add a × close button.
  // This matches react-bootstrap 0.31 behaviour: closeButton={props.onHide !== null}.
  const handleHide = props.onHide || null;
  return (
    <Modal
      show={props.show}
      size={mapModalSize(props.modalSize)}
      onHide={handleHide || props.onCancel || (() => {})}
      enforceFocus={typeof props.enforceFocus === 'boolean' ? props.enforceFocus : true}
      animation={props.animation !== false}
    >
      <div className="modal-header">
        { handleHide && (
          <button type="button" className="close" onClick={handleHide} aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        ) }
        <h4 className="modal-title">{ props.title }</h4>
      </div>
      <Modal.Body>
        { props.children }
      </Modal.Body>
      { (props.onCancel || (props.onOk && props.showOnOk)) &&
        <Modal.Footer>
          { props.onCancel && (
            <Button size="sm" style={{ minWidth: 90 }} onClick={props.onCancel} variant={props.styleCancel}>
              { props.labelCancel }
            </Button>
          ) }
          { props.onOk && props.showOnOk && (
            <Button size="sm" style={{ minWidth: 90 }} onClick={props.onOk} variant={props.styleOk} disabled={props.progress}>
              { props.progress && (<span><i className="fa fa-spinner fa-pulse" />&nbsp;&nbsp;</span>) }
              { (props.progress && props.labelProgress !== null)
                ? props.labelProgress
                : props.labelOk
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

ModalWrapper.defaultProps = {
  labelOk: 'Save',
  labelCancel: 'Cancel',
  showOnOk: true,
  progress: false,
  labelProgress: null,
  /** Cancel defaults to outline-secondary (white bg, grey border — BS3 btn-default look via index.css compat rule) */
  styleCancel: 'outline-secondary',
  /** OK defaults to primary (blue) */
  styleOk: 'primary',
};

export default ModalWrapper;
