import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel } from 'react-bootstrap';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import { EntityRevisionDetails } from '../Entity';
import ExchangeRateDetails from './ExchangeRateDetails';
import {
  getEntityById,
  saveEntity,
  clearEntity,
  setCloneEntity,
  updateEntityField,
  deleteEntityField,
} from '@/actions/entityActions';
import {
  getRevisions,
  clearRevisions,
  clearItems,
} from '@/actions/entityListActions';
import { showSuccess } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import {
  modeSelector,
  itemSelector,
  idSelector,
  revisionsSelector,
} from '@/selectors/entitySelector';
import {
  buildPageTitle,
  getConfig,
  getItemId,
} from '@/common/Util';

const ENTITY_NAME = 'exchangerate';
const COLLECTION = 'exchangerates';
const UNIQUE_FIELD = 'target_currency';

class ExchangeRateSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    revisions: Immutable.List(),
  }

  state = {
    progress: false,
  }

  componentWillMount() {
    this.fetchItem();
  }

  componentDidMount() {
    const { mode } = this.props;
    if (['clone', 'create'].includes(mode)) {
      this.props.dispatch(setPageTitle(buildPageTitle(mode, ENTITY_NAME)));
    }
    this.initDefaultValues();
  }

  componentWillReceiveProps(nextProps) {
    const { item, mode, itemId } = nextProps;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      this.props.dispatch(setPageTitle(buildPageTitle(mode, ENTITY_NAME, item)));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearEntity(ENTITY_NAME));
  }

  initDefaultValues = () => {
    const { mode } = this.props;
    if (mode === 'create') {
      this.onChangeFieldValue(['from'], moment().add(1, 'days').toISOString());
    }
    if (mode === 'clone') {
      // setCloneEntity(reducerKey, configKey); both are the systemItems key 'exchangerate'.
      this.props.dispatch(setCloneEntity(ENTITY_NAME, ENTITY_NAME));
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      this.props.dispatch(getRevisions(COLLECTION, UNIQUE_FIELD, item.get(UNIQUE_FIELD, '')));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getEntityById(ENTITY_NAME, COLLECTION, itemId)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    this.props.dispatch(clearRevisions(COLLECTION, item.get(UNIQUE_FIELD, '')));
  }

  clearItemsList = () => {
    this.props.dispatch(clearItems(COLLECTION));
  }

  onChangeFieldValue = (path, value) => {
    this.props.dispatch(updateEntityField(ENTITY_NAME, path, value));
  }

  onRemoveFieldValue = (path) => {
    this.props.dispatch(deleteEntityField(ENTITY_NAME, path));
  }

  afterItemReceived = (response) => {
    if (response.status) {
      this.initRevisions();
      this.initDefaultValues();
    } else {
      this.handleBack();
    }
  }

  afterSave = (response) => {
    this.setState({ progress: false });
    const { mode } = this.props;
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The exchange rate was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  handleSave = () => {
    const { item, mode } = this.props;
    this.setState({ progress: true });
    this.props.dispatch(saveEntity(COLLECTION, item, mode)).then(this.afterSave);
  }

  handleBack = (itemWasChanged = false) => {
    if (itemWasChanged) {
      this.clearItemsList();
    }
    const listUrl = getConfig(['systemItems', ENTITY_NAME, 'itemsType'], COLLECTION);
    this.props.router.push(`/${listUrl}`);
  }

  render() {
    const { progress } = this.state;
    const { item, mode, revisions } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }
    const allowEdit = mode !== 'view';
    return (
      <div className="ExchangeRateSetup">
        <Panel>
          <EntityRevisionDetails
            itemName={ENTITY_NAME}
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

        <Panel>
          <ExchangeRateDetails
            item={item}
            mode={mode}
            onFieldUpdate={this.onChangeFieldValue}
            onFieldRemove={this.onRemoveFieldValue}
          />
        </Panel>

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
  itemId: idSelector(state, props, ENTITY_NAME),
  item: itemSelector(state, props, ENTITY_NAME),
  mode: modeSelector(state, props, ENTITY_NAME),
  revisions: revisionsSelector(state, props, ENTITY_NAME),
});

export default withRouter(connect(mapStateToProps)(ExchangeRateSetup));
