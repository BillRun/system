import React from 'react';
import PropTypes from 'prop-types';
import { InputGroup } from 'react-bootstrap';


const Password = ({
  onChange, value, editable, disabled, suffix, preffix, ...otherProps
}) => {
  if (editable) {
    const input = (
      <input
        {...otherProps}
        type="password"
        className="form-control"
        value={value}
        onChange={onChange}
        disabled={disabled}
      />
    );
    if (suffix !== null || preffix !== null) {
      return (
        <InputGroup>
          {preffix !== null && (<InputGroup.Addon>{preffix}</InputGroup.Addon>)}
          {input}
          {suffix !== null && (<InputGroup.Addon>{suffix}</InputGroup.Addon>)}
        </InputGroup>
      );
    }
    return input;
  }

  return (
    <div className="non-editable-field">
      <span>
        {(preffix !== null) && `${preffix} `}
        ******
        {(suffix !== null) && ` ${suffix}`}
      </span>
    </div>
  );
};


Password.defaultProps = {
  value: '',
  required: false,
  disabled: false,
  editable: true,
  placeholder: '',
  suffix: null,
  preffix: null,
  onChange: () => {},
};

Password.propTypes = {
  value: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  required: PropTypes.bool,
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  placeholder: PropTypes.string,
  suffix: PropTypes.node,
  preffix: PropTypes.node,
  onChange: PropTypes.func,
};

export default Password;
