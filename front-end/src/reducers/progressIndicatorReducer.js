import {
  PROGRESS_INDICATOR_FINISH,
  PROGRESS_INDICATOR_START,
  PROGRESS_INDICATOR_DISMISS } from '@/actions/progressIndicatorActions';


export default function(state = 0, action) {

  switch(action.type) {
    case PROGRESS_INDICATOR_START: return state + 1;
    case PROGRESS_INDICATOR_FINISH: return (state > 0) ? state - 1 : 0;
    case PROGRESS_INDICATOR_DISMISS: return 0;
    default: return state;
  }
}
