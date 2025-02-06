import { createStore, applyMiddleware, compose } from 'redux';
import Immutable from 'immutable';
import thunkMiddleware from 'redux-thunk';
import persistState from 'redux-localstorage';
import rootReducer from '@/reducers';
import { getConfig } from '@/common/Util';


const createLocalstorageConfig = () => ({
  key: getConfig(['env', 'storageVersion'], 'app'),
  slicer: () => state => ({
    entityList: {
      size: state.entityList.size,
      filter: state.entityList.filter,
      sort: state.entityList.sort,
      state: state.entityList.state,
    },
    guiState: {
      menu: state.guiState.menu,
    },
    settings : state.settings,
  }),
  deserialize: (serializedData) => {
    const subReducersDataKeys = ['entityList', 'guiState'];
    const parseData = JSON.parse(serializedData);
    if (parseData) {
      Object.keys(parseData).forEach((key) => {
        if (subReducersDataKeys.includes(key)) {
          Object.keys(parseData[key]).forEach((entityListKey) => {
            parseData[key][entityListKey] = Immutable.fromJS(parseData[key][entityListKey]);
          });
        } else {
          parseData[key] = Immutable.fromJS(parseData[key]);
        }
      });
    }
    return parseData;
  },
})

const createEnhancer = () => {
  const localstorageConfig = createLocalstorageConfig();
  if (process.env.NODE_ENV === "development") {
    const DevTools = require('./components/DevTools').default;
    return compose(
      persistState(null, localstorageConfig),
      // Middleware you want to use in development:
      applyMiddleware(thunkMiddleware),
      // Required! Enable Redux DevTools with the monitors you chose
      DevTools.instrument()
    );
  }
  return compose(
    persistState(null, localstorageConfig),
    applyMiddleware(thunkMiddleware),
  );
}

export default function configureStore(initialState = {}) {
  const enhancer = createEnhancer();
  return createStore(
    rootReducer,
    initialState,
    enhancer
  );
}
