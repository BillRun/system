import { createSelector } from 'reselect';
import Immutable from 'immutable';
import {
  subscriberFieldsSelector,
} from './settingsSelector';

const getCheckedFields = state => state.inputProcessor.get('fields', Immutable.List());

const getAllFields = state => state.inputProcessor.get('unfiltered_fields', Immutable.List());

const selectAllChecked = (checkedFields, allFields) => checkedFields.size === allFields.size;

export const allCheckedSelector = createSelector(
  getCheckedFields,
  getAllFields,
  selectAllChecked,
);

export const customerIdentificationFieldsPlaySelector = createSelector(
  (state, props) => props.settings.getIn(['customer_identification_fields', props.usaget], Immutable.List()),
  subscriberFieldsSelector,
  (customerIdentificationFields, subscriberFields) => {
    const plyas = customerIdentificationFields.map(customerIdentificationField =>
      subscriberFields.find(subscriberField =>
        subscriberField.get('field_name', '') === customerIdentificationField.get('target_key', ''),
        null, Immutable.Map(),
      ).get('plays', Immutable.List()),
    ).reduce((acc, list) => acc.union(list), Immutable.Set());
    return plyas;
  },
);
