import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { apiBillRun } from '../../../common/Api';
import { searchProductsByKeyAndUsagetQuery } from '../../../common/ApiQueries';
import Field from '@/components/Field';


export default class ProductSearchByUsagetype extends Component {

  static defaultProps = {
    disabled: false,
    existingProducts: Immutable.List(),
    products: Immutable.List(),
    usages: Immutable.List(),
    plays: '',
  }

  static propTypes = {
    onChangeGroupRates: PropTypes.func.isRequired,
    usages: PropTypes.instanceOf(Immutable.List),
    disabled: PropTypes.bool,
    existingProducts: PropTypes.instanceOf(Immutable.List),
    products: PropTypes.instanceOf(Immutable.List),
    plays: PropTypes.string,
  }

  state = {
    options: [],
  };

  componentDidMount() {
    this.loadOptions();
  }

  onChangeGroupRates = (productKeys) => {
    const productKeysList = (productKeys.length) ? productKeys.split(',') : [];
    this.props.onChangeGroupRates(Immutable.List(productKeysList));
  }

  loadOptions = () => {
    const { usages, existingProducts, plays } = this.props;
    const notKeys = existingProducts.toArray();
    const query = searchProductsByKeyAndUsagetQuery(usages.toArray(), notKeys, plays);
    return apiBillRun(query)
      .then((success) => {
        const uniqueKeys = [...new Set(success.data[0].data.details.map(option => option.key))];
        const options = uniqueKeys.map(key => ({
          value: key,
          label: key,
        }));
        this.setState({ options })
      })
      .catch(() => {
        this.setState({ options: [] })
      });
  }

  render() {
    const { disabled, usages, products } = this.props;
    const { options } = this.state;
    if (typeof usages === 'undefined') {
      return null;
    }
    return (
      <Field
        fieldType="select"
        multi={true}
        value={products.join(',')}
        options={options}
        onChange={this.onChangeGroupRates}
        disabled={disabled}
        placeholder="Add product..."
        noResultsText="No products found."
        searchPromptText="Type product key to search"
      />
    );
  }

}
