import Immutable from 'immutable';

import { getFieldsWithPreFunctions } from '@/selectors/settingsSelector'
import { SET_NAME,
         SET_PARSER_SETTING,
         SET_PROCESSOR_TYPE,
         SET_DELIMITER_TYPE,
         UPDATE_INPUT_PROCESSOR_FIELD,
         GOT_PROCESSOR_SETTINGS,
         SET_FIELDS,
         SET_DELIMITER,
         SET_FIELD_MAPPING,
         REMOVE_CSV_FIELD,
         REMOVE_ALL_CSV_FIELDS,
         CHECK_ALL_FIELDS,
         ADD_CSV_FIELD,
         MAP_USAGET,
         SET_CUSETOMER_MAPPING,
         ADD_RATE_CATEGORY,
         REMOVE_RATE_CATEGORY,
         SET_PRICING_MAPPING,
         ADD_CUSTOMER_MAPPING,
         REMOVE_CUSTOMER_MAPPING,
         SET_RATING_FIELD,
         ADD_RATING_FIELD,
         ADD_RATING_PRIORITY,
         REMOVE_RATING_PRIORITY,
         REMOVE_RATING_FIELD,
         SET_RECEIVER_FIELD,
         CANCEL_KEY_AUTH,
         SET_FIELD_WIDTH,
         CLEAR_INPUT_PROCESSOR,
         REMOVE_USAGET_MAPPING,
         SET_USAGET_TYPE,
         SET_STATIC_USAGET,
         SET_LINE_KEY,
         SET_COMPUTED_LINE_KEY,
         UNSET_COMPUTED_LINE_KEY,
         SET_INPUT_PROCESSOR_TEMPLATE,
         MOVE_CSV_FIELD_DOWN,
         MOVE_CSV_FIELD_UP,
         CHANGE_CSV_FIELD,
	       UNSET_FIELD,
         SET_REALTIME_FIELD,
         SET_REALTIME_DEFAULT_FIELD,
         SET_CHECKED_FIELD,
	       REMOVE_RECEIVER,
         ADD_RECEIVER } from '@/actions/inputProcessorActions';

const defaultState = Immutable.fromJS({
  file_type: '',
  usaget_type: 'static',
  delimiter: '',
  fields: [],
  unfiltered_fields: [],
  field_widths: [],
  processor: {
    usaget_mapping: [],
  },
  customer_identification_fields: {},
  rate_calculators: {
    retail: {},
  },
  receiver: [],
  pricing: {},
  unify: {},
  /* receiver: {
   *   passive: false,
   *   delete_received: false
   * }*/
});

const defaultCustomerIdentification = Immutable.fromJS({
  target_key: 'sid',
  src_key: '',
  conditions: [{
    field: 'usaget',
    regex: "/.*/",
  }],
  clear_regex: '//',
});

export default function (state = defaultState, action) {
  const { field, mapping, width, index, priority } = action;
  let field_to_move, fieldWidthToMove;
  switch (action.type) {
    case GOT_PROCESSOR_SETTINGS:
      return Immutable.fromJS(action.settings);

    case SET_NAME:
      return state.set('file_type', action.file_type);

    case SET_PROCESSOR_TYPE:
      return state.set('type', action.processor_type);

    case SET_DELIMITER_TYPE:
      return state.set('delimiter_type', action.delimiter_type);

    case UPDATE_INPUT_PROCESSOR_FIELD:
      return state.setIn(action.fieldPath, action.value);

    case SET_DELIMITER:
      return state.set('delimiter', action.delimiter);

    case SET_FIELDS:
      if (state.get('unfiltered_fields').size > 0) {
        return state.update('unfiltered_fields', list => [...list, ...action.fields])
                    .set('fields', Immutable.List(state.get('unfiltered_fields')
                      .filter(field => field.get('checked') === true)
                      .map(field => field.get('name'))));
      }
      return state.set('unfiltered_fields', Immutable.fromJS(action.fields))
                  .set('fields', Immutable.fromJS(action.fields).map(field => field.get('name')));

    case SET_FIELD_WIDTH:
      return state.setIn(['field_widths', index], width);

    case SET_FIELD_MAPPING:
      return state.setIn(['processor', field], mapping);

    case ADD_CSV_FIELD: {
      const fieldToAdd = Immutable.Map({ name: field, checked: true });
      return state.update('unfiltered_fields', list => list.push(fieldToAdd))
                  .update('fields', list => list.push(field));
    }

    case REMOVE_CSV_FIELD: {
      const fieldName = state.getIn(['unfiltered_fields', index, 'name']);
      const newState = state.update('unfiltered_fields', list => list.delete(index));
      const indexToRemove = newState.get('fields').findIndex(field => field === fieldName);
      if (indexToRemove !== -1) {
        return newState.update('fields', list => list.delete(indexToRemove));
      }
      return newState;
    }

    case REMOVE_ALL_CSV_FIELDS:
      return state.set('unfiltered_fields', Immutable.List()).set('fields', Immutable.List());

    case CHECK_ALL_FIELDS: {
      const { checked } = action;
      return state.update('unfiltered_fields', Immutable.List(), list => list.map(field => field.set('checked', checked === true)))
                  .update('fields', Immutable.List(), list => checked ? state.get('unfiltered_fields').map(field => field.get('name')) : Immutable.List());
    }

    case SET_USAGET_TYPE:
      return state
        .set('usaget_type', action.usaget_type)
        .set('customer_identification_fields', Immutable.Map())
        .set('pricing', Immutable.Map())
        .setIn(['processor', 'usaget_mapping'], Immutable.List())
        .setIn(['processor', 'default_usaget'], '')
        .setIn(['processor', 'src_field'], '')
        .setIn(['rate_calculators'], Immutable.Map({ retail: Immutable.Map() }));

    case SET_STATIC_USAGET: {
      const rateCalculators = state.get('rate_calculators', Immutable.Map()).map(() => Immutable.Map({ [action.usaget]: Immutable.List() }));
      return state
        .setIn(['processor', 'default_usaget'], action.usaget)
        .set('rate_calculators', rateCalculators)
        .update('pricing', Immutable.Map(), map => map.clear().set(action.usaget, Immutable.Map()))
        .update('customer_identification_fields', Immutable.Map(), map => map.clear().set(action.usaget, Immutable.List()));
    }

    case MAP_USAGET: {
      const { usaget, pattern, unit, volumeType, volumeSrc, fieldName, conditions } = action.mapping;
      const newMap = Immutable.fromJS({
        usaget,
        pattern,
        unit,
        volume_type: volumeType,
        volume_src: volumeSrc,
        src_field: fieldName,
        conditions,
      });
      const rateCalculators = state.get('rate_calculators', Immutable.Map()).map(calc => ((!calc.has(usaget)) ? calc.set(usaget, Immutable.List()) : calc));
      return state
        .updateIn(['processor', 'usaget_mapping'], list => list.push(newMap))
        .set('rate_calculators', rateCalculators)
        .update('pricing', Immutable.Map(), map => ((!map.has(usaget)) ? map.set(usaget, Immutable.Map()) : map))
        .update('customer_identification_fields', Immutable.Map(), map => ((!map.has(usaget)) ? map.set(usaget, Immutable.List()) : map));
    }

    case REMOVE_USAGET_MAPPING: {
      const usaget = state.getIn(['processor', 'usaget_mapping', action.index, 'usaget']);
      const countUsaget = state
        .getIn(['processor', 'usaget_mapping'])
        .map(usagetMap => usagetMap.get('usaget'))
        .countBy(key => (key === usaget ? 'found' : 'notfound')).get('found', 0);
      const rateCalculators = state.get('rate_calculators', Immutable.Map()).map((calc) => {
        if (countUsaget === 1) {
          return calc.delete(usaget);
        }
        return calc;
      });
      return state
        .updateIn(['processor', 'usaget_mapping'], list => list.remove(action.index))
        .updateIn(['customer_identification_fields'], Immutable.Map(), (customerCalc) => {
          if (countUsaget === 1) {
            return customerCalc.delete(usaget);
          }
          return customerCalc;
        })
        .updateIn(['pricing'], Immutable.Map(), (priceCalc) => {
          if (countUsaget === 1) {
            return priceCalc.delete(usaget);
          }
          return priceCalc;
        })
        .set('rate_calculators', rateCalculators);;
    }

    case SET_CUSETOMER_MAPPING:
      return state.setIn(['customer_identification_fields', action.usaget, action.index, field], mapping);

    case SET_PRICING_MAPPING:
      return state.setIn(['pricing', action.usaget, field], mapping);

    case ADD_CUSTOMER_MAPPING:
      return state.updateIn(['customer_identification_fields', action.usaget], list => (list ? list.push(defaultCustomerIdentification) : Immutable.List([defaultCustomerIdentification])));

    case REMOVE_CUSTOMER_MAPPING:
      return state.updateIn(['customer_identification_fields', action.usaget], list => list.remove(priority));

    case ADD_RATE_CATEGORY: {
      const { rateCategory } = action;
      const rate = state.get('rate_calculators', Immutable.Map()).first().map(() => Immutable.List());
      return state.setIn(['rate_calculators', rateCategory], rate);
    }

    case REMOVE_RATE_CATEGORY: {
      const { rateCategory } = action;
      return state.deleteIn(['rate_calculators', rateCategory]);
    }

    case SET_RATING_FIELD: {
      const { rate_key, value, rateCategory, usaget } = action;
      const new_priority = state
        .getIn(['rate_calculators', rateCategory, usaget, priority, index])
        .set('type', value)
        .set('rate_key', rate_key);
      return state.setIn(['rate_calculators', rateCategory, usaget, priority, index], new_priority);
    }

    case ADD_RATING_FIELD: {
      const { rateCategory, usaget } = action;
      const newRating = Immutable.fromJS({
        type: '',
        rate_key: '',
        line_key: '',
      });
      return state.updateIn(['rate_calculators', rateCategory, usaget, priority], list => (list ? list.push(newRating) : Immutable.List([newRating])));
    }

    case ADD_RATING_PRIORITY: {
      const { rateCategory, usaget } = action;
      const newRating = Immutable.fromJS({
        type: '',
        rate_key: '',
        line_key: '',
      });
      return state.updateIn(['rate_calculators', rateCategory, usaget], list => list.push(Immutable.List([newRating])));
    }

    case REMOVE_RATING_PRIORITY: {
      const { rateCategory, usaget } = action;
      return state.updateIn(['rate_calculators', rateCategory, usaget], list => list.remove(priority));
    }

    case REMOVE_RATING_FIELD: {
      const { rateCategory, usaget } = action;
      return state.updateIn(['rate_calculators', rateCategory, usaget, priority], list => list.remove(index));
    }

    case REMOVE_RECEIVER: {
      return state.update('receiver', Immutable.List(), list => list.remove(index));
    }

    case ADD_RECEIVER: {
      const newReceiver = Immutable.fromJS({});
      return state.update('receiver', Immutable.List(), list => list.push(newReceiver));
    }

    case SET_LINE_KEY: {
      const { value, rateCategory, usaget } = action;
      const preFunctionFields = getFieldsWithPreFunctions().find(preFunctionField => preFunctionField.get('value') === value, null, false);
      if (preFunctionFields !== false) {
        const lineKey = preFunctionFields.get('preFunctionValue', '');
        const preFunction = preFunctionFields.get('preFunction', '');
        return state
          .setIn(['rate_calculators', rateCategory, usaget, priority, index, 'line_key'], lineKey)
          .setIn(['rate_calculators', rateCategory, usaget, priority, index, 'preFunction'], preFunction);
      }
      return state
        .setIn(['rate_calculators', rateCategory, usaget, priority, index, 'line_key'], value)
        .deleteIn(['rate_calculators', rateCategory, usaget, priority, index, 'preFunction']);
    }

    case SET_COMPUTED_LINE_KEY:
      return state.withMutations((stateWithMutations) => {
        action.paths.forEach((path, i) => {
          stateWithMutations.setIn(['rate_calculators', ...path], action.values[i]);
        });
      });

    case UNSET_COMPUTED_LINE_KEY:
      return state.deleteIn(['rate_calculators', action.rateCategory, action.usaget, action.priority, action.index, 'computed']);

    case SET_RECEIVER_FIELD:
      return state.setIn(['receiver', index, field], mapping);

    case CANCEL_KEY_AUTH:
      return state.deleteIn(['receiver', index, field]);

    case CLEAR_INPUT_PROCESSOR:
      return defaultState;

    case SET_INPUT_PROCESSOR_TEMPLATE:
      return Immutable.fromJS(action.template);

    case MOVE_CSV_FIELD_UP: {
      field_to_move = field ? field : state.getIn(['unfiltered_fields', index]);
      fieldWidthToMove = width ? width : state.getIn(['field_widths', index]);
      return state
        .update('unfiltered_fields', list => list.delete(index).insert(index - 1, field_to_move))
        .update('field_widths', list => list.delete(index).insert(index - 1, fieldWidthToMove));
    }

    case MOVE_CSV_FIELD_DOWN: {
      field_to_move = field ? field : state.getIn(['unfiltered_fields', index]);
      fieldWidthToMove = width ? width : state.getIn(['field_widths', index]);
      return state
        .update('unfiltered_fields', list => list.delete(index).insert(index + 1, field_to_move))
        .update('field_widths', list => list.delete(index).insert(index + 1, fieldWidthToMove));
    }

    case CHANGE_CSV_FIELD: {
      const { value } = action;
      const oldValue = state.getIn(['unfiltered_fields', index, 'name']);
      const newState = state.updateIn(['unfiltered_fields', index], struct => struct.set('name', value));
      const indexToChange = newState.get('fields').findIndex(field => field === oldValue);
      if (indexToChange !== -1) {
        return newState.update('fields', list => list.set(indexToChange, value));
      }
      return newState;
    }

    case UNSET_FIELD:
      return state.deleteIn(action.path);

    case SET_PARSER_SETTING:
      return state.setIn(['parser', action.name], action.value);

    case SET_REALTIME_FIELD:
      return state.setIn(['realtime', action.name], Immutable.fromJS(action.value));

    case SET_REALTIME_DEFAULT_FIELD:
      return state.setIn(['realtime', 'default_values', action.name], action.value);

    case SET_CHECKED_FIELD: {
      const { checked } = action;
      const fieldName = state.getIn(['unfiltered_fields', index, 'name']);
      const newState = state.updateIn(['unfiltered_fields', index], struct => struct.set('checked', checked));
      return newState.update('fields', list => {
        if (checked === false) {
          const indexToChange = list.findIndex(field => field === fieldName);
          if (indexToChange !== -1) {
            return list.delete(indexToChange);
          }
          return list;
        }
        return list.push(fieldName);
      });
    }

    default:
      return state;
  }
}
