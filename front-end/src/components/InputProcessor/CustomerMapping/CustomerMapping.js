import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import classNames from 'classnames';
import Field from '@/components/Field';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { getFieldName, getAvailableFields } from '@/common/Util';

class CustomerMapping extends Component {
  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.Map),
    usaget: PropTypes.string.isRequired,
    mapping: PropTypes.instanceOf(Immutable.Map).isRequired,
    priority: PropTypes.number.isRequired,
    onSetCustomerMapping: PropTypes.func.isRequired,
    subscriberFields: PropTypes.instanceOf(Immutable.List),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    settings: Immutable.Map(),
    subscriberFields: Immutable.List(),
  };

  onSetCustomerMapping = (e) => {
    const { usaget, priority } = this.props;
    const { value, id } = e.target;
    this.props.onSetCustomerMapping(id, value, usaget, priority);
  }

  onSetCustomerMappingTarget = (e) => {
    const { value, id } = e.target;
    const { subscriberFields } = this.props;
    const event = { target: { id, value } };
    const isUnique = subscriberFields.find(
      subscriberField => subscriberField.get('field_name', '') === value,
      null, Immutable.Map(),
    ).get('unique', false);
    // If new field is unique -> update, if not unique - show confirm
    if (isUnique) {
      return this.onSetCustomerMapping(event);
    }
    const onOk = () => {
      this.onSetCustomerMapping(event);
    };
    const confirm = {
      message: 'You selected a non-unique field for subscriber matching. Continue?',
      onOk,
      type: 'delete',
      labelOk: 'Continue',
    };
    return this.props.dispatch(showConfirmModal(confirm));
  }

  onChangeClearRegex = (value) => {
    const { usaget, priority } = this.props;
    this.props.onSetCustomerMapping('clear_regex', value, usaget, priority);
  }

  getCustomerIdentificationFields = () => getAvailableFields(this.props.settings)
    .map((field, key) => (
      <option value={field.get('value', '')} key={key}>{field.get('label', '')}</option>
    ))
    .toArray();


  getAvailableTargetFields = () => {
    const { subscriberFields } = this.props;
    const options = subscriberFields
      .sort(field => (field.get('unique', false) ? -1 : 1))
      .map((field, key) => {
        const value = field.get('field_name', '');
        const label = getFieldName(value, 'customerIdentification', field.get('title', ''));
        const optionClass = classNames({
          'label-text': field.get('unique', false),
          disabled: !field.get('unique', false),
        });
        return (
          <option value={value} key={`target-field-${key}`} className={optionClass}>{label}</option>
        );
      });

    return([
      <option disabled value="-1" key="target-field-empty">Select...</option>,
      ...options.toArray()
    ]);
  }

  render() {
    const { mapping } = this.props;
    const targetKey = mapping.getIn(['target_key'], 'sid');
    const srcKey = mapping.getIn(['src_key'], '');
    const clearRegex = mapping.getIn(['clear_regex'], '');
    const availableFields = this.getCustomerIdentificationFields();
    const availableTargetFields = this.getAvailableTargetFields();

    return (
      <div>
        <div className="col-lg-4">
          <select id="src_key" className="form-control" onChange={this.onSetCustomerMapping} value={srcKey} >
            { availableFields }
          </select>
        </div>
        <div className="col-lg-4">
          <Field
            value={clearRegex}
            id="clear_regex"
            disabledValue={'//'}
            onChange={this.onChangeClearRegex}
            label="Regex"
            fieldType="toggeledInput"
          />
        </div>
        <div className="col-lg-4">
          <select id="target_key" className="form-control" onChange={this.onSetCustomerMappingTarget} value={targetKey}>
            { availableTargetFields }
          </select>
        </div>
      </div>
    );
  }
}

export default connect(null)(CustomerMapping);
