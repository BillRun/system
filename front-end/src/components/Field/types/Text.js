import React from 'react';
import PropTypes from 'prop-types';
import { InputGroup } from 'react-bootstrap';
const Text = ({
  onChange = () => {}, value = '', editable = true, disabled = false, suffix = null, preffix = null, ...otherProps
}) => {
  if (editable) {
    const input = (
      <input
        {...otherProps}
        type="text"
        className="form-control"
        value={value}
        onChange={onChange}
        disabled={disabled}
      />
    );
    // `suffix`/`preffix` may be `undefined` when not provided; treat both nullish
    // values as "absent" to avoid rendering empty InputGroup.Text placeholders.
    if (suffix != null || preffix != null) {
      return (
        <InputGroup>
          {preffix != null && (<InputGroup.Text>{preffix}</InputGroup.Text>)}
          {input}
          {suffix != null && (<InputGroup.Text>{suffix}</InputGroup.Text>)}
        </InputGroup>
      );
    }
    return input;
  }

  return (
    <div className="non-editable-field">
      <span>
        {(preffix != null) && `${preffix} `}
        {value}
        {(suffix != null) && ` ${suffix}`}
      </span>
    </div>
  );
};

Text.propTypes = {
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

export default Text;
