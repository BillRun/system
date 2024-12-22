import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import { Tabs, Tab, Panel } from 'react-bootstrap';
import Immutable from 'immutable';
import moment from 'moment';
import getSymbolFromCurrency from 'currency-symbol-map';
import ChargingPlanDetails from './ChargingPlanDetails';
import ChargingPlanIncludes from './ChargingPlanIncludes';
import { EntityRevisionDetails } from '../Entity';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import { getPrepaidIncludesQuery } from '../../common/ApiQueries';
import {
  buildPageTitle,
  getConfig,
  getItemId,
  getUnitLabel,
} from '@/common/Util';
import {
  getPrepaidGroup,
  clearPlan,
  savePrepaidGroup,
  onPlanFieldUpdate,
  addUsagetInclude,
  onPlanTariffAdd,
  setClonePlan,
} from '@/actions/planActions';
import {
  currencySelector,
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';
import { getList } from '@/actions/listActions';
import { showSuccess } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { clearItems, getRevisions, clearRevisions } from '@/actions/entityListActions';
import { modeSelector, itemSelector, idSelector, tabSelector, revisionsSelector } from '@/selectors/entitySelector';


class ChargingPlanSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    prepaidIncludes: PropTypes.instanceOf(Immutable.List),
    activeTab: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
    currency: PropTypes.string,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
  };

  static defaultProps = {
    item: Immutable.Map(),
    revisions: Immutable.List(),
    prepaidIncludes: Immutable.List(),
    activeTab: 1,
    currency: '',
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
  };

  state = {
    activeTab: parseInt(this.props.activeTab),
  }

  componentWillMount() {
    this.props.dispatch(getList('pp_includes', getPrepaidIncludesQuery()));
    this.fetchItem();
    this.initDefaultValues();
  }

  componentDidMount() {
    const { mode } = this.props;
    if (['clone', 'create'].includes(mode)) {
      const pageTitle = buildPageTitle(mode, 'charging_plan');
      this.props.dispatch(setPageTitle(pageTitle));
    }
  }


  componentWillReceiveProps(nextProps) {
    const { item, mode, itemId, prepaidIncludes } = nextProps;
    const {
      item: oldItem,
      itemId: oldItemId,
      mode: oldMode,
      prepaidIncludes: oldPrepaidIncludes,
    } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, 'charging_plan', item);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone') || !Immutable.is(prepaidIncludes, oldPrepaidIncludes)) {
      this.fetchItem(itemId);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearPlan());
  }

  initDefaultValues = () => {
    const { mode } = this.props;
    if (mode === 'create') {
      const defaultFromValue = moment().add(1, 'days').toISOString();
      this.props.dispatch(onPlanFieldUpdate(['from'], defaultFromValue));
      this.props.dispatch(onPlanFieldUpdate(['connection_type'], 'prepaid'));
      this.props.dispatch(onPlanFieldUpdate(['charging_type'], 'prepaid'));
      this.props.dispatch(onPlanFieldUpdate(['type'], 'charging'));
      this.props.dispatch(onPlanTariffAdd());
      this.props.dispatch(onPlanFieldUpdate(['price', 0, 'price'], 0));
      this.props.dispatch(onPlanFieldUpdate(['upfront'], true));
      this.props.dispatch(onPlanFieldUpdate(['recurrence'], Immutable.Map({ unit: 1, periodicity: 'month' })));
      this.props.dispatch(onPlanFieldUpdate(['operation'], 'inc'));
    }
    if (mode === 'clone') {
      this.props.dispatch(setClonePlan());
      this.handleSelectTab(1);
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      const key = item.get('name', '');
      this.props.dispatch(getRevisions('prepaidgroups', 'name', key));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getPrepaidGroup(itemId)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    const key = item.get('name', '');
    this.props.dispatch(clearRevisions('prepaidgroups', key)); // refetch items list because item was (changed in / added to) list
  }

  clearItemsList = () => {
    this.props.dispatch(clearItems('charging_plans'));
  }

  afterItemReceived = (response) => {
    if (response.status) {
      this.initRevisions();
      this.initDefaultValues();
    } else {
      this.handleBack();
    }
  }

  onChangeField = (path, value) => {
    this.props.dispatch(onPlanFieldUpdate(path, value));
  };

  onSelectPPInclude = (value) => {
    const { prepaidIncludes, item, propertyTypes, usageTypesData, currency } = this.props;
    if (value === '') {
      return;
    }
    const ppInclude = prepaidIncludes.find(pp => pp.get('name') === value);
    const ppIncludesName = ppInclude.get('name');
    const ppIncludesExternalId = ppInclude.get('external_id');
    const unit = ppInclude.get('charging_by_usaget_unit', false);
    const usaget = ppInclude.get('charging_by_usaget', '');
    const unitLabel = unit
      ? `Volume (${getUnitLabel(propertyTypes, usageTypesData, usaget, unit)})`
      : `Cost (${getSymbolFromCurrency(currency)})`;
    const includes = item.get('include', Immutable.List());
    const alreadyExists = includes.find(include => include.get('pp_includes_name', '') === value) !== undefined;
    if (!alreadyExists) {
      this.props.dispatch(addUsagetInclude(ppIncludesName, ppIncludesExternalId, unitLabel));
    }
  };

  onUpdatePeriodField = (index, id, value) => {
    this.props.dispatch(onPlanFieldUpdate(['include', index, 'period', id], value));
  };

  onUpdateIncludeField = (index, id, value) => {
    this.props.dispatch(onPlanFieldUpdate(['include', index, id], value));
  };

  onRemoveChargingPlan = (index) => {
    const { item } = this.props;
    this.props.dispatch(onPlanFieldUpdate(['include'], item.get('include').remove(index)));
  };

  afterSave = (response) => {
    const { mode } = this.props;
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The plan was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  handleSave = () => {
    const { item, mode } = this.props;
    this.props.dispatch(savePrepaidGroup(item, mode)).then(this.afterSave);
  };

  handleBack = (itemWasChanged = false) => {
    if (itemWasChanged) {
      this.clearItemsList(); // refetch items list because item was (changed in / added to) list
    }
    const listUrl = getConfig(['systemItems', 'charging_plan', 'itemsType'], '');
    this.props.router.push(`/${listUrl}`);
  };

  handleSelectTab = (key) => {
    this.setState({ activeTab: key });
  }

  render() {
    const { activeTab } = this.state;
    const { item, prepaidIncludes, mode, revisions } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }

    const prepaidIncludesOptions = prepaidIncludes.map(pp => ({
      label: pp.get('name'),
      value: pp.get('name'),
    })).toJS();
    return (
      <div className="ChargingPlanSetup">

        <Panel>
          <EntityRevisionDetails
            itemName="charging_plan"
            revisions={revisions}
            item={item}
            mode={mode}
            onChangeFrom={this.onChangeField}
            backToList={this.handleBack}
            reLoadItem={this.fetchItem}
            clearRevisions={this.clearRevisions}
            clearList={this.clearItemsList}
          />
        </Panel>

        <Tabs activeKey={activeTab} id="ChargingPlan" animation={false} onSelect={this.handleSelectTab}>

          <Tab title="Details" eventKey={1}>
            <Panel style={{ borderTop: 'none' }}>
              <ChargingPlanDetails
                item={item}
                mode={mode}
                onChangeField={this.onChangeField}
              />
            </Panel>
          </Tab>

          <Tab title="Prepaid Buckets" eventKey={2}>
            <Panel style={{ borderTop: 'none' }}>
              <ChargingPlanIncludes
                mode={mode}
                includes={item.get('include', Immutable.List())}
                prepaidIncludesOptions={prepaidIncludesOptions}
                onSelectPPInclude={this.onSelectPPInclude}
                onUpdatePeriodField={this.onUpdatePeriodField}
                onUpdateField={this.onUpdateIncludeField}
                onRemoveChargingPlan={this.onRemoveChargingPlan}
              />
            </Panel>
          </Tab>
        </Tabs>

        <ActionButtons onClickCancel={this.handleBack} onClickSave={this.handleSave} />

      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  itemId: idSelector(state, props, 'plan'),
  item: itemSelector(state, props, 'plan'),
  mode: modeSelector(state, props, 'plan'),
  activeTab: tabSelector(state, props, 'plan'),
  revisions: revisionsSelector(state, props, 'charging_plan'),
  prepaidIncludes: state.list.get('pp_includes'),
  currency: currencySelector(state, props),
  usageTypesData: usageTypesDataSelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
});
export default withRouter(connect(mapStateToProps)(ChargingPlanSetup));
