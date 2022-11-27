import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { titleCase } from 'change-case';
import { Panel } from 'react-bootstrap';
import Importer from '../Importer';
import { getSettings } from '@/actions/settingsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { clearList, clearRevisions } from '@/actions/entityListActions';
import { itemSelector, importItemTypeSelector } from '@/selectors/entitySelector';
import { getConfig } from '@/common/Util';


class ImporterSetup extends Component {

  static defaultProps = {
    importEntity: undefined,
    item: Immutable.Map(),
  };

  static propTypes = {
    importEntity: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  state = {
    refreshString: '',
  }

  componentWillMount() {
    const { importEntity } = this.props;
    if (importEntity) {
      const entityName = getConfig(['systemItems', importEntity, 'itemsName'], importEntity);
      this.props.dispatch(setPageTitle(titleCase(`Import ${entityName}`)));
    } else {
      this.props.dispatch(setPageTitle('Import'));
    }
    this.props.dispatch(getSettings(['rates.fields', 'subscribers.account', 'subscribers.subscriber']));
  }

  componentWillReceiveProps(nextProps) {
    const { importEntity } = nextProps;
    if (this.props.importEntity !== importEntity) {
      this.setState({
        refreshString: moment().format(), //refetch screen import
      });
      if (!importEntity) {
        this.props.dispatch(setPageTitle('Import'));
      } else {
        const entityName = getConfig(['systemItems', importEntity, 'itemsName'], importEntity);
        this.props.dispatch(setPageTitle(titleCase(`Import ${entityName}`)));
      }
    }
  }

  onCloseImport = () => {
    const { item, importEntity } = this.props;
    if (importEntity) {
      this.clearList(item.get('entity', ''));
      const entity = (['subscription', 'customer'].includes(item.get('entity', ''))) ? 'customer' : item.get('entity', '');
      const listUrl = getConfig(['systemItems', entity, 'itemsType'], '');
      this.props.router.push(`/${listUrl}`);
    } else {
      this.setState({
        refreshString: moment().format(), //refetch screen import
      });
    }
  }

  clearList = (entityName) => {
    if (entityName !== '') {
      if (['subscription', 'customer'].includes(entityName)) {
        this.props.dispatch(clearList('customers'));
        this.props.dispatch(clearList('subscribers'));
      } else {
        const clearlistName = getConfig(['systemItems', entityName, 'itemsType'], '');
        this.props.dispatch(clearList(clearlistName));
      }
      const collection = getConfig(['systemItems', entityName, 'collection'], '');
      this.props.dispatch(clearRevisions(collection));
    }
  }

  render() {
    const { refreshString } = this.state;
    const { importEntity } = this.props;
    const importEntities = (importEntity) ? Immutable.List([importEntity]) : importEntity;
    const apiDateFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
    const defaultFrom = moment().format(apiDateFormat);
    const defaultTo = moment().add(100, 'years').format(apiDateFormat);

    const subscriptionDefaultValues = Immutable.Map({
      from: defaultFrom,
      to: defaultTo,
    });
    const customerDefaultValues = Immutable.Map({
      from: defaultFrom,
      to: defaultTo,
    });
    const productDefaultValues = Immutable.Map({
      from: defaultFrom,
      to: defaultTo,
      price_plan: 'BASE',
      pricing_method: 'tiered',
      price_from: 0,
      price_to: getConfig('productUnlimitedValue', ''),
    });
    const defaultValues = Immutable.Map({
      subscription: subscriptionDefaultValues,
      customer: customerDefaultValues,
      product: productDefaultValues,
    });
    const predefinedValues = Immutable.Map({
      // product: Immutable.Map({
      //   pricing_method: 'tiered',
      // }),
    });
    return (
      <Panel>
        <Importer
          entityOptions={importEntities}
          onFinish={this.onCloseImport}
          onClearItems={this.clearList}
          defaultValues={defaultValues}
          predefinedValues={predefinedValues}
          restartString={refreshString}
        />
      </Panel>
    );
  }
}

const mapStateToProps = (state, props) => ({
  importEntity: importItemTypeSelector(state, props) || undefined,
  item: itemSelector(state, props, 'importer'),
});
export default withRouter(connect(mapStateToProps)(ImporterSetup));
