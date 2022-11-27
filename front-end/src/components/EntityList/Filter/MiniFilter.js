import React from 'react';
import PropTypes from 'prop-types';
import { Button, InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';


const MiniFilter = ({ filter, placeholder, onChange, onClear }) => (
  <InputGroup className="mini-list-filter">
    <Field
      onChange={onChange}
      value={filter}
      placeholder={placeholder}
      autoComplete="off"
    />
    <InputGroup.Addon className="filter-reset">
      <Button
        bsSize="xsmall"
        bsStyle="link"
        disabled={filter === ''}
        onClick={onClear}
        title="reset filter"
      >
        <i className="fa fa-eraser danger-red" />
      </Button>
    </InputGroup.Addon>
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

MiniFilter.defaultProps = {
  filter: '',
  placeholder: 'Filter...',
};

export default MiniFilter;
