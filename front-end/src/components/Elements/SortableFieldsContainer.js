import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import DndSortableItemContext from './DndSortableItemContext';

const SortableItem = ({ id, child }) => {
  const disabled = Boolean(child.props.disabled);
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id, disabled });
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 2 : 'auto',
  };

  return (
    <div ref={setNodeRef} style={style}>
      <DndSortableItemContext.Provider
        value={{
          attributes,
          listeners,
          setActivatorNodeRef,
          disabled,
        }}
      >
        {child}
      </DndSortableItemContext.Provider>
    </div>
  );
};

SortableItem.propTypes = {
  id: PropTypes.string.isRequired,
  child: PropTypes.node.isRequired,
};

const SortableFieldsContainer = ({ items = [], onSortEnd, collection }) => {
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );
  const normalizedItems = Array.isArray(items) ? items.filter(Boolean) : items.filter(Boolean).toArray();
  const sortableItems = normalizedItems.map((item, idx) => ({
    id: String(item.key || `sortable-item-${idx}`),
    child: item,
  }));

  const handleDragEnd = ({ active, over }) => {
    if (!over || active.id === over.id) {
      return;
    }
    const oldIndex = sortableItems.findIndex(item => item.id === active.id);
    const newIndex = sortableItems.findIndex(item => item.id === over.id);
    if (oldIndex < 0 || newIndex < 0) {
      return;
    }
    onSortEnd({ oldIndex, newIndex, collection });
  };

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
      <SortableContext items={sortableItems.map(item => item.id)} strategy={verticalListSortingStrategy}>
        <div>
          {sortableItems.map(item => (
            <SortableItem key={item.id} id={item.id} child={item.child} />
          ))}
        </div>
      </SortableContext>
    </DndContext>
  );
};

SortableFieldsContainer.propTypes = {
  items: PropTypes.oneOfType([
    PropTypes.instanceOf(Immutable.List),
    PropTypes.array,
  ]),
  onSortEnd: PropTypes.func,
  collection: PropTypes.string,
};

SortableFieldsContainer.defaultProps = {
  onSortEnd: () => {},
  collection: '',
};

export default SortableFieldsContainer;
