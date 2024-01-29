import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel } from 'react-bootstrap';
import TaxDetails from './TaxDetails';
import { EntityRevisionDetails } from '../../Entity';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import {
  buildPageTitle,
  getConfig,
  getItemId,
  getItemDateValue,
} from '@/common/Util';
import { showSuccess } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import {
  clearTax,
  setCloneTax,
  getTaxRevisions,
  getTax,
  clearTaxRevisions,
  clearTaxList,
  deleteTaxValue,
  updateTax,
  saveTax,
} from '@/actions/taxesActions';
import { modeSelector, itemSelector, idSelector, revisionsSelector } from '@/selectors/entitySelector';


class TaxSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    deletePathOnEmptyValue: PropTypes.array,
    deletePathOnNullValue: PropTypes.array,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    revisions: Immutable.List(),
    deletePathOnEmptyValue: [],
    deletePathOnNullValue: [],
  }

  state = {
    progress: false,
  }

  componentDidMount() {
    const { mode } = this.props;
    this.fetchItem();
    if (mode === 'create') {
      const pageTitle = buildPageTitle(mode, 'tax');
      this.props.dispatch(setPageTitle(pageTitle));
    }
    this.initDefaultValues();
  }

  componentWillReceiveProps(nextProps) {
    const { item, mode, itemId } = nextProps;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, 'tax', item);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearTax());
  }

  initDefaultValues = () => {
    const { mode, item } = this.props;
    if (mode === 'create' || (mode === 'closeandnew' && getItemDateValue(item, 'from').isBefore(moment()))) {
      const defaultFromValue = moment().add(1, 'days').toISOString();
      this.onChangeFieldValue(['from'], defaultFromValue);
    }

    if (mode === 'clone') {
      this.props.dispatch(setCloneTax());
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      const key = item.get('key', '');
      this.props.dispatch(getTaxRevisions(key));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getTax(itemId)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    const key = item.get('key', '');
    this.props.dispatch(clearTaxRevisions(key)); // refetch items list because item was (changed in / added to) list
  }

  clearItemsList = () => {
    const itemsType = getConfig(['systemItems', 'tax', 'itemsType'], '');
    this.props.dispatch(clearTaxList(itemsType));
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
    this.props.dispatch(deleteTaxValue(path));
  }

  onChangeFieldValue = (path, value) => {
    const { deletePathOnEmptyValue, deletePathOnNullValue } = this.props;
    const stringPath = Array.isArray(path) ? path.join('.') : path;
    if (value === '' && deletePathOnEmptyValue.includes(stringPath)) {
      this.props.dispatch(deleteTaxValue(path));
    } else if (value === null && deletePathOnNullValue.includes(stringPath)) {
      this.props.dispatch(deleteTaxValue(path));
    } else {
      this.props.dispatch(updateTax(path, value));
    }
  }

  afterSave = (response) => {
    this.setState({ progress: false });
    const { mode } = this.props;
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The tax was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  handleSave = () => {
    const { item, mode } = this.props;
    this.setState({ progress: true });
    this.props.dispatch(saveTax(item, mode)).then(this.afterSave);
  }

  handleBack = (itemWasChanged = false) => {
    const itemsType = getConfig(['systemItems', 'tax', 'itemsType'], '');
    if (itemWasChanged) {
      this.clearItemsList(); // refetch items list because item was (changed in / added to) list
    }
    this.props.router.push(`/${itemsType}`);
  }

  handleSelectTab = (key) => {
    this.setState({ activeTab: key });
  }

  render() {
    const { progress } = this.state;
    const { item, mode, revisions } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }

    const allowEdit = mode !== 'view';
    return (
      <div className="tax-setup">
        <Panel>
          <EntityRevisionDetails
            itemName="tax"
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
          <TaxDetails
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
  itemId: idSelector(state, props, 'tax'),
  item: itemSelector(state, props, 'tax'),
  mode: modeSelector(state, props, 'tax'),
  revisions: revisionsSelector(state, props, 'tax'),
});

export default withRouter(connect(mapStateToProps)(TaxSetup));
