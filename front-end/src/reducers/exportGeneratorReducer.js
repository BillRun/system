import Immutable from 'immutable';

import {
  GOT_EXPORT_GENERATOR,
  SELECT_INPUT_PROCESSOR,
  SET_GENERATOR_NAME,
  SET_SEGMENTATION,
  ADD_SEGMENTATION,
  DELETE_SEGMENTATION,
  CLEAR_EXPORT_GENERATOR,
  SET_FTP_FIELD
} from '@/actions/exportGeneratorActions';

let defaultState = Immutable.fromJS({
  name: '',
  inputProcess: {},
  segments: [{field: null, from: null, to: null}]
});

export default function (state = defaultState, action) {
  // const {field, mapping, width} = action;

  switch (action.type) {
    case GOT_EXPORT_GENERATOR:
      return Immutable.fromJS(action.generator);

    case SET_GENERATOR_NAME:
      return state.set('name', action.name);

    case SELECT_INPUT_PROCESSOR:
      return state.set('inputProcess', action.inputProcessor).set('file_type', action.inputProcessor.get('file_type'));

    case SET_SEGMENTATION:
      let segment = state.get('segments').get(action.index);
      let segments = state.get('segments');
      segment = segment.set(action.key, action.value);
      segments = segments.set(action.index, segment);
      return state.set('segments', segments);

    case ADD_SEGMENTATION:
      const newSegment = Immutable.fromJS({field: null, from: null, to: null});
      return state.update('segments', segments => segments.push(newSegment)); //state.set('segments', state.get('segments').push(newSegment));

    case DELETE_SEGMENTATION:
        return state.set('segments', state.get('segments').delete(action.index));

    case CLEAR_EXPORT_GENERATOR:
      return defaultState;

    case SET_FTP_FIELD:
      /* TODO: Change 'receive' name most likely... */
      return state.setIn(['receiver', action.field], action.value);

    default:
      return state;
  }
}
