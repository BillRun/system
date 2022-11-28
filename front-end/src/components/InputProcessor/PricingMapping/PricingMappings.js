import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form } from 'react-bootstrap';
import PricingMapping from './PricingMapping';

class PricingMappings extends Component {
  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.Map),
    onSetPricingMapping: PropTypes.func.isRequired,
  }

  static defaultProps = {
    settings: Immutable.Map(),
  };

  render() {
    const { settings } = this.props;
    const pricingMappings = settings.get('pricing', Immutable.Map());
    return (
      <Form horizontal className="pricingMappings">
        <div className="form-group">
          <div className="col-lg-12">
            <h4>
              Pre-Pricing
              <p className="help-block">When Pre priced is checked, the price will be taken directly from the record instead of being calculated</p>
            </h4>
          </div>
        </div>
        {pricingMappings.map((mappings, usaget) => (
          <div key={`pricing-mapping-${usaget}`}>
            <div className="form-group">
              <div className="col-lg-3">
                <label htmlFor={usaget}>{ usaget }</label>
              </div>
              <div className="col-lg-9">
                <div className="col-lg-1" style={{ marginTop: 8 }}>
                  <i className="fa fa-long-arrow-right" />
                </div>
                <div className="row">
                  <div className="col-lg-10">
                    <PricingMapping
                      usaget={usaget}
                      mapping={mappings}
                      onSetPricingMapping={this.props.onSetPricingMapping}
                      settings={settings}
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        )).toArray()}
      </Form>
    );
  }
}

export default connect()(PricingMappings);
