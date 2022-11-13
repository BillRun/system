import { showSuccess } from './alertsActions';
import { apiBillRun, apiBillRunErrorHandler } from '../common/Api';
import { startProgressIndicator, finishProgressIndicator } from './progressIndicatorActions';


export const SET_GENERATOR_NAME = 'SET_GENERATOR_NAME';
export const SELECT_INPUT_PROCESSOR = 'SELECT_INPUT_PROCESSOR';
export const SET_SEGMENTATION = 'SET_SEGMENTATION';
export const ADD_SEGMENTATION = 'ADD_SEGMENTATION';
export const DELETE_SEGMENTATION = 'DELETE_SEGMENTATION';
export const CLEAR_EXPORT_GENERATOR = 'CLEAR_EXPORT_GENERATOR';
export const SET_FTP_FIELD = 'SET_FTP_FIELD';
export const GOT_EXPORT_GENERATOR = 'GOT_EXPORT_GENERATOR';


function gotExportGenerator(generator) {
  return {
    type: GOT_EXPORT_GENERATOR,
    generator
  };
}

function fetchExportGenerator(name) {
  const query = {
    api: "settings",
    params: [
      { category: 'export_generators' },
      { action: 'get' },
      { data: JSON.stringify({name}) }
    ]
  };

  return dispatch => {
    dispatch(startProgressIndicator());
    apiBillRun(query).then(
      resp => {
        dispatch(finishProgressIndicator());
        dispatch(gotExportGenerator(resp.data[0].data.details));
      }
    ).catch(
      error => {
        dispatch(finishProgressIndicator());
        dispatch(apiBillRunErrorHandler(error, `Error loading export generator ${name}`));
      }
    );
  };
}

export function getExportGenerator(name) {
  return dispatch => {
    return dispatch(fetchExportGenerator(name));
  };
}

export function setGeneratorName(name) {
  return {
    type: SET_GENERATOR_NAME,
    name
  };
}

export function selectInputProcessor(inputProcessor) {
  return {
    type: SELECT_INPUT_PROCESSOR,
    inputProcessor
  };
}

export function setSegmentation(index, key, value) {
  return {
    type: SET_SEGMENTATION,
    index,
    key,
    value
  };
}

export function addSegmentation() {
  return {
    type: ADD_SEGMENTATION
  };
}

export function deleteSegmentation(index) {
  return {
    type: DELETE_SEGMENTATION,
    index
  };
}

export function clearExportGenerator() {
  return {
    type: CLEAR_EXPORT_GENERATOR
  };
}

export function setFtpField(field, value) {
  return {
    type: SET_FTP_FIELD,
    field,
    value
  };
}

function saveExportGeneratorToDB(generator) {
  const query = {
    api: "settings",
    params: [
      { category: "export_generators" },
      { action: "set" },
      { data: JSON.stringify(generator.remove('inputProcess').toJS()) }
    ]
  };

  return dispatch => {
    dispatch(startProgressIndicator());
    apiBillRun(query).then(
      success => {
        dispatch(showSuccess("Export generator saved successfully"));
        dispatch(finishProgressIndicator());
      },
      failure => {
        dispatch(apiBillRunErrorHandler('Error'));
        dispatch(finishProgressIndicator());
      }
    ).catch(
      error => { dispatch(apiBillRunErrorHandler(error)); }
    );
  };
}

export function saveExportGenerator() {
  return (dispatch, getState) => {
    return dispatch(saveExportGeneratorToDB(getState().exportGenerator));
  };
}
