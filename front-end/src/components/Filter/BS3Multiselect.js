import React, { useId } from 'react';
import PropTypes from 'prop-types';
import { Dropdown } from 'react-bootstrap';

/**
 * React replacement for the jQuery react-bootstrap-multiselect plugin.
 * Preserves the original BS3 DOM structure so yeti.css styles apply as-is.
 *
 * Props: data [{ value, label, selected, disabled }], onChange, nonSelectedText,
 *        buttonWidth, disabled, renderToggle({ selectedOptions, defaultLabel })
 */
const BS3Multiselect = ({
  data = [],
  onChange,
  nonSelectedText = '',
  buttonWidth = '100%',
  disabled = false,
  renderToggle = null,
}) => {
  const domId = useId();
  const toggleId = `bs3-multiselect-${domId.replace(/:/g, '')}`;

  const selectedOptions = data.filter((o) => o.selected);
  const selectedValues = selectedOptions.map((o) => o.value);

  const toggleValue = (value) => {
    const next = selectedValues.includes(value)
      ? selectedValues.filter((v) => v !== value)
      : [...selectedValues, value];
    onChange(next.length ? next.join(',') : '');
  };

  const defaultLabel =
    selectedOptions.length === 0
      ? nonSelectedText
      : selectedOptions
          .map((o) => (typeof o.label === 'string' ? o.label : ''))
          .filter(Boolean)
          .join(', ') || nonSelectedText;

  const toggleContent =
    typeof renderToggle === 'function'
      ? renderToggle({ selectedOptions, defaultLabel })
      : defaultLabel;

  return (
    <Dropdown autoClose="outside" align="start">
      <Dropdown.Toggle
        id={toggleId}
        as="button"
        type="button"
        disabled={disabled}
        className="btn btn-default dropdown-toggle"
        style={{
          width: buttonWidth,
          maxWidth: '100%',
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
          textAlign: 'center',
        }}
      >
        {toggleContent}
      </Dropdown.Toggle>
      {/* ul > li > a > label matches original jQuery plugin DOM; yeti.css styles it automatically */}
      <Dropdown.Menu
        as="ul"
        className="multiselect-container bs3-multiselect-menu"
        style={{ maxHeight: 280, overflowY: 'auto' }}
        popperConfig={{ modifiers: [{ name: 'offset', options: { offset: [0, 0] } }] }}
      >
        {data.map((opt) => (
          <li
            key={String(opt.value)}
            className={opt.selected ? 'active' : ''}
          >
            {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */} {/* toggle handled by <a> onClick */}
            <a
              tabIndex={0}
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!opt.disabled && !disabled) toggleValue(opt.value);
              }}
            >
              <label
                className="checkbox"
                onClick={(e) => e.stopPropagation()}
                style={{
                  cursor: opt.disabled ? 'not-allowed' : 'pointer',
                  opacity: opt.disabled ? 0.6 : 1,
                }}
              >
                <input
                  type="checkbox"
                  checked={!!opt.selected}
                  disabled={disabled || !!opt.disabled}
                  onChange={() => !opt.disabled && !disabled && toggleValue(opt.value)}
                />
                {opt.label}
              </label>
            </a>
          </li>
        ))}
      </Dropdown.Menu>
    </Dropdown>
  );
};

BS3Multiselect.propTypes = {
  data: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
      label: PropTypes.node.isRequired,
      selected: PropTypes.bool,
      disabled: PropTypes.bool,
    })
  ),
  onChange: PropTypes.func.isRequired,
  nonSelectedText: PropTypes.string,
  buttonWidth: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  disabled: PropTypes.bool,
  renderToggle: PropTypes.func,
};

export default BS3Multiselect;
