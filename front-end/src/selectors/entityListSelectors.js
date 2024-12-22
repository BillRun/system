import { createSelector } from 'reselect';


const getItems = (state, props, entityName) => state.entityList.items.get(entityName);
const getPage = (state, props, entityName) => state.entityList.page.get(entityName);
const getNextPage = (state, props, entityName) => state.entityList.nextPage.get(entityName);
const getSize = (state, props, entityName) => state.entityList.size.get(entityName);

export const itemsSelector = createSelector(
  getItems,
  items => items,
);

export const pageSelector = createSelector(
  getPage,
  page => page,
);

export const nextPageSelector = createSelector(
  getNextPage,
  nextPage => nextPage,
);

export const sizeSelector = createSelector(
  getSize,
  size => size,
);
