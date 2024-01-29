import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Field from '@/components/Field';
import { apiBillRun } from '../../../common/Api';
import { getProductsKeysQuery } from '../../../common/ApiQueries';


export default class ProductSearch extends Component {

  static propTypes = {
    onSelectProduct: PropTypes.func.isRequired,
    searchFunction: PropTypes.object,
    filterFunction: PropTypes.func,
  }

  static defaultProps = {
    searchFunction: getProductsKeysQuery({ key: 1, description: 1 }),
    filterFunction: () => true,
  };

  state = {
    val: '',
    rates: [],
  }

  componentDidMount() {
    this.getProducts();
  }

  onSelectProduct = (productKey) => {
    if (productKey) {
      this.props.onSelectProduct(productKey);
    }
    this.setState({ val: '' });
  }

  getProducts = () => apiBillRun(this.props.searchFunction)
    .then((success) => {
      const options = success.data[0].data.details
      .map(option => ({
        value: option.key,
        label: `${option.key} (${option.description})`,
        play: option.play,
      }));
      this.setState({ rates: options });
    })
    .catch(() => {
      this.setState({ options: [] });
    });

  render() {
    const { val, rates } = this.state;
    const ratesOptions = rates.filter(this.props.filterFunction);
    return (
      <Field
        fieldType="select"
        value={val}
        options={ratesOptions}
        onChange={this.onSelectProduct}
        placeholder="Search by product key or title..."
        noResultsText="No products found, please try another key"
      />
    );
  }
}
