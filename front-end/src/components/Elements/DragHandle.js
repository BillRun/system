import React from 'react';
import PropTypes from 'prop-types';
import DndSortableItemContext from './DndSortableItemContext';

const DragHandle = ({ element = <i className="fa fa-bars fa-fw" style={{ cursor: 'row-resize', opacity: '.25', lineHeight: '35px' }} />, disabled = false }) => {
  const {
    attributes,
    listeners,
    setActivatorNodeRef,
    disabled: dndDisabled,
  } = React.useContext(DndSortableItemContext);
  const isDisabled = disabled || dndDisabled;

  if (isDisabled) {
    return <i className="fa fa-bars fa-fw" style={{ opacity: '.05', lineHeight: '35px' }} />;
  }

  return (
    <span
      ref={setActivatorNodeRef}
      {...attributes}
      {...listeners}
      style={{ display: 'inline-block', touchAction: 'none' }}
    >
      {element}
    </span>
  );
};

DragHandle.propTypes = {
  element: PropTypes.element.isRequired,
  disabled: PropTypes.bool,
};

export default DragHandle;
