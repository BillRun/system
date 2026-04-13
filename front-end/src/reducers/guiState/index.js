import { combineReducers } from 'redux';
import app from './appReducer';
import page from './pageReducer';
import menu from './menuReducer';


export default combineReducers({
  app,
  page,
  menu,
});
