import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Map, List } from 'immutable';
import { Form, } from 'react-bootstrap';
import EntityDefaultTax from './EntityDefaultTax';
import { getList, clearList } from '@/actions/listActions';
import { getEntitesQuery } from '@/common/ApiQueries';


class EntityTaxDetails extends PureComponent {

  static propTypes = {
    tax: PropTypes.instanceOf(List),
    mode: PropTypes.string,
    itemName: PropTypes.string,
    typeOptions: PropTypes.instanceOf(List),
    taxRateOptions: PropTypes.instanceOf(List),
    onFieldUpdate: PropTypes.func.isRequired,
    onFieldRemove: PropTypes.func.isRequired,
    loadRates: PropTypes.func.isRequired,
    clearRates: PropTypes.func.isRequired,
  }

  static defaultProps = {
    tax: List(),
    mode: '',
    itemName: '',
    typeOptions: List([Map({ id:'vat', title: 'Vat'})]),
    taxRateOptions: List(),
  };

  static defaultTax = Map({
    type: 'vat',
    taxation: 'global',
  });

  componentWillMount() {
    this.props.loadRates();
    this.initDefaultValues();
  }

  componentWillUnmount() {
    this.props.clearRates();
  }

  initDefaultValues = () => {
      const { tax } = this.props;
      if (tax.isEmpty()) {
        this.props.onFieldUpdate(['tax'], List([EntityTaxDetails.defaultTax]));
      }
  }


  onUpdateTax = (path, value) => {
    // Temp fix - current support only for single tax object
    this.props.onFieldUpdate(['tax', 0, ...path], value);
  }

  render () {
    const { tax, mode, itemName, typeOptions, taxRateOptions } = this.props;
    const disabled = (mode === 'view');
    return (
      <Form horizontal>
        {tax.map((taxation, idx) => (
          <EntityDefaultTax
            key={idx}
            tax={taxation}
            itemName={itemName}
            typeOptions={typeOptions}
            taxRateOptions={taxRateOptions}
            disabled={disabled}
            onUpdate={this.onUpdateTax}
          />
        ))}
      </Form>
    );
  }
}


const mapStateToProps = (state, props) => ({
  taxRateOptions: state.list.get('available_taxRates'),
});

const mapDispatchToProps = dispatch => ({
  loadRates: () => dispatch(getList('available_taxRates', getEntitesQuery('taxes', { key: 1, description: 1 }))),
  clearRates: () => dispatch(clearList('available_taxRates')),
});

export default connect(mapStateToProps, mapDispatchToProps)(EntityTaxDetails);
