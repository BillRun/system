import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import DiscountDetails from './DiscountDetails';
import { currencySelector } from '@/selectors/settingsSelector';
import { FormGroup, InputGroup, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  getConfig,
  getItemDateValue,
} from '@/common/Util';


class DiscountPopup extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    errors: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string.isRequired,
    hideFields: PropTypes.array,
    currency: PropTypes.string,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    errors: Immutable.Map(),
    hideFields: false,
    currency: '',
  }

  shouldComponentUpdate(nextProps) {
    const { item, mode, hideFields, currency, errors } = this.props;
    return (
      !Immutable.is(item, nextProps.item)
      || !Immutable.is(errors, nextProps.errors)
      || mode !== nextProps.mode
      || hideFields !== nextProps.hideFields
      || currency !== nextProps.currency
    );
  }

  onRemoveFieldValue = (path) => {
    const pathString = path.join('.');
    this.props.setError(pathString);
    this.props.removeField(path);
  }

  onChangeFieldValue = (path, value) => {
    const pathString = path.join('.');
    this.props.setError(pathString);
    this.props.updateField(path, value);
  }

  onChangeFromTo = (value) => {
    this.props.setError('from');
    this.props.setError('to');
    if (Immutable.Map.isMap(value)) {
      this.props.updateField(['from'], value.get('from', ''));
      this.props.updateField(['to'], value.get('to', ''));
    } else {
      this.props.updateField(['from'], '');
      this.props.updateField(['to'], '');
    }
  }

  render() {
    const { item, mode, currency, hideFields, errors } = this.props;
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
    const rangeValue = Immutable.Map({
      from: getItemDateValue(item, 'from').format(apiDateTimeFormat),
      to: getItemDateValue(item, 'to').format(apiDateTimeFormat),
    });
    const editable = !['view'].includes(mode);
    const fromToError = errors.get('from', errors.get('to', false));
    return (
      <div className="discount-setup">

        <FormGroup className="form-inner-edit-row mr0 ml0 text-center" validationState={fromToError ? 'error' : null}>
          <InputGroup className="full-width">
            <Field
              fieldType="range"
              value={rangeValue}
              onChange={this.onChangeFromTo}
              editable={editable}
              inputProps={{fieldType: 'date', isClearable: true}}
              inputFromProps={{selectsStart: true, endDate:'@valueTo@'}}
              inputToProps={{selectsEnd: true, startDate: '@valueFrom@', endDate: '@valueTo@', minDate: '@valueFrom@'}}
            />
          </InputGroup>
          { fromToError && (<HelpBlock className="text-left"><small>{fromToError}</small></HelpBlock>)}
        </FormGroup>

        <DiscountDetails
          discount={item}
          mode={mode}
          hideFields={hideFields}
          currency={currency}
          onFieldUpdate={this.onChangeFieldValue}
          onFieldRemove={this.onRemoveFieldValue}
          errors={errors}
        />
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  currency: currencySelector(state, props) || undefined,
});

export default connect(mapStateToProps)(DiscountPopup);
