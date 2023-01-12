import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { fetchProductByIdQuery } from '../common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';
import { saveEntity } from './entityActions';
import {
  getProductConvertedRates,
} from '@/common/Util';
import {
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';


export const PRODUCT_GOT = 'PRODUCT_GOT';
export const SAVE_PRODUCT = 'SAVE_PRODUCT';
export const PRODUCT_CLEAR = 'PRODUCT_CLEAR';
export const PRODUCT_CLONE_RESET = 'PRODUCT_CLONE_RESET';
export const PRODUCT_ADD_RATE = 'PRODUCT_ADD_RATE';
export const PRODUCT_REMOVE_RATE = 'PRODUCT_REMOVE_RATE';
export const PRODUCT_DELETE_FIELD = 'PRODUCT_DELETE_FIELD';
export const PRODUCT_UPDATE_FIELD_VALUE = 'PRODUCT_UPDATE_FIELD_VALUE';
export const PRODUCT_UPDATE_USAGET_VALUE = 'PRODUCT_UPDATE_USAGET_VALUE';
export const PRODUCT_UPDATE_TO_VALUE = 'PRODUCT_UPDATE_TO_VALUE';


const gotItem = product => ({
  type: PRODUCT_GOT,
  product,
});

export const clearProduct = () => ({
  type: PRODUCT_CLEAR,
});

export const onFieldUpdate = (path, value) => ({
  type: PRODUCT_UPDATE_FIELD_VALUE,
  path,
  value,
});

export const onFieldRemove = path => ({
  type: PRODUCT_DELETE_FIELD,
  path,
});

export const onToUpdate = (path, index, value) => ({
  type: PRODUCT_UPDATE_TO_VALUE,
  path,
  index,
  value,
});

export const onUsagetUpdate = (path, oldUsaget, newUsaget) => ({
  type: PRODUCT_UPDATE_USAGET_VALUE,
  path,
  oldUsaget,
  newUsaget,
});

export const onRateAdd = path => ({
  type: PRODUCT_ADD_RATE,
  path,
});

export const onRateRemove = (path, index) => ({
  type: PRODUCT_REMOVE_RATE,
  path,
  index,
});

export const setCloneProduct = () => ({
  type: PRODUCT_CLONE_RESET,
  uniquefields: ['key'],
});

export const saveProduct = (product, action) => (dispatch, getState) => {
  const state = getState();
  const usageTypesData = usageTypesDataSelector(state);
  const propertyTypes = propertyTypeSelector(state);
  const rates = getProductConvertedRates(propertyTypes, usageTypesData, product, true);
  const convertedProduct = product.withMutations((itemWithMutations) => {
    if (!rates.isEmpty()) {
      itemWithMutations.set('rates', rates);
    }
  });
  return dispatch(saveEntity('rates', convertedProduct, action));
};

export const getProduct = id => (dispatch, getState) => {
  dispatch(startProgressIndicator());
  const query = fetchProductByIdQuery(id);
  return apiBillRun(query)
    .then((response) => {
      const item = response.data[0].data.details[0];
      item.originalValue = item.from;
      const state = getState();
      const usageTypesData = usageTypesDataSelector(state);
      const propertyTypes = propertyTypeSelector(state);
      const product = Immutable.fromJS(item);
      const rates = getProductConvertedRates(propertyTypes, usageTypesData, product, false);
      const convertedProduct = product.withMutations((itemWithMutations) => {
        if (!rates.isEmpty()) {
          itemWithMutations.set('rates', rates);
        }
      }).toJS();
      dispatch(gotItem(convertedProduct));
      return dispatch(apiBillRunSuccessHandler(response));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving product')));
};
