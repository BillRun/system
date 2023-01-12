import { createSelector } from 'reselect';
import Immutable from 'immutable';

export const templateSelector = createSelector(
  (templates, type) => templates.get(type, Immutable.List()),
  templates => templates.map(template => template.get('label', 'Template')).toArray(),
);

export const suggestionsSelector = createSelector(
   suggestions => suggestions,
   suggestions => (typeof suggestions !== 'undefined' ? suggestions.toArray() : undefined),
);
