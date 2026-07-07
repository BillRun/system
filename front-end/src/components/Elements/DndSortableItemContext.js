import React from 'react';

const DndSortableItemContext = React.createContext({
  attributes: {},
  listeners: undefined,
  setActivatorNodeRef: () => {},
  disabled: false,
});

export default DndSortableItemContext;
