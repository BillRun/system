import React from 'react';
import PropTypes from 'prop-types';
import { Modal, Button } from 'react-bootstrap';


const ModalWrapper = props => (
  <Modal show={props.show} bsSize={props.modalSize}>
    <Modal.Header closeButton={props.onHide !== null} onHide={props.onHide}>
      <Modal.Title>{ props.title }</Modal.Title>
    </Modal.Header>
    <Modal.Body>
      { props.children }
    </Modal.Body>
    { (props.onCancel || (props.onOk && props.showOnOk)) &&
      <Modal.Footer>
        { props.onCancel && (
          <Button bsSize="small" style={{ minWidth: 90 }} onClick={props.onCancel} bsStyle={props.styleCancel}>
            { props.labelCancel }
          </Button>
        ) }
        { props.onOk && props.showOnOk && (
          <Button bsSize="small" style={{ minWidth: 90 }} onClick={props.onOk} bsStyle={props.styleOk} disabled={props.progress}>
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

ModalWrapper.defaultProps = {
  children: null,
  title: '',
  show: false,
  progress: false,
  labelOk: 'OK',
  labelCancel: 'Cancel',
  labelProgress: null,
  modalSize: undefined,
  onHide: null,
  onOk: null,
  showOnOk: true,
  onCancel: null,
  styleOk: 'primary',
  styleCancel: undefined,
};

ModalWrapper.propTypes = {
  children: PropTypes.element,
  labelOk: PropTypes.string,
  labelCancel: PropTypes.string,
  onOk: PropTypes.func,
  showOnOk: PropTypes.bool,
  onCancel: PropTypes.func,
  show: PropTypes.bool.isRequired,
  progress: PropTypes.bool,
  labelProgress: PropTypes.string,
  modalSize: PropTypes.oneOf(['large', 'small', undefined]),
  title: PropTypes.node,
  onHide: PropTypes.func,
  styleOk: PropTypes.string,
  styleCancel: PropTypes.string,
};

export default ModalWrapper;
