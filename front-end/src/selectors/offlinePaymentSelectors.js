import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { billsUserFieldsSelector } from './settingsSelector';
import { sentenceCase } from 'change-case';
import {
  getFieldName,
} from '@/common/Util';

const selectOfflinePaymentFields = (
  userFields = Immutable.List(),
) =>
  Immutable.List().withMutations((optionsWithMutations) => {
    userFields.forEach((userField) => {
      optionsWithMutations.push(Immutable.Map({
        field_name: `${userField}`,
        title: `${getFieldName(userField, 'bills', sentenceCase(userField))} (User field)`,
      }));
    });
  })

  export const offlinePaymentFieldsSelector = createSelector(
    billsUserFieldsSelector,
    selectOfflinePaymentFields,
  );