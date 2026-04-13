import React, { memo } from 'react';
import PropTypes from 'prop-types';
import classNames from 'classnames';


const inputFileStyle = {height: 0, width: 0, overflow: 'hidden', display: 'inline-block' };


const AddFileButton = ({
  label, onClick, fileType, multiple, disabled, buttonStyle, buttonSize
}) => {
  const buttonClass = classNames('custom-file-upload', 'btn', 'full-width', {
    'btn-xs': buttonSize === 'xsmall',
    'btn-sm': buttonSize === 'small',
    'btn-lg': buttonSize === 'large',
    'btn-primary': buttonStyle === 'primary',
    'btn-success': buttonStyle === 'success',
    'btn-info': buttonStyle === 'info',
    'btn-warning': buttonStyle === 'warning',
    'btn-danger': buttonStyle === 'danger',
    'btn-link': buttonStyle === 'link',
    'btn-default': buttonStyle === 'default',
  });
  return (
    <label className={buttonClass}>
        <input
          type="file"
          accept={fileType}
          onChange={onClick}
          multiple={multiple}
          disabled={disabled}
          style={inputFileStyle}
        />
      <i className="fa fa-plus" /> {label}
    </label>
  );
}

AddFileButton.defaultProps = {
  label: 'Add File',
  action: '',
  fileType: '.csv',
  multiple: true,
  disabled: false,
  buttonStyle: 'primary',
  buttonSize: undefined,
  onClick: () => {},
};

AddFileButton.propTypes = {
  label: PropTypes.string,
  action: PropTypes.string,
  fileType: PropTypes.string,
  multiple: PropTypes.bool,
  disabled: PropTypes.bool,
  buttonStyle: PropTypes.oneOf(['primary', 'success', 'info', 'warning', 'danger', 'link', 'default']),
  buttonSize: PropTypes.oneOf(['large', 'small', 'xsmall']),
  onClick: PropTypes.func,
};

export default memo(AddFileButton);
