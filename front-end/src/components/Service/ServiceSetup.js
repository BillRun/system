import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel, Tabs, Tab } from 'react-bootstrap';
import ServiceDetails from './ServiceDetails';
import PlanIncludesTab from '../Plan/PlanIncludesTab';
import PlanProductsPriceTab from '../Plan/PlanProductsPriceTab';
import { EntityTaxDetails } from '@/components/Tax';
import { EntityRevisionDetails } from '../Entity';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import {
  buildPageTitle,
  getConfig,
  getItemId,
} from '@/common/Util';
import {
  addGroup,
  removeGroup,
  getService,
  clearService,
  updateService,
  deleteServiceField,
  saveService,
  setCloneService,
} from '@/actions/serviceActions';
import { showSuccess } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { clearItems, getRevisions, clearRevisions } from '@/actions/entityListActions';
import { modeSelector, itemSelector, idSelector, tabSelector, revisionsSelector, itemSourceSelector } from '@/selectors/entitySelector';

class ServiceSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    sourceItem: PropTypes.instanceOf(Immutable.Map),
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
    sourceItem: Immutable.Map(),
    revisions: Immutable.List(),
    activeTab: 1,
  };

  static entityName = 'service';

  state = {
    activeTab: parseInt(this.props.activeTab),
    progress: false,
  };

  componentWillMount() {
    this.fetchItem();
  }

  componentDidMount() {
    const { mode } = this.props;
    if (['clone', 'create'].includes(mode)) {
      const pageTitle = buildPageTitle(mode, 'service');
      this.props.dispatch(setPageTitle(pageTitle));
    }
    this.initDefaultValues();
  }

  componentWillReceiveProps(nextProps) {
    const { item, mode, itemId } = nextProps;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, 'service', item);
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
    this.props.dispatch(clearService());
  }

  initDefaultValues = () => {
    const { mode, item } = this.props;
    if (mode === 'create') {
      const defaultFromValue = moment().add(1, 'days').toISOString();
      this.props.dispatch(updateService(['from'], defaultFromValue));
    }
    if (mode === 'clone') {
      this.props.dispatch(setCloneService());
      this.handleSelectTab(1);
    }
    if (item.get('prorated', null) === null) {
      this.props.dispatch(updateService(['prorated'], true));
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      const key = item.get('name', '');
      this.props.dispatch(getRevisions('services', 'name', key));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getService(itemId, true)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    const key = item.get('name', '');
    this.props.dispatch(clearRevisions('services', key));// refetch items list because item was (changed in / added to) list
  }

  clearItemsList = () => {
    this.props.dispatch(clearItems('services'));
  }

  afterItemReceived = (response) => {
    if (response.status) {
      this.initRevisions();
      this.initDefaultValues();
    } else {
      this.handleBack();
    }
  }

  onGroupAdd = (groupName, usages, unit, value, shared, pooled, quantityAffected, products) => {
    this.props.dispatch(addGroup(groupName, usages, unit, value, shared, pooled, quantityAffected, products));
  }

  onGroupRemove = (groupName) => {
    this.props.dispatch(removeGroup(groupName));
  }

  onUpdateItem = (path, value) => {
    this.props.dispatch(updateService(path, value));
  }

  onRemoveFieldValue = (path) => {
    this.props.dispatch(deleteServiceField(path));
  }

  afterSave = (response) => {
    const { mode } = this.props;
    this.setState({ progress: false });
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The service was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  handleSelectTab = (activeTab) => {
    this.setState({ activeTab });
  }

  handleBack = (itemWasChanged = false) => {
    if (itemWasChanged) {
      this.clearItemsList(); // refetch items list because item was (changed in / added to) list
    }
    const listUrl = getConfig(['systemItems', ServiceSetup.entityName, 'itemsType'], '');
    this.props.router.push(`/${listUrl}`);
  }

  handleSave = () => {
    const { item, mode } = this.props;
    this.setState({ progress: true });
    this.props.dispatch(saveService(item, mode)).then(this.afterSave);
  }

  render() {
    const { progress, activeTab } = this.state;
    const { item, sourceItem, mode, revisions } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }

    const allowEdit = mode !== 'view';
    const includeGroups = item.getIn(['include', 'groups'], Immutable.Map());
    const planRates = item.get('rates', Immutable.Map());
    const plays = item.get('play', Immutable.List());
    return (
      <div className="ServiceSetup">
        <Panel>
          <EntityRevisionDetails
            itemName={ServiceSetup.entityName}
            revisions={revisions}
            item={item}
            mode={mode}
            onChangeFrom={this.onUpdateItem}
            backToList={this.handleBack}
            reLoadItem={this.fetchItem}
            clearRevisions={this.clearRevisions}
            clearList={this.clearItemsList}
          />
        </Panel>

        <Tabs activeKey={activeTab} animation={false} id="ServiceTab" onSelect={this.handleSelectTab}>

          <Tab title="Details" eventKey={1}>
            <Panel style={{ borderTop: 'none' }}>
              <ServiceDetails
                item={item}
                sourceItem={sourceItem}
                mode={mode}
                updateItem={this.onUpdateItem}
                onFieldRemove={this.onRemoveFieldValue}
              />
            </Panel>
          </Tab>

          <Tab title="Override Product Price" eventKey={2}>
            <Panel style={{ borderTop: 'none' }}>
              <PlanProductsPriceTab
                itemName={ServiceSetup.entityName}
                mode={mode}
                planRates={planRates}
                onChangeFieldValue={this.onUpdateItem}
                plays={plays.join(',')}
              />
            </Panel>
          </Tab>

          <Tab title="Service Includes" eventKey={3}>
            <Panel style={{ borderTop: 'none' }}>
              <PlanIncludesTab
                includeGroups={includeGroups}
                onChangeFieldValue={this.onUpdateItem}
                onGroupAdd={this.onGroupAdd}
                onGroupRemove={this.onGroupRemove}
                mode={mode}
                type={ServiceSetup.entityName}
                plays={plays.join(',')}
              />
            </Panel>
          </Tab>

          <Tab title="Tax" eventKey={4}>
            <Panel style={{ borderTop: 'none' }}>
              <EntityTaxDetails
                tax={item.get('tax')}
                mode={mode}
                itemName={ServiceSetup.entityName}
                onFieldUpdate={this.onUpdateItem}
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
  itemId: idSelector(state, props, 'service'),
  sourceItem: itemSourceSelector(state, props, 'service'),
  item: itemSelector(state, props, 'service'),
  mode: modeSelector(state, props, 'service'),
  activeTab: tabSelector(state, props, 'service'),
  revisions: revisionsSelector(state, props, 'service'),
});

export default withRouter(connect(mapStateToProps)(ServiceSetup));
