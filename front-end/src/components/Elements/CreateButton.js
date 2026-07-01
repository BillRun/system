import React, { useCallback, memo } from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';
const CreateButton = ({
  // Default matches react-bootstrap 0.31 behavior (bsSize="xsmall" → .btn-xs).
  // RB2 has no native xs size, so we drive it through className.
  label = 'New', onClick = () => {}, type = '', action = '', disabled = false, title = '', data = null, buttonStyle = { marginTop: 15 }, buttonClass = 'btn-xs',
}) => {
  const cachedOnClick = useCallback(() => onClick(data), [onClick, data]);
  const isXs = /\bbtn-xs\b/.test(buttonClass);
  return (
    <Button variant="primary"
      size={isXs ? undefined : 'sm'}
      className={buttonClass}
      onClick={cachedOnClick}
      style={buttonStyle}
      disabled={disabled}
      title={title}
    >
      <i className="fa fa-plus" />
      {action.length > 0 && ` ${action}`}
      {label.length > 0 && ` ${label}`}
      {type.length > 0 && ` ${type}`}
    </Button>
  );
}

CreateButton.propTypes = {
  label: PropTypes.string,
  action: PropTypes.string,
  type: PropTypes.string,
  data: PropTypes.any,
  title: PropTypes.string,
  buttonStyle: PropTypes.object,
  disabled: PropTypes.bool,
  buttonClass: PropTypes.string,
  onClick: PropTypes.func,
};

export default memo(CreateButton);
