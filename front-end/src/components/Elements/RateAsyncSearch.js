import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { debounce } from 'throttle-debounce';
import Field from '@/components/Field';
import { entitySearchByQuery } from '@/actions/entityActions';


const RateAsyncSearch = ({ rate, label, editable, onChange, searchPlaceholder, noResultsPlaceholder, dispatch }) => {
  const findRates = (inputValue, callback) => {
    // if (inputValue === '') {
    //   return callback([]);
    // }
    const query = {
      key: { $regex: inputValue, $options: 'i' },
      description: { $regex: inputValue, $options: 'i' },
    };
    const options = {or_fields: Object.keys(query)};
    const project = {key: 1, description: 1, play: 1, };
    const sort = {description: 1};
    return dispatch(entitySearchByQuery('rates', query, project, sort, options))
      .then(options => callback(createSelectOptions(Immutable.fromJS(options))))
      .catch(() => callback([]));
  }

  const createSelectOptions = options => options
    .map(createRateSelectOption)
    .toList()
    .toArray();

  const createRateSelectOption = option => ({
      value: option.get('key', ''),
      label: option.get('description', ''),
      play: option.play,
  })

  const debounceFindRates = debounce(500, (inputValue, callback) => {
    findRates(inputValue, callback);
  });

  const onChangeRate = (key, { option }) => {
    onChange(key, option);
  }

  let defaultInputValue = undefined;
  if (rate.length > 0) {
    defaultInputValue = {value: rate, label};
  }

  return (
    <Field
      fieldType="select"
      value={defaultInputValue}
      onChange={onChangeRate}
      clearable={false}
      isAsync={true}
      isControlled={true}
      cacheOptions={true}
      defaultOptions={true}
      placeholder={searchPlaceholder}
      loadAsyncOptions={debounceFindRates}
      noResultsText={noResultsPlaceholder}
      disabled={!editable}
    />
  );
}

RateAsyncSearch.defaultProps = {
  rate: '',
  label: '',
  editable: true,
  accountsOptions: [],
  searchPlaceholder: "Type a key or a title to search.",
  noResultsPlaceholder: "No rates were found. Try searching by another key or title.",
};

RateAsyncSearch.propTypes = {
  editable: PropTypes.bool,
  rate: PropTypes.string,
  label: PropTypes.string,
  searchPlaceholder: PropTypes.string,
  noResultsPlaceholder: PropTypes.string,
  accountsOptions: PropTypes.array,
  onChange: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

export default connect()(RateAsyncSearch);