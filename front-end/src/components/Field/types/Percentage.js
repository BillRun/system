import React from 'react';
import PropTypes from 'prop-types';
import { InputGroup } from 'react-bootstrap';
import isNumber from 'is-number';

const Percentage = ({
  onChange = () => {}, value = '', editable = true, disabled = false, tooltip = '', preffix = null, ...otherProps
}) => {

  const onChangePercentage = (e) => {
    const { value:targetValue , id:targetId } = e.target;
    const convertedVal = isNumber(targetValue) ? parseFloat(targetValue) / 100 : targetValue;
    const convertedEvent = { target: { value:convertedVal, id: targetId} };
    onChange(convertedEvent);
  }

  const displayValue = isNumber(value) ? parseFloat((parseFloat(value) * 100).toFixed(3)) : value;

  if (editable) {
    return (
      <InputGroup>
        {preffix !== null && (<InputGroup.Text>{preffix}</InputGroup.Text>)}
        <input
          {...otherProps}
          type="number"
          className="form-control"
          value={displayValue}
          onChange={onChangePercentage}
          disabled={disabled}
          title={tooltip}
        />
        <InputGroup.Text>%</InputGroup.Text>
      </InputGroup>
    );
  }

  return (
    <div className="non-editable-field">
      <span>
        {(preffix !== null) && `${preffix} `}
        {displayValue}
        {isNumber(displayValue) && '%'}
      </span>
    </div>
  );
};

Percentage.propTypes = {
  value: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  required: PropTypes.bool,
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  placeholder: PropTypes.string,
  tooltip: PropTypes.string,
  preffix: PropTypes.node,
  onChange: PropTypes.func,
};

export default Percentage;
