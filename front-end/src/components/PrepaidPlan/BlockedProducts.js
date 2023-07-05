import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import { apiBillRun } from '../../common/Api';
import { getProductsKeysQuery } from '../../common/ApiQueries';


class BlockedProducts extends Component {

  static propTypes = {
    plan: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string,
    onChangeBlockProduct: PropTypes.func,
  };

  static defaultProps = {
    plan: Immutable.Map(),
    mode: 'create',
    onChangeBlockProduct: () => {},
  };

  state = {
    options: [],
  };

  componentDidMount() {
    this.loadOptions();
  }

  loadOptions = () => apiBillRun(getProductsKeysQuery({ key: 1, description: 1 }))
    .then((success) => {
      const uniqueKeys = [...new Set(success.data[0].data.details.map(option => option.key))];
      const options = uniqueKeys.map(key => ({
        value: key,
        label: key,
      }));
      this.setState({ options });
    })
    .catch(() => {
      this.setState({ options: [] })
    });

  render() {
    const { plan, mode } = this.props;
    const { options } = this.state;
    const editable = (mode !== 'view');
    const products = plan.get('disallowed_rates', Immutable.List()).join(',');
    return (
      <div className="BlockedProducts">
        <Panel header={<h3>Blocked products</h3>}>
          <Field
            fieldType="select"
            value={products}
            options={options}
            onChange={this.props.onChangeBlockProduct}
            multi={true}
            disabled={!editable}
            placeholder="Add product..."
            noResultsText="No products found."
            searchPromptText="Type product key to search"
          />
        </Panel>
      </div>
    );
  }
}

export default BlockedProducts;
