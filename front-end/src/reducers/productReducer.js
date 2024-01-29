import Immutable from 'immutable';
import {
  PRODUCT_DELETE_FIELD,
  PRODUCT_UPDATE_FIELD_VALUE,
  PRODUCT_UPDATE_USAGET_VALUE,
  PRODUCT_UPDATE_TO_VALUE,
  PRODUCT_ADD_RATE,
  PRODUCT_REMOVE_RATE,
  PRODUCT_GOT,
  PRODUCT_CLEAR,
  PRODUCT_CLONE_RESET,
} from '@/actions/productActions';
import { getConfig } from '@/common/Util';

const PRODUCT_UNLIMITED = getConfig('productUnlimitedValue', 'UNLIMITED');
const defaultState = Immutable.Map({
  key: '',
  description: '',
  pricing_method: 'tiered',
});
const DefaultRate = Immutable.Record({
  from: 0,
  to: PRODUCT_UNLIMITED,
  interval: '',
  price: '',
  uom_display: Immutable.Map({
    range: '',
    interval: '',
  }),
});


export default function (state = defaultState, action) {
  switch (action.type) {

    case PRODUCT_UPDATE_FIELD_VALUE:
      return state.setIn(action.path, action.value);
    case PRODUCT_DELETE_FIELD:
      const { path } = action;
      if (Array.isArray(path)) {
        return state.deleteIn(path);
      }
      return state.delete(path);

    case PRODUCT_UPDATE_TO_VALUE: {
      return state.updateIn(action.path, Immutable.List(), (list) => {
        if (action.index < list.size - 1) {
          const nextItemIndex = action.index + 1;
          return list
            .update(nextItemIndex, Immutable.Map(), nextItem => nextItem.set('from', action.value))
            .update(action.index, Immutable.Map(), item => item.set('to', action.value));
        }
        return list.update(action.index, Immutable.Map(), item => item.set('to', action.value));
      });
    }

    case PRODUCT_UPDATE_USAGET_VALUE: {
      const oldPath = [...action.path, action.oldUsaget];
      const newPath = [...action.path, action.newUsaget];
      return state.setIn(newPath, state.getIn(oldPath, Immutable.List())).deleteIn(oldPath);
    }

    case PRODUCT_ADD_RATE: {
      // if product prices array is empty - add new default item
      if (state.getIn(action.path, Immutable.List()).size === 0) {
        return state.updateIn(action.path, Immutable.List(), list => list.push(new DefaultRate()));
      }
      return state.updateIn(action.path, Immutable.List(), (list) => {
        // use last item for new price row
        const newItem = list.last().set('to', PRODUCT_UNLIMITED);
        const lastItemIndex = list.size - 1;
        return list
          .update(lastItemIndex, Immutable.Map(), prevItem => prevItem.set('to', ''))
          .push(newItem);
      });
    }

    case PRODUCT_REMOVE_RATE:
      return state.updateIn(action.path, (list) => {
        if (action.index > 0) {
          const prevItemIndex = action.index - 1;
          return list
            .update(prevItemIndex, item => item.set('to', PRODUCT_UNLIMITED))
            .delete(action.index);
        }
        return list.delete(action.index);
      });

    case PRODUCT_CLONE_RESET: {
      const keysToDeleteOnClone = ['_id', 'from', 'to', 'originalValue', ...action.uniquefields];
      return state.withMutations((itemWithMutations) => {
        keysToDeleteOnClone.forEach((keyToDelete) => {
          itemWithMutations.delete(keyToDelete);
        });
      });
    }

    case PRODUCT_GOT:
      return Immutable.fromJS(action.product);

    case PRODUCT_CLEAR:
      return defaultState;

    default:
      return state;
  }
}
