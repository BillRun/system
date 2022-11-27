import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col, InputGroup } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import { getFieldName } from '@/common/Util';
import Field from '@/components/Field';
import Help from '@/components/Help';
import { DiscountDescription } from '@/language/FieldDescriptions';


class DiscountPlanValue extends Component {

  static propTypes = {
    name: PropTypes.string,
    label: PropTypes.string,
    plan: PropTypes.instanceOf(Immutable.Map),
    isPercentage: PropTypes.bool.isRequired,
    mode: PropTypes.string.isRequired,
    currency: PropTypes.string,
    onChange: PropTypes.func.isRequired,
  };

  static defaultProps = {
    name: '',
    label: '',
    plan: Immutable.Map(),
    isPercentage: false,
    currency: '',
  };

  onChangeSequential = (e) => {
    const { name, plan } = this.props;
    const { value } = e.target;    
    this.props.onChange(name, plan.set('sequential', value));
  }

  onChangeValue = (e) => {
    const { name, plan } = this.props;
    const { value } = e.target;
    this.props.onChange(name, plan.set('value', value));
  }

  getValue = () => {
    const { plan } = this.props;
    return plan.get('value', '');
  }

  getSuffix = () => {
    const { isPercentage, currency } = this.props;
    return isPercentage ? undefined : getSymbolFromCurrency(currency);
  }

  renderSequentialLabel = () => (
    <span>
      {getFieldName('sequential', 'discount')}
      <Help contents={DiscountDescription.sequential} />
    </span>
  )

  render() {
    const { name, label, mode, plan, isPercentage } = this.props;
    if (name === '') {
      return null;
    }
    const editable = (mode !== 'view');
    const value = this.getValue();
    if (!editable && value === null) {
      return null;
    }
    const showSequential = isPercentage;
    return (
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3} lg={2}>
          { label }
        </Col>
        <Col sm={8} lg={9}>
          <InputGroup className="full-width">
            <Field
              value={value}
              onChange={this.onChangeValue}
              fieldType={isPercentage ? "percentage" : "number"}
              editable={editable}
              suffix={this.getSuffix()}
            />
            {showSequential && (
              <InputGroup.Addon className="input-group-space pr0 pl5"> </InputGroup.Addon>
            )}
            {showSequential && (
              <InputGroup.Addon>
                <Field
                  value={plan.get('sequential', false)}
                  onChange={this.onChangeSequential}
                  fieldType="checkbox"
                  label={this.renderSequentialLabel()}
                />
              </InputGroup.Addon>
            )}
          </InputGroup>
        </Col>
      </FormGroup>
    );
  }
}

export default DiscountPlanValue;
