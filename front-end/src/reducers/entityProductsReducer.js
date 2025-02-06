import Immutable from 'immutable';
import productReduser from './productReducer';

import {
  PLAN_PRODUCTS_REMOVE,
  PLAN_PRODUCTS_RATE_UPDATE_TO,
  PLAN_PRODUCTS_RATE_UPDATE,
  PLAN_PRODUCTS_RATE_REMOVE,
  PLAN_PRODUCTS_RATE_ADD,
  PLAN_PRODUCTS_RATE_INIT,
} from '@/actions/planActions';

import {
  SERVICE_PRODUCTS_REMOVE,
  SERVICE_PRODUCTS_RATE_UPDATE_TO,
  SERVICE_PRODUCTS_RATE_UPDATE,
  SERVICE_PRODUCTS_RATE_REMOVE,
  SERVICE_PRODUCTS_RATE_ADD,
  SERVICE_PRODUCTS_RATE_INIT,
} from '@/actions/serviceActions';

import {
  PRODUCT_UPDATE_FIELD_VALUE,
  PRODUCT_UPDATE_TO_VALUE,
  PRODUCT_ADD_RATE,
  PRODUCT_REMOVE_RATE,
} from '@/actions/productActions';


const defaultState = Immutable.Map();


export default function (state = defaultState, action) {
  switch (action.type) {

    case SERVICE_PRODUCTS_REMOVE:
    case PLAN_PRODUCTS_REMOVE:
      return state.deleteIn([...action.path, action.name]);

    case SERVICE_PRODUCTS_RATE_UPDATE_TO:
    case PLAN_PRODUCTS_RATE_UPDATE_TO: {
      const productAction = Object.assign(action, { type: PRODUCT_UPDATE_TO_VALUE });
      return productReduser(state, productAction);
    }

    case SERVICE_PRODUCTS_RATE_UPDATE:
    case PLAN_PRODUCTS_RATE_UPDATE: {
      const productAction = Object.assign(action, { type: PRODUCT_UPDATE_FIELD_VALUE });
      return productReduser(state, productAction);
    }

    case SERVICE_PRODUCTS_RATE_REMOVE:
    case PLAN_PRODUCTS_RATE_REMOVE: {
      const productAction = Object.assign(action, { type: PRODUCT_REMOVE_RATE });
      return productReduser(state, productAction);
    }

    case SERVICE_PRODUCTS_RATE_ADD:
    case PLAN_PRODUCTS_RATE_ADD: {
      const productAction = Object.assign(action, { type: PRODUCT_ADD_RATE });
      return productReduser(state, productAction);
    }

    case SERVICE_PRODUCTS_RATE_INIT:
    case PLAN_PRODUCTS_RATE_INIT: {
      const usaget = action.product.get('rates', Immutable.Map()).keySeq().first();
      return state.setIn(action.path, action.product.getIn(['rates', usaget, 'BASE', 'rate']));
    }

    default:
      return state;
  }
}
