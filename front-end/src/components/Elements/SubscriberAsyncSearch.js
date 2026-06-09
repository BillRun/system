import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { debounce } from 'throttle-debounce';
import Field from '@/components/Field';
import {
  getFirstName,
  getLastName,
} from '@/common/Util';
import { entitySearchByQuery } from '@/actions/entityActions';


const SubscriberAsyncSearch = ({
   sid, aid, label, editable, searchPlaceholder, noResultsPlaceholder,
   onChange, dispatch
}) => {

  const defaultOptions = {
    value: 0,
    label: 'Customer Level',
    aid: 0,
  }

  const findSubscribers = (inputValue, callback) => {
    if (inputValue === '') {
      return callback([defaultOptions]);
    }
    const query = {
      firstname: { $regex: inputValue, $options: 'i' },
      lastname: { $regex: inputValue, $options: 'i' },
      aid: parseFloat(aid),
      sid: parseFloat(inputValue),
    };
    const options = {or_fields: ['firstname', 'lastname', 'sid']};
    const project = {sid: 1, aid: 1, firstname: 1, lastname: 1};
    const sort = {aid: 1, sid: 1, firstname: 1, lastname: 1};
    return dispatch(entitySearchByQuery('subscribers', query, project, sort, options))
      .then(options =>
        callback(subscriptionsSelectOptions(Immutable.fromJS(options)))
      )
      .catch(() => callback([]));
  }

  const subscriptionsSelectOptions = (options, sids = []) => options
    .filter(option => !sids.includes(option.get('sid', '')))
    .map(createSubscriptionsSelectOption)
    .toList()
    .insert(0, defaultOptions)
    .toArray();

  const createSubscriptionsSelectOption = option => {
    const name = [getFirstName(option), getLastName(option)]
      .map(option => option.trim())
      .filter(option => option !== '')
      .join(' ');
    const sid = option.get('sid', '');
    const aid = option.get('aid', '');
    return {
      value: isNumber(sid) ? parseFloat(sid) : sid,
      label: `${name} (Subscriber ID: ${option.get('sid', '')}, Customer ID: ${option.get('aid', '')})`,
      aid: isNumber(aid) ? parseFloat(aid) : aid,
      name,
    };
  }

  const debounceFindSubscribers = debounce(500, (inputValue, callback) => {
    findSubscribers(inputValue, callback);
  });

  const onChangeSubscriber = (sid, { option }) => {
    onChange(sid, option);
  }
  let defaultInputValue = undefined;
  if (isNumber(sid)) {
    defaultInputValue = {value: parseFloat(sid), label};
  }

  return (
    <Field
      fieldType="select"
      value={defaultInputValue}
      onChange={onChangeSubscriber}
      clearable={false}
      isAsync={true}
      isControlled={true}
      cacheOptions={true}
      defaultOptions={true}
      placeholder={searchPlaceholder}
      loadAsyncOptions={debounceFindSubscribers}
      noResultsText={noResultsPlaceholder}
      disabled={!editable}
    />
  );
}

SubscriberAsyncSearch.defaultProps = {
  sid: '',
  aid: '',
  label: '',
  currency: '',
  editable: true,
  accountsOptions: [],
  searchPlaceholder: "Type a subscriber ID, first, or last name to search.",
  noResultsPlaceholder: "No subscriber were found. Try searching by another ID or name.",
};

SubscriberAsyncSearch.propTypes = {
  sid: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]).isRequired,
  aid: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]).isRequired,
  label: PropTypes.string,
  currency: PropTypes.string,
  editable: PropTypes.bool,
  searchPlaceholder: PropTypes.string,
  noResultsPlaceholder: PropTypes.string,
  accountsOptions: PropTypes.array,
  onChange: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

export default connect()(SubscriberAsyncSearch);
