import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import { Panel, Tabs, Tab } from 'react-bootstrap';
import Immutable from 'immutable';
import moment from 'moment';
import PlanTab from './PlanTab';
import { EntityTaxDetails } from '@/components/Tax';
import { EntityRevisionDetails } from '../Entity';
import PlanProductsPriceTab from './PlanProductsPriceTab';
import PlanIncludesTab from './PlanIncludesTab';
import PlanIncludedServicesTab from './PlanIncludedServicesTab';
import { LoadingItemPlaceholder, ActionButtons } from '@/components/Elements';
import {
  getPlan,
  savePlan,
  clearPlan,
  onPlanFieldUpdate,
  onPlanFieldRemove,
  onPlanCycleUpdate,
  onPlanTariffAdd,
  onPlanTariffRemove,
  onGroupAdd,
  onGroupRemove,
  setClonePlan,
} from '@/actions/planActions';
import {
  buildPageTitle,
  getConfig,
  getItemId,
} from '@/common/Util';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { clearItems, getRevisions, clearRevisions } from '@/actions/entityListActions';
import { showSuccess } from '@/actions/alertsActions';
import { modeSelector, itemSelector, idSelector, tabSelector, revisionsSelector, itemSourceSelector } from '@/selectors/entitySelector';


class PlanSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    originalPlan: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    activeTab: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    originalPlan: Immutable.Map(),
    revisions: Immutable.List(),
    activeTab: 1,
  };

  static entityName = 'plan';

  state = {
    activeTab: parseInt(this.props.activeTab),
    progress: false,
  }

  componentWillMount() {
    this.fetchItem();
  }

  componentDidMount() {
    const { mode } = this.props;
    if (['clone', 'create'].includes(mode)) {
      const pageTitle = buildPageTitle(mode, PlanSetup.entityName);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    this.initDefaultValues();
  }


  componentWillReceiveProps(nextProps) {
    const { item, itemId, mode } = nextProps;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, PlanSetup.entityName, item);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  shouldComponentUpdate(nextProps, nextState) {
    return !Immutable.is(this.props.item, nextState.item)
      || !Immutable.is(this.props.revisions, nextState.revisions)
      || this.props.activeTab !== nextProps.activeTab
      || this.props.itemId !== nextProps.itemId
      || this.props.mode !== nextProps.mode;
  }

  componentWillUnmount() {
    this.props.dispatch(clearPlan());
  }

  initDefaultValues = () => {
    const { mode, item } = this.props;
    if (mode === 'create') {
      const defaultFromValue = moment().add(1, 'days').toISOString();
      this.props.dispatch(onPlanFieldUpdate(['from'], defaultFromValue));
      this.props.dispatch(onPlanFieldUpdate(['connection_type'], 'postpaid'));
    }
    if (mode === 'clone') {
      this.props.dispatch(setClonePlan());
      this.handleSelectTab(1);
    }
    if (item.get('prorated_start', null) === null) {
      this.props.dispatch(onPlanFieldUpdate(['prorated_start'], true));
    }
    if (item.get('prorated_end', null) === null) {
      this.props.dispatch(onPlanFieldUpdate(['prorated_end'], true));
    }
    if (item.get('prorated_termination', null) === null) {
      this.props.dispatch(onPlanFieldUpdate(['prorated_termination'], true));
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      const key = item.get('name', '');
      this.props.dispatch(getRevisions('plans', 'name', key));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getPlan(itemId, true)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    const key = item.get('name', '');
    this.props.dispatch(clearRevisions('plans', key)); // refetch items list because item was (changed in / added to) list
  }

  clearItemsList = () => {
    this.props.dispatch(clearItems('plans'));
  }

  afterItemReceived = (response) => {
    if (response.status) {
      this.initRevisions();
      this.initDefaultValues();
    } else {
      this.handleBack();
    }
  }

  onChangeFieldValue = (path, value) => {
    this.props.dispatch(onPlanFieldUpdate(path, value));
  }

  onRemoveFieldValue = (path) => {
    this.props.dispatch(onPlanFieldRemove(path));
  }

  onDeleteField = (path, value) => {
    this.props.dispatch(onPlanFieldUpdate(path, value));
  }

  onPlanCycleUpdate = (index, value) => {
    this.props.dispatch(onPlanCycleUpdate(index, value));
  }

  onPlanTariffAdd = (trail) => {
    this.props.dispatch(onPlanTariffAdd(trail));
  }

  onPlanTariffRemove = (index) => {
    this.props.dispatch(onPlanTariffRemove(index));
  }

  onGroupAdd = (groupName, usages, unit, value, shared, pooled, quantityAffected, products) => {
    this.props.dispatch(onGroupAdd(groupName, usages, unit, value, shared, pooled, quantityAffected, products));
  }

  onGroupRemove = (groupName) => {
    this.props.dispatch(onGroupRemove(groupName));
  }

  handleSave = () => {
    const { item, mode } = this.props;
    this.setState({ progress: true });
    this.props.dispatch(savePlan(item, mode)).then(this.afterSave);
  }

  afterSave = (response) => {
    const { mode } = this.props;
    this.setState({ progress: false });
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The plan was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  handleBack = (itemWasChanged = false) => {
    if (itemWasChanged) {
      this.clearItemsList(); // refetch items list because item was (changed in / added to) list
    }
    const listUrl = getConfig(['systemItems', PlanSetup.entityName, 'itemsType'], '');
    this.props.router.push(`/${listUrl}`);
  }

  handleSelectTab = (key) => {
    this.setState({ activeTab: key });
  }

  render() {
    const { progress, activeTab } = this.state;
    const { item, mode, revisions, originalPlan } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }

    const allowEdit = mode !== 'view';
    const planRates = item.get('rates', Immutable.Map());
    const includedServices = item.getIn(['include', 'services'], Immutable.List());
    const includeGroups = item.getIn(['include', 'groups'], Immutable.Map());
    const plays = item.get('play', Immutable.List());
    return (
      <div className="PlanSetup">

        <Panel>
          <EntityRevisionDetails
            itemName="plan"
            revisions={revisions}
            item={item}
            mode={mode}
            onChangeFrom={this.onChangeFieldValue}
            backToList={this.handleBack}
            reLoadItem={this.fetchItem}
            clearRevisions={this.clearRevisions}
            clearList={this.clearItemsList}
          />
        </Panel>

        <Tabs activeKey={activeTab} animation={false} id="PlanTab" onSelect={this.handleSelectTab}>
          <Tab title="Details" eventKey={1}>
            <Panel style={{ borderTop: 'none' }}>
              <PlanTab
                mode={mode}
                plan={item}
                originalPlan={originalPlan}
                onChangeFieldValue={this.onChangeFieldValue}
                onRemoveField={this.onRemoveFieldValue}
                onPlanCycleUpdate={this.onPlanCycleUpdate}
                onPlanTariffAdd={this.onPlanTariffAdd}
                onPlanTariffRemove={this.onPlanTariffRemove}
              />
            </Panel>
          </Tab>

          <Tab title="Override Product Price" eventKey={2}>
            <Panel style={{ borderTop: 'none' }}>
              <PlanProductsPriceTab
                itemName="plan"
                mode={mode}
                planRates={planRates}
                onChangeFieldValue={this.onChangeFieldValue}
                plays={plays.join(',')}
              />
            </Panel>
          </Tab>

          <Tab title="Included Products" eventKey={3}>
            <Panel style={{ borderTop: 'none' }}>
              <PlanIncludesTab
                mode={mode}
                plays={plays.join(',')}
                includeGroups={includeGroups}
                onChangeFieldValue={this.onChangeFieldValue}
                onGroupAdd={this.onGroupAdd}
                onGroupRemove={this.onGroupRemove}
              />
            </Panel>
          </Tab>

          <Tab title="Included Services" eventKey={4}>
            <Panel style={{ borderTop: 'none' }}>
              <PlanIncludedServicesTab
                mode={mode}
                plays={plays}
                includedServices={includedServices}
                onChangeFieldValue={this.onChangeFieldValue}
              />
            </Panel>
          </Tab>

          <Tab title="Tax" eventKey={5}>
            <Panel style={{ borderTop: 'none' }}>
              <EntityTaxDetails
                tax={item.get('tax')}
                mode={mode}
                itemName={PlanSetup.entityName}
                onFieldUpdate={this.onChangeFieldValue}
                onFieldRemove={this.onRemoveFieldValue}
              />
            </Panel>
          </Tab>

        </Tabs>
        <ActionButtons
          onClickCancel={this.handleBack}
          onClickSave={this.handleSave}
          hideSave={!allowEdit}
          cancelLabel={allowEdit ? undefined : 'Back'}
          progress={progress}
        />
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  itemId: idSelector(state, props, PlanSetup.entityName),
  item: itemSelector(state, props, PlanSetup.entityName),
  mode: modeSelector(state, props, PlanSetup.entityName),
  activeTab: tabSelector(state, props, PlanSetup.entityName),
  revisions: revisionsSelector(state, props, PlanSetup.entityName),
  originalPlan: itemSourceSelector(state, props, PlanSetup.entityName),
});
export default withRouter(connect(mapStateToProps)(PlanSetup));
