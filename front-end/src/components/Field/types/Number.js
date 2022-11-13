import React from 'react';
import PropTypes from 'prop-types';
import { InputGroup } from 'react-bootstrap';
import isNumber from 'is-number';


const Number = (props) => {
  const { onChange, value, editable, disabled, tooltip, suffix, preffix, ...otherProps } = props;
  if (editable) {

    const onChangeNumber = (e) => {
      const { value:targetValue , id:targetId } = e.target;
      const convertedVal = isNumber(targetValue) ? parseFloat(targetValue) : targetValue;
      const convertedEvent = { target: { value:convertedVal, id: targetId} };
      onChange(convertedEvent);
    }

    const input = (
      <input
        {...otherProps}
        type="number"
        className="form-control"
        value={value}
        onChange={onChangeNumber}
        disabled={disabled}
        title={tooltip}
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
        {(preffix !== null) && preffix}
        {value}
        {(suffix !== null) && suffix}
      </span>
    </div>
  );
};


Number.defaultProps = {
  value: '',
  required: false,
  disabled: false,
  editable: true,
  placeholder: '',
  tooltip: '',
  suffix: null,
  preffix: null,
  onChange: () => {},
};

Number.propTypes = {
  value: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  required: PropTypes.bool,
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  placeholder: PropTypes.string,
  tooltip: PropTypes.string,
  suffix: PropTypes.node,
  preffix: PropTypes.node,
  onChange: PropTypes.func,
};

export default Number;
