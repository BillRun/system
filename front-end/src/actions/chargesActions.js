import { fetchChargeByIdQuery } from '../common/ApiQueries';
import {
  saveEntity,
  getEntity,
  clearEntity,
  updateEntityField,
  deleteEntityField,
  setCloneEntity,
} from './entityActions';

export const setClone = () => setCloneEntity('charge', 'charge');

export const clear = () => clearEntity('charge');

export const save = (item, action) => saveEntity('charges', item, action);

export const update = (path, value) => updateEntityField('charge', path, value);

export const deleteValue = path => deleteEntityField('charge', path);

export const get = id => getEntity('charge', fetchChargeByIdQuery(id));
