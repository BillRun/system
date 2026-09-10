import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import withRouter from '@/common/withRouter';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel } from '@/common/BootstrapCompat';
import ProductsGroupDetails, { KEY_EXISTS_ERROR } from './ProductsGroupDetails';
import { EntityRevisionDetails } from '../../Entity';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import {
  buildPageTitle,
  getConfig,
  getItemId,
  getItemDateValue,
  collectExistingGroupNames,
} from '@/common/Util';
import { getAllGroup } from '@/actions/planActions';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import {
  clearProductsGroup,
  setCloneProductsGroup,
  getProductsGroupRevisions,
  getProductsGroup,
  clearProductsGroupRevisions,
  clearProductsGroupList,
  deleteProductsGroupValue,
  updateProductsGroup,
  saveProductsGroup,
} from '@/actions/productsGroupsActions';
import { modeSelector, itemSelector, idSelector, revisionsSelector } from '@/selectors/entitySelector';


class ProductsGroupSetup extends Component {

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
    // the group names already taken in the balance groups namespace: a products group
    // key is a balance group name like a service counter or a plan group name
    existingGroupsNames: Immutable.List(),
  }

  componentDidMount() {
    const { mode } = this.props;
    this.fetchItem();
    this.fetchExistingGroupsNames();
    if (mode === 'create') {
      const pageTitle = buildPageTitle(mode, 'products_group');
      this.props.dispatch(setPageTitle(pageTitle));
    }
    this.initDefaultValues();
  }

  componentDidUpdate(prevProps, prevState) { // eslint-disable-line no-unused-vars
    const { item, mode, itemId } = this.props;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = prevProps;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, 'products_group', item);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearProductsGroup());
    this.props.dispatch(setPageTitle(''));
  }

  initDefaultValues = () => {
    const { mode, item } = this.props;
    if (mode === 'create' || (mode === 'closeandnew' && getItemDateValue(item, 'from').isBefore(moment()))) {
      const defaultFromValue = moment().add(1, 'days').toISOString();
      this.onChangeFieldValue(['from'], defaultFromValue);
    }

    if (mode === 'clone') {
      this.props.dispatch(setCloneProductsGroup());
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      const key = item.get('key', '');
      this.props.dispatch(getProductsGroupRevisions(key));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getProductsGroup(itemId)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    const key = item.get('key', '');
    this.props.dispatch(clearProductsGroupRevisions(key)); // refetch items list because item was (changed in / added to) list
  }

  clearItemsList = () => {
    this.props.dispatch(clearProductsGroupList());
  }

  afterItemReceived = (response) => {
    if (response.status) {
      this.initRevisions();
      this.initDefaultValues();
    } else {
      this.handleBack();
    }
  }

  onRemoveFieldValue = (path) => {
    this.props.dispatch(deleteProductsGroupValue(path));
  }

  onChangeFieldValue = (path, value) => {
    this.props.dispatch(updateProductsGroup(path, value));
  }

  afterSave = (response) => {
    this.setState({ progress: false });
    const { mode } = this.props;
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The products group was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  fetchExistingGroupsNames = () => {
    getAllGroup().then((responses) => {
      this.setState({ existingGroupsNames: Immutable.List(collectExistingGroupNames(responses.data)) });
    });
  }

  handleSave = () => {
    const { item, mode } = this.props;
    const { existingGroupsNames } = this.state;
    const itemToSave = item.update('usage_types', Immutable.List(), usageTypes => usageTypes.filter(usageType => usageType !== ''));
    if (itemToSave.get('rates', Immutable.List()).isEmpty()) {
      this.props.dispatch(showDanger('Please select at least one product'));
      return;
    }
    if (['clone', 'create'].includes(mode) && existingGroupsNames.includes(itemToSave.get('key', ''))) {
      this.props.dispatch(showDanger(KEY_EXISTS_ERROR));
      return;
    }
    this.setState({ progress: true });
    this.props.dispatch(saveProductsGroup(itemToSave, mode)).then(this.afterSave);
  }

  handleBack = (itemWasChanged = false) => {
    const itemsType = getConfig(['systemItems', 'products_group', 'itemsType'], '');
    if (itemWasChanged) {
      this.clearItemsList(); // refetch items list because item was (changed in / added to) list
    }
    this.props.router.push(`/${itemsType}`);
  }

  render() {
    const { progress, existingGroupsNames } = this.state;
    const { item, mode, revisions } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }

    const allowEdit = mode !== 'view';
    return (
      <div className="products-group-setup">
        <Panel>
          <EntityRevisionDetails
            itemName="products_group"
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
          <ProductsGroupDetails
            item={item}
            mode={mode}
            existingGroupsNames={existingGroupsNames}
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
  itemId: idSelector(state, props, 'products_group'),
  item: itemSelector(state, props, 'products_group'),
  mode: modeSelector(state, props, 'products_group'),
  revisions: revisionsSelector(state, props, 'products_group'),
});

export default withRouter(connect(mapStateToProps)(ProductsGroupSetup));
