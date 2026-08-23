import React from 'react';
import PropTypes from 'prop-types';
import { Button, InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';

const MiniFilter = ({ filter = '', placeholder = 'Filter...', onChange, onClear }) => (
  <InputGroup className="mini-list-filter">
    <Field
      onChange={onChange}
      value={filter}
      placeholder={placeholder}
      autoComplete="off"
    />
    <InputGroup.Text className="filter-reset">
      <Button
        className="btn-xs"
        variant="outline-secondary"
        disabled={filter === ''}
        onClick={onClear}
        title="reset filter"
      >
        <i className="fa fa-eraser danger-red" />
      </Button>
    </InputGroup.Text>
  </InputGroup>
)

MiniFilter.propTypes = {
  filter: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  placeholder: PropTypes.string,
  onChange: PropTypes.func.isRequired,
  onClear: PropTypes.func,
};

export default MiniFilter;
