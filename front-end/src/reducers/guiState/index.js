import { combineReducers } from 'redux';
import page from './pageReducer';
import menu from './menuReducer';


export default combineReducers({
  page,
  menu,
});
