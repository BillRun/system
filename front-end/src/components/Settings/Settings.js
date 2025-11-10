import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import { Tabs, Tab, Panel } from 'react-bootstrap';
import DateTime from './DateTime';
import Currency from './Currency';
import Invoicing from './Invoicing';
import Plugins from './Plugins/PluginsContainer';
//import Allowances from './Allowances';
import Plays from './Plays/PlaysContainer';
import Tax from './Tax';
import Tenant from './Tenant';
import Security from './Security';
import EditMenu from './EditMenu';
import UsageTypes from './UsageTypes';
import Subscribers from './Subscribers';
import System from './System';
import { ActionButtons } from '@/components/Elements';
import { getSettings, updateSetting, saveSettings, fetchFile, getCurrencies } from '@/actions/settingsActions';
import { prossessMenuTree, combineMenuOverrides, initMainMenu } from '@/actions/guiStateActions/menuActions';
import { getList, clearList } from '@/actions/listActions';
import { getEntitesQuery } from '@/common/ApiQueries';
import { tabSelector } from '@/selectors/entitySelector';
import {
  inputProssesorCsiOptionsSelector,
  taxationSelector,
  systemSettingsSelector,
  playsSettingsSelector,
} from '@/selectors/settingsSelector';
import { getFieldName } from '@/common/Util';


class Settings extends Component {

  static defaultProps = {
    activeTab: 10,
    settings: Immutable.Map(),
    csiOptions: Immutable.List(),
    taxation: Immutable.Map(),
    system: Immutable.Map(),
    plays: Immutable.List(),
  };

  static propTypes = {
    activeTab: PropTypes.number,
    settings: PropTypes.instanceOf(Immutable.Map),
    location: PropTypes.shape({
      pathname: PropTypes.string,
      query: PropTypes.object,
    }).isRequired,
    csiOptions: PropTypes.instanceOf(Immutable.Iterable),
    taxation: PropTypes.instanceOf(Immutable.Map),
    system: PropTypes.instanceOf(Immutable.Map),
    plays: PropTypes.instanceOf(Immutable.List),
    router: PropTypes.object.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  state = {
    currencyOptions: [],
    changeCategories: Immutable.Set(),
    // playsBeforeSave: Immutable.List(),
  };

  componentWillMount() {
    const settingsToFetch = [
      'subscribers',
      'pricing',
      'billrun',
      'tenant',
      'shared_secret',
      'menu',
      'taxation',
      'file_types',
      'system',
      'plugins',
      'plays'
    ];
    this.props.dispatch(getSettings(settingsToFetch));
    this.props.dispatch(getCurrencies()).then(this.initCurrencyOptions);
    this.props.dispatch(getList('available_taxRates', getEntitesQuery('taxes', { key: 1, description: 1 })));
  }

  componentWillUnmount() {
    const { changeCategories } = this.state;
    if (!changeCategories.isEmpty()) {
      this.props.dispatch(getSettings(changeCategories.toArray()));
    }
    this.props.dispatch(clearList('available_taxRates'));
  }

  initCurrencyOptions = (response) => {
    if (response.status) {
      const currencyOptions = Immutable.fromJS(response.data)
        .map(currency => ({
          label: `${currency.get('code', '')} - ${currency.get('name', '')} ${currency.get('symbol', '')}`,
          value: currency.get('code', ''),
        }))
        .toArray();
      this.setState({ currencyOptions });
    }
  }

  onChangeFieldValue = (category, id, value) => {
    this.setState((prevState) => ({ changeCategories: prevState.changeCategories.add(category) }));
    this.props.dispatch(updateSetting(category, id, value));
  }

  onChangeMenuOrder = (path, newOrder) => {
    const { settings } = this.props;
    const mainMenuOverrides = settings.getIn(['menu', ...path], Immutable.Map()).withMutations(
      (mainMenuOverridesWithMutations) => {
        newOrder.forEach((order, key) => {
          if (mainMenuOverridesWithMutations.has(key)) {
            mainMenuOverridesWithMutations.setIn([key, 'order'], order);
          } else {
            const orderField = Immutable.Map({ order });
            mainMenuOverridesWithMutations.set(key, orderField);
          }
        });
      },
    );
    this.props.dispatch(updateSetting('menu', path, mainMenuOverrides));
  }

  onSave = () => {
    const { changeCategories } = this.state;
    if (!changeCategories.isEmpty()) {
      const categoryToSave = changeCategories.toArray();
      this.props.dispatch(saveSettings(categoryToSave))
        .then((response) => {
          this.afterSave(response, categoryToSave);
        });
    }
  }

  afterSave = (response, categoryToSave) => {
    const { settings } = this.props;
    if (response && (response.status === 1 || response.status === 2)) { // settings successfully saved
      // Reload Menu
      const mainMenuOverrides = settings.getIn(['menu', 'main'], Immutable.Map());
      this.props.dispatch(initMainMenu(mainMenuOverrides));
      this.setState(() => ({ changeCategories: Immutable.Set() }));
      // Update logo
      if (categoryToSave.includes('tenant') && settings.getIn(['tenant', 'logo'], '').length > 0) {
        localStorage.removeItem('logo');
        this.props.dispatch(fetchFile({ filename: settings.getIn(['tenant', 'logo'], '') }, 'logo'));
      }
    }
  };


  handleSelectTab = (tab) => {
    const { pathname, query } = this.props.location;
    this.props.router.push({
      pathname,
      query: Object.assign({}, query, { tab }),
    });
  }

  render() {
    const { settings, activeTab, csiOptions, rasRatesOptions, taxation, system, plays } = this.props;
    const { currencyOptions } = this.state;

    const subscribers = settings.get('subscribers', Immutable.Map());
    const currency = settings.getIn(['pricing', 'currency'], '');
    const plugins = settings.get('plugins', Immutable.List());
    const billrun = settings.get('billrun', Immutable.Map());
    const sharedSecret = settings.get('shared_secret', Immutable.List());
    const tenant = settings.get('tenant', Immutable.Map());
    const mainMenuOverrides = settings.getIn(['menu', 'main'], Immutable.Map());
    const mainMenu = prossessMenuTree(combineMenuOverrides(mainMenuOverrides), 'root');

    return (
      <div>
        <Tabs activeKey={activeTab} animation={false} id="SettingsTab" onSelect={this.handleSelectTab}>
          <Tab title="Company" eventKey={10}>
            <Panel style={{ borderTop: 'none' }}>
              <Tenant onChange={this.onChangeFieldValue} data={tenant} />
            </Panel>
          </Tab>


          <Tab title="Locale" eventKey={20}>
            <Panel style={{ borderTop: 'none' }}>
              <DateTime onChange={this.onChangeFieldValue} data={billrun} />
              <Currency
                onChange={this.onChangeFieldValue}
                data={currency}
                currencies={currencyOptions}
              />
            </Panel>
          </Tab>

          <Tab title="Tax" eventKey={30}>
            <Panel style={{ borderTop: 'none' }}>
              <Tax
                data={taxation}
                csiOptions={csiOptions}
                taxRateOptions={rasRatesOptions}
                onChange={this.onChangeFieldValue}
              />
            </Panel>
          </Tab>

          <Tab title="Menu" eventKey={40}>
            <Panel style={{ borderTop: 'none' }}>
              <EditMenu
                data={mainMenu}
                onChange={this.onChangeFieldValue}
                onChangeMenuOrder={this.onChangeMenuOrder}
              />
            </Panel>
          </Tab>

          <Tab title="Security" eventKey={50}>
            <Panel style={{ borderTop: 'none' }}>
              <Security data={sharedSecret} />
            </Panel>
          </Tab>

          <Tab title={getFieldName('subs', 'settings')} eventKey={55}>
            <Panel style={{ borderTop: 'none' }}>
              <Subscribers onChange={this.onChangeFieldValue} data={subscribers} />
            </Panel>
          </Tab>

          <Tab title="Invoicing" eventKey={60}>
            <Panel style={{ borderTop: 'none' }}>
              <Invoicing onChange={this.onChangeFieldValue} data={billrun} />
              {/*<Allowances onChange={this.onChangeFieldValue} data={billrun} />*/}
            </Panel>
          </Tab>

          <Tab title="Plays" eventKey={70}>
            <Panel style={{ borderTop: 'none' }}>
              <Plays data={plays} />
            </Panel>
          </Tab>

          <Tab title="Activity Types" eventKey={80}>
            <Panel style={{ borderTop: 'none' }}>
              <UsageTypes />
            </Panel>
          </Tab>

          <Tab title="System" eventKey={90}>
            <Panel style={{ borderTop: 'none' }}>
              <System onChange={this.onChangeFieldValue} data={system} />
            </Panel>
          </Tab>

          <Tab title="Plugins" eventKey={100}>
            <Panel style={{ borderTop: 'none' }}>
              <Plugins onChange={this.onChangeFieldValue} data={plugins} />
            </Panel>
          </Tab>

        </Tabs>

        <ActionButtons
          onClickSave={this.onSave}
          hideCancel={true}
          hideSave={[50, 70, 80, 100].includes(activeTab)}
        />

      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  activeTab: tabSelector(state, props, 'settings'),
  settings: state.settings,
  csiOptions: inputProssesorCsiOptionsSelector(state, props),
  taxation: taxationSelector(state, props),
  system: systemSettingsSelector(state, props),
  plays: playsSettingsSelector(state, props),
  rasRatesOptions: state.list.get('available_taxRates'),
});
export default withRouter(connect(mapStateToProps)(Settings));
