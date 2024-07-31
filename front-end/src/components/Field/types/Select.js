import React from 'react';
import PropTypes from 'prop-types';
import ReactSelect from 'react-select';
import Creatable from 'react-select/creatable';
import AsyncSelect from 'react-select/async';


const Select = ({
  value, onChange, editable, disabled,
  multi, clearable, noResultsText, options, allowCreate, addLabelText, placeholder,
  isAsync, isControlled, loadAsyncOptions,
  ...otherProps
}) => {
  const fixBoolValues = (value) => {
    if (value === 'false') {
      return false;
    }
    if (value === 'true') {
      return true;
    }
    return value;
  }
  /* Support old existing Select fileds from v0.9.x*/
  const legacyValues = ((multi) ? value.split(',').map(fixBoolValues) : [value]).filter(val => val !== '');

  if (!editable) {
    const displayValue = options
    .filter(option => legacyValues.includes(option.value))
    .map(option => option.label)
    .join(', ');
    return (
      <div className="non-editable-field">
        {displayValue}
      </div>
    );
  }

  const isDisabled = disabled;
  const isMulti = multi;
  const isClearable = clearable;
  let noOptionsMessage = noResultsText;
  if (typeof noResultsText !== 'undefined' && typeof noResultsText === "string") {
    noOptionsMessage = () => noResultsText;
  }
  let formatCreateLabel = addLabelText;
  if (typeof addLabelText !== 'undefined' && typeof addLabelText === "string") {
    formatCreateLabel = (label) => addLabelText.replace('{label}', label);
  }


  const onChangeValue = (option, { action, removedValue, name }) => {
    let newValue = '';
    if (action !== 'clear' && option !== null) {
      newValue = (multi) ? option.map(opt => opt.value).join(',') : option.value;
    }
    return onChange(newValue, {option, action, removedValue, name});
  }

  let selectValue = '';
  if (isMulti) {
    selectValue = legacyValues.map(legacyValue => {
      const index = options.findIndex(option => legacyValue === option.value);
      return (index !== -1) ? options[index] : { value: legacyValue, label: legacyValue };
    });
  } else {
    if (isAsync) {
      selectValue = value;
    }
    else if (value !== '') {
      const index = options.findIndex(option => value === option.value);
      selectValue = (index !== -1) ? options[index] : { value, label: value };
    } else {
      selectValue = null;
    }
  }
  if (isAsync && !isControlled) {
    return (
      <AsyncSelect
        {...otherProps}
        placeholder={placeholder}
        classNamePrefix="react-select"
        onChange={onChangeValue}
        isMulti={isMulti}
        isClearable={isClearable}
        isDisabled={isDisabled}
        noOptionsMessage={noOptionsMessage}
        formatCreateLabel={formatCreateLabel}
        loadOptions={loadAsyncOptions}
      />
    );
  }

  if (isAsync) {
    return (
      <AsyncSelect
        {...otherProps}
        placeholder={placeholder}
        value={selectValue}
        classNamePrefix="react-select"
        onChange={onChangeValue}
        isMulti={isMulti}
        isClearable={isClearable}
        isDisabled={isDisabled}
        noOptionsMessage={noOptionsMessage}
        formatCreateLabel={formatCreateLabel}
        loadOptions={loadAsyncOptions}
      />
      );
  }

  if (allowCreate) {
    return (
      <Creatable
        {...otherProps}
        options={options}
        placeholder={placeholder}
        value={selectValue}
        classNamePrefix="react-select"
        onChange={onChangeValue}
        isMulti={isMulti}
        isClearable={isClearable}
        isDisabled={isDisabled}
        noOptionsMessage={noOptionsMessage}
        formatCreateLabel={formatCreateLabel}
      />
    );
  }

  return (
    <ReactSelect
      {...otherProps}
      placeholder={placeholder}
      options={options}
      value={selectValue}
      classNamePrefix="react-select"
      onChange={onChangeValue}
      isMulti={isMulti}
      isClearable={isClearable}
      isDisabled={isDisabled}
      noOptionsMessage={noOptionsMessage}
    />
  );
};

Select.defaultProps = {
  value: '',
  disabled: false,
  editable: true,
  multi: false,
  isAsync: false,
  isControlled: true,
  clearable: true,
  allowCreate:false,
  noResultsText: undefined,
  placeholder: undefined,
  addLabelText: undefined,
  options: [],
  inputProps: {},
  onChange: () => {},
  loadAsyncOptions: () => {},
};

Select.propTypes = {
  value: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
    PropTypes.bool,
    PropTypes.object,
  ]),
  allowCreate: PropTypes.bool,
  disabled: PropTypes.bool,
  editable: PropTypes.bool,
  multi: PropTypes.bool,
  isAsync: PropTypes.bool,
  isUncontrolled: PropTypes.bool,
  clearable: PropTypes.bool,
  noResultsText: PropTypes.string,
  options: PropTypes.array,
  addLabelText: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.func,
  ]),
  placeholder: PropTypes.string,
  onChange: PropTypes.func,
  loadAsyncOptions: PropTypes.func,
};

export default Select;
