import { fetchtaxeByIdQuery } from '../common/ApiQueries';
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


export const getTax = id => getEntity('tax', fetchtaxeByIdQuery(id));
export const saveTax = (item, action) => saveEntity('taxes', item, action)
export const updateTax = (path, value) => updateEntityField('tax', path, value);
export const setCloneTax = () => setCloneEntity('tax', 'tax');
export const deleteTaxValue = path => deleteEntityField('tax', path);
export const clearTax = () => clearEntity('tax');
export const clearTaxList = () => clearItems('taxes');
export const getTaxRevisions = key => getRevisions('taxes', 'key', key);
export const clearTaxRevisions = key => clearRevisions('taxes', key);
