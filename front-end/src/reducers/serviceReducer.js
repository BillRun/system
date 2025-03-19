import Immutable from 'immutable';
import { getConfig } from '@/common/Util';
import includeGroupsReducer from './includeGroupsReducer';
import { ADD_GROUP, REMOVE_GROUP } from '@/actions/includeGroupsActions';
import {
  SERVICE_PRODUCTS_REMOVE,
  SERVICE_PRODUCTS_RATE_UPDATE_TO,
  SERVICE_PRODUCTS_RATE_UPDATE,
  SERVICE_PRODUCTS_RATE_REMOVE,
  SERVICE_PRODUCTS_RATE_ADD,
  SERVICE_PRODUCTS_RATE_INIT,
  GOT_SERVICE,
  CLONE_RESET_SERVICE,
  CLEAR_SERVICE,
  UPDATE_SERVICE,
  ADD_GROUP_SERVICE,
  REMOVE_GROUP_SERVICE,
  DELETE_SERVICE_FIELD,
  SERVICE_ADD_TARIFF,
  SERVICE_UPDATE_SERVICE_CYCLE, 
  SERVICE_REMOVE_TARIFF
} from '@/actions/serviceActions';
import entityProductsReducer from './entityProductsReducer';

const SERVICE_CYCLE_UNLIMITED = getConfig('serviceCycleUnlimitedValue', 'UNLIMITED');

const DefaultState = Immutable.fromJS({
  description: '',
  name: '',
  price: [{
    from: 0,
    to: SERVICE_CYCLE_UNLIMITED,
    price: '',
  }],
});

const defaultTariff = Immutable.Map({
  price: '',
  from: 0,
  to: SERVICE_CYCLE_UNLIMITED,
});

const serviceReducer = (state = DefaultState, action) => {
  switch (action.type) {
    case CLEAR_SERVICE:
      return DefaultState;

    case GOT_SERVICE:
      return Immutable.fromJS(action.item);

    case UPDATE_SERVICE:
      return state.setIn(action.path, action.value);

    case CLONE_RESET_SERVICE: {
      const keysToDeleteOnClone = ['_id', 'from', 'to', 'originalValue', ...action.uniquefields];
      return state.withMutations((itemWithMutations) => {
        keysToDeleteOnClone.forEach((keyToDelete) => {
          itemWithMutations.delete(keyToDelete);
        });
      });
    }

    case ADD_GROUP_SERVICE: {
      const includeGroupsAction = Object.assign({}, action, { type: ADD_GROUP });
      return includeGroupsReducer(state, includeGroupsAction);
    }

    case REMOVE_GROUP_SERVICE: {
      const includeGroupsAction = Object.assign({}, action, { type: REMOVE_GROUP });
      return includeGroupsReducer(state, includeGroupsAction);
    }

    case SERVICE_PRODUCTS_REMOVE:
    case SERVICE_PRODUCTS_RATE_UPDATE_TO:
    case SERVICE_PRODUCTS_RATE_UPDATE:
    case SERVICE_PRODUCTS_RATE_REMOVE:
    case SERVICE_PRODUCTS_RATE_ADD:
    case SERVICE_PRODUCTS_RATE_INIT:
      return entityProductsReducer(state, action);

    case DELETE_SERVICE_FIELD: {
      const { path } = action;
      const arrayPath = Array.isArray(path) ? path : [path];
      return state.deleteIn(arrayPath);
    }

    case SERVICE_ADD_TARIFF: {
      if (state.get('price', Immutable.List()).isEmpty()) {
        return state.update('price', Immutable.List(), list => list.push(defaultTariff));
      }
      const limit_cycles = state.get('limit_cycles', false);
      if(limit_cycles){ 
        return state.update('price', Immutable.List(), list =>
        list
          .update(list.size - 1, Immutable.Map(), item => item.set('to', ''))
          .push(defaultTariff.set('to', '').set('from', ''))
        );
      }
      return state.update('price', Immutable.List(), list =>
        list
          .update(list.size - 1, Immutable.Map(), item => item.set('to', ''))
          .push(defaultTariff.set('from', ''))
      );
    }

    case SERVICE_REMOVE_TARIFF: {
      if (action.index === 0) { // removed first item
        return state.update('price', Immutable.List(), (list) => {
          if (list.size > 1) { // there is other items in list, update next item from to 0
            return list
              .update(action.index + 1, Immutable.Map(), item => item.set('from', 0))
              .delete(action.index);
          }
          return list.delete(action.index); // only on item, delete it
        });
      }
      //item removed from end and there is limit of cycles
      const limit_cycles = state.get('limit_cycles', false);
      if(limit_cycles){ 
        return state.update('price', Immutable.List(), list =>
        list
          .update(action.index - 1, item => item.set('to', ''))
          .delete(action.index)
        );
      }
      // item removed from end and there is other items (index > 0)
      return state.update('price', Immutable.List(), list =>
        list
          .update(action.index - 1, item => item.set('to', SERVICE_CYCLE_UNLIMITED))
          .delete(action.index)
      );
    }

    case SERVICE_UPDATE_SERVICE_CYCLE:
      return state.updateIn(['price'], list => reCalculateCycles(list, action.index, action.value));


    default:
      return state;
  }
};

const reCalculateCycles = (prices, index, value) => prices.reduce((newList, price, i) => {
  if (i === index) {
    // set new To
    if (typeof value === 'undefined') { // first item was removed
      price = price.set('to', parseInt(price.get('to', 0) || 0) - parseInt(price.get('from', 0) || 0));
    } else if (value === SERVICE_CYCLE_UNLIMITED) { // last value set to unlimited
      price = price.set('to', value);
    } else { // simple case, update to new value
      price = price.set('to', parseInt(price.get('from') || 0) + parseInt(value));
    }
    // set new From
    if (index === 0) {
       price = price.set('from', 0);
    }
    return newList.push(price);
  } else if (i > index) {
    const from = price.get('from', 0);
    const to = price.get('to', '');
    // set new From
    const prevTo = parseInt(newList.last().get('to', 0) || 0);
    price = price.set('from', prevTo);
    // set new To
    if (to === '') { // TO not set
      price = price.set('to', price.get('from'));
    } else if (to === SERVICE_CYCLE_UNLIMITED) { // TO is unlimited
      // do nothing
    } else { // normal case, update with shifting
      const diff = parseInt(to || 0) - parseInt(from || 0);
      price = price.set('to', prevTo + diff);
    }
    return newList.push(price);
  }
  return newList.push(price);
}, Immutable.List());

export default serviceReducer;
