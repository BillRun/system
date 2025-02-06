import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel } from 'react-bootstrap';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import { EntityRevisionDetails } from '../Entity';
import DiscountDetails from './DiscountDetails';
import {
  buildPageTitle,
  getConfig,
  getItemId,
  getItemDateValue,
} from '@/common/Util';
import { showSuccess } from '@/actions/alertsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import {
  saveDiscount,
  getDiscount,
  clearDiscount,
  updateDiscount,
  deleteDiscountValue,
  setCloneDiscount,
} from '@/actions/discountsActions';
import { clearItems, getRevisions, clearRevisions } from '@/actions/entityListActions';
import { validateEntity } from '@/actions/discountsActions';
import { modeSelector, itemSelector, idSelector, revisionsSelector } from '@/selectors/entitySelector';
import { currencySelector, discountFieldsSelector } from '@/selectors/settingsSelector';


class DiscountSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    fields: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    currency: PropTypes.string,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    currency: '',
    revisions: Immutable.List(),
    fields: Immutable.List(),
  }

  state = {
    errors: Immutable.Map(),
    progress: false,
  }

  componentWillMount() {
    this.fetchItem();
  }

  componentDidMount() {
    const { mode } = this.props;
    if (mode === 'create') {
      const pageTitle = buildPageTitle(mode, 'discount');
      this.props.dispatch(setPageTitle(pageTitle));
    }
    this.initDefaultValues();
  }

  componentWillReceiveProps(nextProps) {
    const { item, mode, itemId } = nextProps;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, 'discount', item);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearDiscount());
  }

  initDefaultValues = () => {
    const { mode, item } = this.props;
    if (mode === 'create' || (mode === 'closeandnew' && getItemDateValue(item, 'from').isBefore(moment()))) {
      const defaultFromValue = moment().add(1, 'days').toISOString();
      this.onChangeFieldValue(['from'], defaultFromValue);
    }
    if (item.get('type', null) === null) {
      this.onChangeFieldValue(['type'], 'monetary');
    }

    if (mode === 'clone') {
      this.props.dispatch(setCloneDiscount());
    }
  }

  initRevisions = () => {
    const { item, revisions } = this.props;
    if (revisions.isEmpty() && getItemId(item, false)) {
      const key = item.get('key', '');
      this.props.dispatch(getRevisions('discounts', 'key', key));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getDiscount(itemId)).then(this.afterItemReceived);
    }
  }

  clearRevisions = () => {
    const { item } = this.props;
    const key = item.get('key', '');
    this.props.dispatch(clearRevisions('discounts', key)); // refetch items list because item was (changed in / added to) list
  }

  clearItemsList = () => {
    const itemsType = getConfig(['systemItems', 'discount', 'itemsType'], '');
    this.props.dispatch(clearItems(itemsType));
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
    this.props.dispatch(deleteDiscountValue(path));
  }

  onChangeFieldValue = (path, value) => {
    const { errors } = this.state;
    const pathString = path.join('.');
    this.setState(() => ({ errors: errors.delete(pathString) }));
    this.props.dispatch(updateDiscount(path, value));
  }

  afterSave = (response) => {
    this.setState({ progress: false });
    const { mode } = this.props;
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The discount was ${action}`));
      this.clearRevisions();
      this.handleBack(true);
    }
  }

  handleSave = () => {
    const { item, mode } = this.props;
    if (this.validate()) {
      this.setState({ progress: true });
      this.props.dispatch(saveDiscount(item, mode)).then(this.afterSave);
    }
  }

  handleBack = (itemWasChanged = false) => {
    const itemsType = getConfig(['systemItems', 'discount', 'itemsType'], '');
    if (itemWasChanged) {
      this.clearItemsList(); // refetch items list because item was (changed in / added to) list
    }
    this.props.router.push(`/${itemsType}`);
  }

  handleSelectTab = (key) => {
    this.setState({ activeTab: key });
  }

  validate = () => {
    const { item, fields, mode} = this.props;
    const errors = this.props.dispatch(validateEntity(item, fields, mode));
    this.setState(() => ({ errors }));
    if (errors.isEmpty()) {
      return true;
    }
    return false;
  }

  render() {
    const { progress, errors } = this.state;
    const { item, mode, revisions, currency } = this.props;
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }
    const allowEdit = mode !== 'view';
    return (
      <div className="discount-setup">
        <Panel>
          <EntityRevisionDetails
            itemName="discount"
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
          <DiscountDetails
            discount={item}
            mode={mode}
            currency={currency}
            onFieldUpdate={this.onChangeFieldValue}
            onFieldRemove={this.onRemoveFieldValue}
            errors={errors}
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
  itemId: idSelector(state, props, 'discount'),
  item: itemSelector(state, props, 'discount'),
  mode: modeSelector(state, props, 'discount'),
  revisions: revisionsSelector(state, props, 'discount'),
  currency: currencySelector(state, props) || undefined,
  fields: discountFieldsSelector(state, props),
});

export default withRouter(connect(mapStateToProps)(DiscountSetup));
