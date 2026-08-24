import { createSelector } from 'reselect';
import Immutable from 'immutable';


const getCharge = (state, props) => state.entity.get('charging_process');
export const chargeSelector = createSelector(
  getCharge,
  item => (item === null ? undefined : item),
);

const getCharges = state => state.list.get('charges', null);
export const chargingListSelector = createSelector(
  getCharges,
  chargeSelector,
  (items, activeItem) => items === null ? undefined : items
    .sort((itemA, itemB) => itemA.get('created', '') < itemB.get('created', '') ? 1 : -1)
    .map(item => Immutable.Map.isMap(activeItem) && activeItem.get('md5', '') === item.get('md5', '')
      ? item.set('active', true) : item
    )
);

const getChargesSchedule = state => state.list.get('charges_schedule', null);
export const chargingScheduleListSelector = createSelector(
  getChargesSchedule,
  items => (items === null) ? undefined : items
);

export const chargingScheduleCountSelector = createSelector(
  chargingScheduleListSelector,
  items => Immutable.List.isList(items) ? items.size: 0
);