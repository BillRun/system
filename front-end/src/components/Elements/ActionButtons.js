import React from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';

const ActionButtons = (props) => {
  const { hide, progress, progressLabel, reversed } = props;
  const { saveLabel, hideSave, disableSave, onClickSave } = props;
  const { cancelLabel, hideCancel, disableCancel, onClickCancel } = props;
  if (hide) {
    return null;
  }
  return (
    <div style={{ marginTop: 12 }}>
      {!hideSave && (
        <Button onClick={onClickSave} bsStyle={reversed ? 'default' : 'primary'} disabled={progress || disableSave} style={{ minWidth: 90, marginRight: 10 }}>
          { progress && (<span><i className="fa fa-spinner fa-pulse" />&nbsp;&nbsp;</span>) }
          { progress && progressLabel !== null
            ? progressLabel
            : saveLabel
          }
        </Button>
      )}
      {!hideCancel && (
        <Button onClick={onClickCancel} bsStyle={reversed ? 'primary' : 'default'} disabled={disableCancel} style={{ minWidth: 90 }}>
          {cancelLabel}
        </Button>
      )}
      { props.children }
    </div>
  );
};

ActionButtons.defaultProps = {
  children: null,
  hide: false,
  hideCancel: false,
  hideSave: false,
  progress: false,
  disableSave: false,
  disableCancel: false,
  cancelLabel: 'Cancel',
  saveLabel: 'Save',
  progressLabel: null,
  reversed: false,
  onClickCancel: () => {},
  onClickSave: () => {},
};

ActionButtons.propTypes = {
  children: PropTypes.element,
  cancelLabel: PropTypes.string,
  hide: PropTypes.bool,
  hideCancel: PropTypes.bool,
  hideSave: PropTypes.bool,
  progress: PropTypes.bool,
  disableSave: PropTypes.bool,
  disableCancel: PropTypes.bool,
  reversed: PropTypes.bool,
  onClickCancel: PropTypes.func,
  onClickSave: PropTypes.func,
  saveLabel: PropTypes.string,
  progressLabel: PropTypes.string,
};

export default ActionButtons;
