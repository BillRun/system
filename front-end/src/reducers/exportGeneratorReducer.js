import Immutable from 'immutable';

import {
  GOT_EXPORT_GENERATOR,
  CLEAR_EXPORT_GENERATOR,
  UPDATE_EXPORT_GENERATOR_VALUE,
  REMOVE_EXPORT_GENERATOR_VALUE,
} from '@/actions/exportGeneratorActions';

const defaultState = Immutable.Map({
  filtration: Immutable.List([
    Immutable.Map({
      collection: 'lines',
    })
  ])
});

export default function (state = defaultState, action) {
  switch (action.type) {
    case GOT_EXPORT_GENERATOR:
      return Immutable.fromJS(action.generator);

    case UPDATE_EXPORT_GENERATOR_VALUE: {
      const { path, value } = action;
      if (Array.isArray(path)) {
        return state.setIn(path, value);
      }
      return state.setIn([path], value);
    }

    case REMOVE_EXPORT_GENERATOR_VALUE: {
      const { path } = action;
      if (Array.isArray(path)) {
        return state.deleteIn(path);
      }
      return state.deleteIn([path]);
    }

    case CLEAR_EXPORT_GENERATOR:
      return defaultState;

    default:
      return state;
  }
}
