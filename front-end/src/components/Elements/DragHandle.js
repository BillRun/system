import React from 'react';
import PropTypes from 'prop-types';
import { SortableHandle } from 'react-sortable-hoc';

const DragHandle = ({ element, disabled }) => (disabled
  ? <i className="fa fa-bars fa-fw" style={{ opacity: '.05', lineHeight: '35px' }} />
  : element
);

DragHandle.defaultProps = {
  element: <i className="fa fa-bars fa-fw" style={{ cursor: 'row-resize', opacity: '.25', lineHeight: '35px' }} />,
  disabled: false,
};

DragHandle.propTypes = {
  element: PropTypes.element.isRequired,
  disabled: PropTypes.bool,
};

export default SortableHandle(DragHandle);
