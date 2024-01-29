import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { Button } from 'react-bootstrap';
import Field from '@/components/Field';
import { ConfirmModal } from '@/components/Elements';
import { getItemDateValue, getItemId, getConfig, isItemClosed } from '@/common/Util';
import { closeEntity } from '@/actions/entityActions';
import { showSuccess } from '@/actions/alertsActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { entityMinFrom } from '@/selectors/entitySelector';
import { getSettings } from '@/actions/settingsActions';
import { minEntityDateSelector } from '@/selectors/settingsSelector';


class CloseActionBox extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    itemName: PropTypes.string.isRequired,
    onCloseItem: PropTypes.func,
    minDate: PropTypes.instanceOf(moment),
    dangerousFrom: PropTypes.instanceOf(moment),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    minDate: moment(0), // default no date so it set to 1970
    dangerousFrom: moment(0), // default no date so it set to 1970
    onCloseItem: () => {},
  }

  state = {
    showConfirmClose: false,
    showCloseDetails: false,
    closeDate: isItemClosed(this.props.item) ? getItemDateValue(this.props.item, 'to', null) : null,
  }

  componentDidMount() {
    this.props.dispatch(getSettings(['minimum_entity_start_date', 'system']));
  }

  onClickCloseConfirm = () => {
    this.setState({
      showConfirmClose: false,
      showCloseDetails: false,
      closeDate: null,
    });
  }

  onClickCloseOk = () => {
    const { closeDate } = this.state;
    const { itemName, item } = this.props;
    const collection = getConfig(['systemItems', itemName, 'collection'], '');
    if (item) {
      const itemToClose = item.set('to', closeDate.toISOString());
      this.props.dispatch(closeEntity(collection, itemToClose)).then(this.afterClose);
    }
  }

  afterClose = (response) => {
    const { closeDate } = this.state;
    const { item } = this.props;
    const closeDateFormated = closeDate.format(getConfig('dateFormat', 'DD/MM/YYYY'));
    if (response.status) {
      this.props.dispatch(showSuccess(`Close date was set to ${closeDateFormated}`));
      const closedItemId = getItemId(item, '');
      this.onClickCloseConfirm();
      this.props.onCloseItem(closedItemId);
    }
  }

  toggleCloseAction = () => {
    const { showCloseDetails } = this.state;
    this.setState({ showCloseDetails: !showCloseDetails });
  }

  toggleCloseConfirm = () => {
    const { showConfirmClose } = this.state;
    this.setState({ showConfirmClose: !showConfirmClose });
  }

  onChangeFrom = (date) => {
    const { closeDate } = this.state;
    const { dangerousFrom } = this.props;
    if (date && date.isBefore(dangerousFrom)) {
      this.confirmSelectedDate(date, closeDate);
    } else {
      this.setState({ closeDate: date });
    }
  }

  confirmSelectedDate = (newDate, oldDate) => {
    const dateString = newDate.format(getConfig('dateFormat', 'DD/MM/YYYY'));
    const confirm = {
      message: 'Billing cycle for the date you have chosen is already over.',
      children: `Are you sure you want to use ${dateString}?`,
      onOk: () => { this.setState({ closeDate: newDate }); },
      onCancel: () => { this.setState({ closeDate: oldDate }); },
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  dayDateClass = (day) => {
    const { minDate, dangerousFrom } = this.props;
    if (day.isBetween(minDate, dangerousFrom)) {
      return 'danger-red';
    }
    return undefined;
  }

  renderDateFromfields = () => {
    const { item, minDate } = this.props;
    const { closeDate } = this.state;
    const btnStyle = { marginLeft: 5, verticalAlign: 'bottom' };
    const itemToDate = getItemDateValue(item, 'to', null);
    const disableSubmit = closeDate === null
      || (itemToDate && closeDate && itemToDate.isSame(closeDate));
    const highlightDates = [moment()];
    return (
      <div className="inline">
        <div className="inline">
          <Field
            fieldType="date"
            value={closeDate}
            onChange={this.onChangeFrom}
            isClearable={true}
            placeholderText="Select Date..."
            minDate={minDate.clone().add(1, 'days')}
            highlightDates={highlightDates}
            dayClassName={this.dayDateClass}
          />
        </div>
        <Button onClick={this.toggleCloseConfirm} style={btnStyle} disabled={disableSubmit} bsStyle="primary" className="inline">
          OK
        </Button>
      </div>
    );
  }

  render() {
    const { item, itemName } = this.props;
    const { showCloseDetails, showConfirmClose } = this.state;
    const closeConfirmMessage = 'Are you sure you want to close the revision?';
    const allowCloseAction = item.getIn(['revision_info', 'status'], '') === 'active' && item.getIn(['revision_info', 'is_last'], false);
    if (allowCloseAction) {
      return (
        <div>
          <Button bsStyle="link" onClick={this.toggleCloseAction} style={{ verticalAlign: 'bottom', lineHeight: '24px' }}>
            Close { getConfig(['systemItems', itemName, 'itemName'], 'item') } <i className="fa fa-angle-right" />
          </Button>
          { showCloseDetails && this.renderDateFromfields() }
          <ConfirmModal onOk={this.onClickCloseOk} onCancel={this.onClickCloseConfirm} show={showConfirmClose} message={closeConfirmMessage} labelOk="Yes" />
        </div>
      );
    }
    return null;
  }
}

const mapStateToProps = (state, props) => ({
  minDate: entityMinFrom(state, props),
  dangerousFrom: minEntityDateSelector(state, props),
});
export default connect(mapStateToProps)(CloseActionBox);
