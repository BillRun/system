import { createStore, applyMiddleware, compose } from 'redux';
import Immutable from 'immutable';
import { thunk as thunkMiddleware } from 'redux-thunk';
import rootReducer from '@/reducers';
import { getConfig } from '@/common/Util';

// ---------------------------------------------------------------------------
// localStorage persistence — replaces abandoned redux-localstorage package.
// Same behaviour: persists a slice of state, deserializes Immutable structures.
// ---------------------------------------------------------------------------

const getStorageKey = () => getConfig(['env', 'storageVersion'], 'app');

/** Extract the slice of state we want to persist */
const sliceState = (state) => ({
  entityList: {
    size: state.entityList.size,
    filter: state.entityList.filter,
    sort: state.entityList.sort,
    state: state.entityList.state,
  },
  guiState: {
    menu: state.guiState.menu,
  },
  settings: state.settings,
});

/** Save slice to localStorage after every action */
const persistMiddleware = store => next => action => {
  const result = next(action);
  try {
    const state = store.getState();
    const slice = sliceState(state);
    // Immutable structures need to go through toJS() to be JSON-serialisable
    const serializable = JSON.parse(JSON.stringify(slice, (_, v) =>
      v && typeof v.toJS === 'function' ? v.toJS() : v
    ));
    localStorage.setItem(getStorageKey(), JSON.stringify(serializable));
  } catch (e) {
    // Quota exceeded or private mode — silently ignore
  }
  return result;
};

/** Load previously saved state and rehydrate Immutable structures */
const loadPersistedState = () => {
  try {
    const raw = localStorage.getItem(getStorageKey());
    if (!raw) return undefined;
    const subReducersDataKeys = ['entityList', 'guiState'];
    const parsed = JSON.parse(raw);
    if (!parsed) return undefined;
    Object.keys(parsed).forEach((key) => {
      if (subReducersDataKeys.includes(key)) {
        Object.keys(parsed[key]).forEach((subKey) => {
          parsed[key][subKey] = Immutable.fromJS(parsed[key][subKey]);
        });
      } else {
        parsed[key] = Immutable.fromJS(parsed[key]);
      }
    });
    return parsed;
  } catch (e) {
    return undefined;
  }
};

// ---------------------------------------------------------------------------

const createEnhancer = () => {
  if (process.env.NODE_ENV === "development") {
    const DevTools = require('./components/DevTools').default;
    return compose(
      applyMiddleware(thunkMiddleware, persistMiddleware),
      DevTools.instrument()
    );
  }
  return applyMiddleware(thunkMiddleware, persistMiddleware);
};

export default function configureStore() {
  const persistedState = loadPersistedState();
  const enhancer = createEnhancer();
  return createStore(
    rootReducer,
    persistedState,
    enhancer
  );
}
