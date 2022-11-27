import Immutable from 'immutable';
import { ADD_GROUP, REMOVE_GROUP } from '@/actions/includeGroupsActions';

const DefaultState = Immutable.Map();

const includeGroupsReducer = (state = DefaultState, action) => {
  switch (action.type) {

    case ADD_GROUP: {
      const group = Immutable.Map({}).withMutations((groupWithMutations) => {
        groupWithMutations.set('account_shared', action.shared);
        groupWithMutations.set('account_pool', action.pooled);
        groupWithMutations.set('quantity_affected', action.quantityAffected);
        groupWithMutations.set('rates', Immutable.List(action.products));
        if (action.usages.get(0, '') === 'cost') {
          groupWithMutations.set('cost', action.value);
        } else {
          const usageTypes = Immutable.Map().withMutations((usageTypesWithMutations) => {
            action.usages.forEach((usage) => {
              usageTypesWithMutations.set(usage, Immutable.Map({ unit: action.unit }));
            });
          });
          groupWithMutations.set('value', action.value);
          groupWithMutations.set('usage_types', usageTypes);
        }
      });

      return state
        // if groups is empty, server return it as empty array instead of empty object
        // in this case ImmutableJS will fail to set new group at key XYZ on list
        .updateIn(['include', 'groups'], Immutable.Map(), groups => (groups.isEmpty() ? Immutable.Map() : groups))
        .setIn(['include', 'groups', action.groupName], group);
    }

    case REMOVE_GROUP:
      return state.deleteIn(['include', 'groups', action.groupName]);

    default:
      return state;
  }
};

export default includeGroupsReducer;
