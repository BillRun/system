import { createSelector } from 'reselect';

const getApp = state => state.guiState.app;
export const isWorkersSelector = createSelector(
  getApp,
  app => app.get('isWorkers', undefined),
);
