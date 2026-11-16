import { fetchProductsGroupByIdQuery } from '../common/ApiQueries';
import {
  saveEntity,
  getEntity,
  clearEntity,
  updateEntityField,
  deleteEntityField,
  setCloneEntity,
} from './entityActions';
import {
  clearItems,
  getRevisions,
  clearRevisions,
} from '@/actions/entityListActions';


export const getProductsGroup = id => getEntity('products_group', fetchProductsGroupByIdQuery(id));
export const saveProductsGroup = (item, action) => saveEntity('productgroups', item, action);
export const updateProductsGroup = (path, value) => updateEntityField('products_group', path, value);
export const setCloneProductsGroup = () => setCloneEntity('products_group', 'products_group');
export const deleteProductsGroupValue = path => deleteEntityField('products_group', path);
export const clearProductsGroup = () => clearEntity('products_group');
export const clearProductsGroupList = () => clearItems('products_groups');
export const getProductsGroupRevisions = key => getRevisions('productgroups', 'key', key);
export const clearProductsGroupRevisions = key => clearRevisions('productgroups', key);
