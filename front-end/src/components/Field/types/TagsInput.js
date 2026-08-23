/**
 * In-house TagsInput component — replaces abandoned react-tagsinput@3.19.0
 * which used legacy componentWillReceiveProps (incompatible with React 19 strict mode).
 *
 * External API mirrors react-tagsinput so Tags.js needs no changes:
 *   value         - array of tag strings (caller normalises string → array)
 *   onChange      - called with the new full array whenever tags change
 *   disabled      - disables input and hides remove buttons
 *   inputProps    - forwarded to the inner <input> (placeholder, className, etc.)
 *   onlyUnique    - prevent duplicate tags
 *   renderTag     - custom tag renderer: ({ tag, key, disabled, onRemove,
 *                   classNameRemove, getTagDisplayValue, ...spanProps }) => ReactNode
 *   renderInput   - custom input renderer: ({ addTag, ...inputProps }) => ReactNode
 *   addOnBlur     - add pending text as a tag when the input loses focus
 *   className     - extra class on the wrapper div
 *
 * CSS class names are intentionally the same as react-tagsinput's so the
 * existing overrides/react-tagsinput.scss applies without changes.
 */
import React, { useState, useRef, useCallback } from 'react';

const TagsInput = ({
  value = [],
  onChange,
  disabled = false,
  inputProps = {},
  onlyUnique = false,
  renderTag,
  renderInput,
  addOnBlur = false,
  className = '',
}) => {
  const [inputValue, setInputValue] = useState('');
  const [focused, setFocused] = useState(false);
  const inputRef = useRef(null);

  // ---------- tag list mutations ----------
  const addTag = useCallback((tag) => {
    const trimmed = String(tag == null ? '' : tag).trim();
    if (!trimmed) return;
    if (onlyUnique && value.indexOf(trimmed) !== -1) return;
    onChange([...value, trimmed]);
    setInputValue('');
  }, [value, onChange, onlyUnique]);

  const removeTag = useCallback((index) => {
    const next = value.slice();
    next.splice(index, 1);
    onChange(next);
  }, [value, onChange]);

  // ---------- input change ----------
  // Accepts both plain-string values (Field components) and DOM events.
  const handleInputChange = useCallback((e) => {
    const val =
      typeof e === 'string' ? e
      : e && e.target ? e.target.value
      : '';
    setInputValue(val);
  }, []);

  // ---------- keyboard handling ----------
  const handleKeyDown = useCallback((e) => {
    const { key } = e;
    if (key === 'Enter' || key === ',') {
      e.preventDefault();
      addTag(inputValue);
    } else if (key === 'Backspace' && !inputValue && value.length > 0) {
      removeTag(value.length - 1);
    }
    if (inputProps.onKeyDown) inputProps.onKeyDown(e);
  }, [inputValue, value, addTag, removeTag, inputProps]);

  const handleFocus = useCallback((e) => {
    setFocused(true);
    if (inputProps.onFocus) inputProps.onFocus(e);
  }, [inputProps]);

  const handleBlur = useCallback((e) => {
    setFocused(false);
    if (addOnBlur && inputValue.trim()) addTag(inputValue);
    if (inputProps.onBlur) inputProps.onBlur(e);
  }, [addOnBlur, inputValue, addTag, inputProps]);

  // ---------- default tag renderer ----------
  const getTagDisplayValue = (tag) => tag;

  const defaultRenderTag = ({
    tag, key, disabled: isDisabled,
    onRemove, classNameRemove, getTagDisplayValue: getDisplay,
    ...tagProps
  }) => (
    <span key={key} className="react-tagsinput-tag" {...tagProps}>
      {!isDisabled && (
        <span className={classNameRemove} onClick={() => onRemove(key)}>×</span>
      )}
      {getDisplay(tag)}
    </span>
  );

  const tagRenderer = renderTag || defaultRenderTag;

  // ---------- build input props (strip handled callbacks to avoid double-firing) ----------
  const {
    placeholder,
    onKeyDown: _kd,  // already wired above
    onFocus:   _f,   // already wired above
    onBlur:    _b,   // already wired above
    ...restInputProps
  } = inputProps;

  const sharedInputProps = {
    value: inputValue,
    onChange: handleInputChange,
    onKeyDown: handleKeyDown,
    onFocus: handleFocus,
    onBlur: handleBlur,
    disabled,
    placeholder: disabled ? '' : (placeholder || ''),
    className: ['react-tagsinput-input', restInputProps.className].filter(Boolean).join(' '),
    ...restInputProps,
  };

  // renderInput receives addTag so it can trigger addition imperatively
  const inputEl = renderInput
    ? renderInput({ addTag, ...sharedInputProps })
    : (
      <input
        ref={inputRef}
        type="text"
        {...sharedInputProps}
      />
    );

  // ---------- wrapper ----------
  const wrapperClass = [
    'react-tagsinput',
    focused ? 'react-tagsinput--focused' : '',
    disabled ? 'react-tagsinput--disabled' : '',
    className,
  ].filter(Boolean).join(' ');

  return (
    <div
      className={wrapperClass}
      onClick={() => !disabled && inputRef.current && inputRef.current.focus()}
    >
      {value.map((tag, index) =>
        tagRenderer({
          tag,
          key: index,
          disabled,
          onRemove: removeTag,
          classNameRemove: 'react-tagsinput-remove',
          getTagDisplayValue,
        })
      )}
      {!disabled && inputEl}
    </div>
  );
};

export default TagsInput;
