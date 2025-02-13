import React, { useCallback, memo } from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';

const CreateButton = ({
  label, onClick, type, action, disabled, title, data, buttonStyle,
}) => {
  const cachedOnClick = useCallback(() => onClick(data), [onClick, data]);
  return (
    <Button
      bsSize="xsmall"
      className="btn-primary"
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

CreateButton.defaultProps = {
  label: 'New',
  action: '',
  type: '',
  title: '',
  data: null,
  disabled: false,
  buttonStyle: { marginTop: 15 },
  onClick: () => {},
};

CreateButton.propTypes = {
  label: PropTypes.string,
  action: PropTypes.string,
  type: PropTypes.string,
  data: PropTypes.any,
  title: PropTypes.string,
  buttonStyle: PropTypes.object,
  disabled: PropTypes.bool,
  onClick: PropTypes.func,
};

export default memo(CreateButton);
