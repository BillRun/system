import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { debounce } from 'throttle-debounce';
import Field from '@/components/Field';
import { entitySearchByQuery } from '@/actions/entityActions';


const RateAsyncSearch = ({ editable, onChange, searchPlaceholder, noResultsPlaceholder, dispatch }) => {
  const findRates = (inputValue, callback) => {
    if (inputValue === '') {
      return callback([]);
    }
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

  const onChangeRate = (key) => {
    onChange(key);
  }

  return (
    <Field
      fieldType="select"
      onChange={onChangeRate}
      clearable={false}
      isAsync={true}
      isControlled={false}
      cacheOptions={true}
      placeholder={searchPlaceholder}
      loadAsyncOptions={debounceFindRates}
      noResultsText={noResultsPlaceholder}
      disabled={!editable}
    />
  );
}

RateAsyncSearch.defaultProps = {
  editable: true,
  accountsOptions: [],
  searchPlaceholder: "Enter key or title",
  noResultsPlaceholder: "No rates found",
};

RateAsyncSearch.propTypes = {
  editable: PropTypes.bool,
  searchPlaceholder: PropTypes.string,
  noResultsPlaceholder: PropTypes.string,
  accountsOptions: PropTypes.array,
  onChange: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

export default connect()(RateAsyncSearch);