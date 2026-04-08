import React from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';
const ActionButtons = (props) => {
  const { hide, progress, progressLabel, reversed } = props;
  const { saveLabel, hideSave, disableSave, onClickSave, saveTitle } = props;
  const { cancelLabel, hideCancel, disableCancel, onClickCancel, cancelTitle } = props;
  if (hide) {
    return null;
  }
  // Determine button variants.
  // 'default' is Bootstrap 3-only; yeti.css has .btn-default but react-bootstrap v2
  // also needs btn-secondary / btn-light for proper styling. We map:
  //   primary action  → 'primary'  (blue, white text)
  //   secondary/cancel→ 'default'  (white, dark text — defined in yeti.css)
  const saveVariant = reversed ? 'default' : 'primary';
  const cancelVariant = reversed ? 'primary' : 'default';
  return (
    <div style={{ marginTop: 12 }}>
      {!hideSave && (
        <Button onClick={onClickSave} variant={saveVariant} disabled={progress || disableSave} style={{ minWidth: 90, marginRight: 10 }} title={saveTitle}>
          { progress && (<span><i className="fa fa-spinner fa-pulse" />&nbsp;&nbsp;</span>) }
          { progress && progressLabel !== null
            ? progressLabel
            : saveLabel
          }
        </Button>
      )}
      {!hideCancel && (
        <Button onClick={onClickCancel} variant={cancelVariant} disabled={disableCancel} style={{ minWidth: 90 }} title={cancelTitle}>
          {cancelLabel}
        </Button>
      )}
      { props.children }
    </div>
  );
};

ActionButtons.propTypes = {
  children: PropTypes.element,
  cancelLabel: PropTypes.string,
  cancelTitle: PropTypes.string,
  saveLabel: PropTypes.string,
  saveTitle: PropTypes.string,
  hide: PropTypes.bool,
  hideCancel: PropTypes.bool,
  hideSave: PropTypes.bool,
  progress: PropTypes.bool,
  disableSave: PropTypes.bool,
  disableCancel: PropTypes.bool,
  reversed: PropTypes.bool,
  onClickCancel: PropTypes.func,
  onClickSave: PropTypes.func,
  progressLabel: PropTypes.string,
};

ActionButtons.defaultProps = {
  saveLabel: 'Save',
  cancelLabel: 'Cancel',
  hide: false,
  hideCancel: false,
  hideSave: false,
  progress: false,
  disableSave: false,
  disableCancel: false,
  reversed: false,
  progressLabel: null,
};

export default ActionButtons;
