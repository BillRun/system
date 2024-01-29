import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Form, FormGroup, Button, ControlLabel, Label } from 'react-bootstrap';
import { RevisionTimeline, ModalWrapper } from '@/components/Elements';
import RevisionList from '../RevisionList';
import Field from '@/components/Field';
import { getItemDateValue, getConfig, getItemId, toImmutableList } from '@/common/Util';
import { getSettings } from '@/actions/settingsActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { entityMinFrom } from '@/selectors/entitySelector';
import { ZoneDate } from '@/components/Elements';
import { minEntityDateSelector } from '@/selectors/settingsSelector';


class EntityRevisionDetails extends Component {

  static propTypes = {
    revisions: PropTypes.instanceOf(Immutable.List),
    item: PropTypes.instanceOf(Immutable.Map),
    minFrom: PropTypes.instanceOf(moment),
    dangerousFrom: PropTypes.instanceOf(moment),
    mode: PropTypes.string,
    onChangeFrom: PropTypes.func,
    backToList: PropTypes.func,
    reLoadItem: PropTypes.func,
    clearRevisions: PropTypes.func,
    clearList: PropTypes.func,
    onActionEdit: PropTypes.func,
    onActionClone: PropTypes.func,
    itemName: PropTypes.string.isRequired,
    revisionItemsInTimeLine: PropTypes.number,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    revisionItemsInTimeLine: 3,
    revisions: Immutable.List(),
    item: Immutable.Map(),
    mode: 'view',
    minFrom: moment(0), // default no limit so it set to 1970
    dangerousFrom: moment(0), // default dangerousFrom - no limit so it set to 1970
    onChangeFrom: () => {},
    backToList: () => {},
    reLoadItem: () => {},
    clearRevisions: () => {},
    clearList: () => {},
  };

  state = {
    showList: false,
  }

  componentDidMount() {
    this.props.dispatch(getSettings(['minimum_entity_start_date', 'system']));
    this.initFormDate();
  }

  componentWillReceiveProps(nextProps) {
    const { item } = nextProps;
    const { item: oldItem } = this.props;
    if (getItemId(item) !== getItemId(oldItem)) {
      this.hideManageRevisions();
      this.initFormDate();
    }
  }

  initFormDate = () => {
    const { mode } = this.props;
    if (['closeandnew'].includes(mode)) {
      const tommorow = moment().add(1, 'day');
      this.onChangeFrom(tommorow);
    }
  }

  showManageRevisions = () => {
    this.setState({ showList: true });
  }

  hideManageRevisions = () => {
    this.setState({ showList: false });
  }

  onDeleteItem = (removedItemId) => {
    const { item, revisions, itemName } = this.props;
    // if screen was with deleted item, go to prev revision or list
    if (getItemId(item) === removedItemId) {
      if (revisions.size > 1) {
        const itemType = getConfig(['systemItems', itemName, 'itemType'], '');
        const itemsType = getConfig(['systemItems', itemName, 'itemsType'], '');
        const idx = revisions.findIndex(revision => getItemId(revision) === getItemId(item));
        const prevItem = (idx !== -1)
          ? revisions.get(idx + 1, revisions.get(idx - 1, ''))
          : revisions.get(0, '');
        if (!this.props.onActionEdit) {
          this.props.router.push(`${itemsType}/${itemType}/${getItemId(prevItem)}`);
        } else {
          this.props.onActionEdit(prevItem);
        }
      } else { // only one revision
        this.props.backToList(true);
      }
    } else {
      // refresh current item because it may effect by deleted revision
      // i.e active_wis_last turn be editable
      this.props.reLoadItem();
    }
    // removed revision may present in list
    // for example: 2 revisions, current + future, after removing current future should be displayed in list
    this.props.clearList();
  }

  onCloseItem = () => {
    this.props.clearRevisions();
    this.props.clearList();
    this.props.reLoadItem();
  }

  renderVerisionList = () => {
    const { itemName, revisions, item } = this.props;
    const { showList } = this.state;
    const revisionBy = toImmutableList(getConfig(['systemItems', itemName, 'uniqueField'], '')).get(0, '');
    const title = `${item.get(revisionBy, '')} - Revision History`;
    return (
      <ModalWrapper title={title} show={showList} onCancel={this.hideManageRevisions} onHide={this.hideManageRevisions} labelCancel="Close">
        <RevisionList
          items={revisions}
          itemName={itemName}
          onSelectItem={this.hideManageRevisions}
          onDeleteItem={this.onDeleteItem}
          onCloseItem={this.onCloseItem}
          onActionEdit={this.props.onActionEdit}
          onActionClone={this.props.onActionClone}
        />
      </ModalWrapper>
    );
  }

  onChangeFrom = (value) => {
    const { item, dangerousFrom } = this.props;
    if (value) {
      this.props.onChangeFrom(['from'], value.format('YYYY-MM-DD'));
      if (value.isBefore(dangerousFrom) && !value.isSame(getItemDateValue(item, 'originalValue'), 'day')) {
        const from = getItemDateValue(item, 'from');
        this.confirmSelectedDate(value, from);
      }
    }
  }

  filterLegalFromDate = (date) => {
    const { minFrom } = this.props;
    return date.isSameOrAfter(minFrom, 'day');
  }

  renderRevisionsBlock = () => {
    const { item, revisions, revisionItemsInTimeLine, mode, itemName } = this.props;
    if (['clone', 'create'].includes(mode)) {
      return null;
    }

    return (
      <div className="inline pull-right">
        <div className="inline mr10">
          <RevisionTimeline
            revisions={revisions}
            item={item}
            itemName={itemName}
            size={revisionItemsInTimeLine}
          />
        </div>
        <div className="inline">
          <Button bsStyle="link" className="pull-right" style={{ padding: '0 10px 15px 10px' }} onClick={this.showManageRevisions}>
            Manage Revisions
          </Button>
        </div>
      </div>
    );
  }

  renderDateViewBlock = () => {
    const { item } = this.props;
    const from = getItemDateValue(item, 'originalValue');
    const to = getItemDateValue(item, 'to').subtract(1,'seconds');
    const format = getConfig('dateFormat', 'DD/MM/YYYY');
    return (
      <div className="inline" style={{ width: 190, padding: 0, margin: '9px 10px 0 10px' }}>
        <p style={{ lineHeight: '32px' }}><ZoneDate value={from} format={format} /> - <ZoneDate value={to} format={format} /></p>
      </div>
    );
  }

  onChangeToggleFrom = (newDate) => {
    const { item } = this.props;
    if (moment.isMoment(newDate) && newDate.isValid()) {
      const tommorow = moment().add(1, 'day');
      const from = getItemDateValue(item, 'from');
      const selectedValue = newDate.isSame(from, 'day') ? tommorow : newDate;
      this.onChangeFrom(selectedValue);
    } else {
      const originFrom = getItemDateValue(item, 'originalValue');
      this.onChangeFrom(originFrom);
    }
  }

  confirmSelectedDate = (newDate, oldDate) => {
    const dateString = newDate.format(getConfig('dateFormat', 'DD/MM/YYYY'));
    const confirm = {
      message: 'Billing cycle for the date you have chosen is already over.',
      children: `Are you sure you want to use ${dateString}?`,
      onOk: () => { this.onChangeFrom(newDate); },
      onCancel: () => { this.onChangeFrom(oldDate); },
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  dayDateClass = (day) => {
    const dayConvert = moment(day);
    const { item, minFrom, dangerousFrom } = this.props;
    const originFrom = getItemDateValue(item, 'originalValue');
    if (dayConvert.isBetween(minFrom, dangerousFrom) && !dayConvert.isSame(originFrom)) {
      return 'danger-red';
    }
    return undefined;
  }

  renderDateSelectBlock = () => {
    const { item, mode, minFrom } = this.props;
    const editable = (['closeandnew', 'clone', 'create'].includes(mode));
    const from = getItemDateValue(item, 'from');
    const originFrom = getItemDateValue(item, 'originalValue');
    const highlightDates = (mode === 'create') ? [moment()] : [originFrom, moment()];
    const inputProps = {
      fieldType: 'date',
      dateFormat: getConfig('dateFormat', 'dd/MM/yyyy'),
      isClearable: false,
      placeholder: 'Select Date...',
      minDate: minFrom,
      highlightDates,
      dayClassName: this.dayDateClass,
      style: {display: 'grid'},
    };
    if (['closeandnew'].includes(mode)) {
      return (
        <div className="inline" style={{ width: 220, padding: 0, margin: '7px 7px 0' }}>
          <Field
            fieldType="toggeledInput"
            value={from}
            onChange={this.onChangeToggleFrom}
            label="Change From"
            editable={editable}
            inputProps={inputProps}
            disabledDisplayValue={originFrom}
            disabledValue={originFrom}
            compare={(a, b) => a.isSame(b, 'day')}
          />
        </div>
      );
    }
    // create / clone
    return (
      <div className="inline" style={{ width: 220, padding: 0, margin: 7 }}>
        <Form horizontal style={{ marginBottom: 0 }}>
          <FormGroup style={{ marginBottom: 0 }}>
            <div className="inline" style={{ verticalAlign: 'top', marginRight: 15 }}>
              <ControlLabel>From</ControlLabel>
            </div>
            <div className="inline" style={{ padding: 0, width: 200 }}>
              <Field
                fieldType="date"
                value={from}
                onChange={this.onChangeFrom}
                editable={editable}
                dateFormat={getConfig('dateFormat', 'DD/MM/YYYY')}
                isClearable={false}
                placeholder="Select Date..."
                minDate={minFrom}
                highlightDates={highlightDates}
                dayClassName={this.dayDateClass}
              />
            </div>
          </FormGroup>
        </Form>
      </div>
    );
  }

  renderTitle = () => {
    const { mode } = this.props;
    const title = (['clone', 'create'].includes(mode)) ? ' ' : 'Revisions History';
    return (
      <div className="inline" style={{ verticalAlign: 'top', marginTop: 16, width: 110 }}>
        <p><small>{title}</small></p>
      </div>
    );
  }

  renderMessage = () => {
    const { mode, item } = this.props;
    if (mode === 'view' && item.getIn(['revision_info', 'status'], '') === 'active' && !item.getIn(['revision_info', 'is_last'], true)) {
      return (
        <Label bsStyle="warning">You cannot edit the current revision because a future revision exists.</Label>
      );
    }
    return null;
  }

  render() {
    const { mode, item } = this.props;
    const earlyExpiration = item.getIn(['revision_info', 'early_expiration'], false);
    return (
      <div className="entity-revision-edit">
        <div>
          { this.renderTitle() }
          { (mode === 'view' || earlyExpiration)
            ? this.renderDateViewBlock()
            : this.renderDateSelectBlock()
          }
          { this.renderRevisionsBlock() }
          { this.renderVerisionList() }
        </div>
        { this.renderMessage() }
      </div>
    );
  }

}


const mapStateToProps = (state, props) => ({
  minFrom: entityMinFrom(state, props),
  dangerousFrom: minEntityDateSelector(state, props),
  timezone: state.settings.getIn([ 'billrun','timezone'])
});

export default withRouter(connect(mapStateToProps)(EntityRevisionDetails));
