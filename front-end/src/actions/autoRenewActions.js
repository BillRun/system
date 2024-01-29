import {
  getEntity,
  saveEntity,
  deleteEntity,
  updateEntityField,
  clearEntity,
} from './entityActions';
import { fetchAutoRenewByIdQuery } from '../common/ApiQueries';

export const saveAutoRenew = (item, mode) => saveEntity('autorenew', item, mode);

export const deleteAutoRenew = item => deleteEntity('autorenew', item);

export const updateAutoRenew = (path, value) => updateEntityField('autorenew', path, value);

export const getAutoRenew = id => getEntity('autorenew', fetchAutoRenewByIdQuery(id));

export const clearAutoRenew = () => clearEntity('autorenew');
