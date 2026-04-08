import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { Form, Col, Tabs, Tab, Button } from 'react-bootstrap'
import { FormGroup } from '@/common/BootstrapCompat';
import { ModalWrapper, RevisionTimeline, ConfirmModal } from '@/components/Elements';
import { getItemDateValue, getConfig, getItemId, getItemMinFromDate, toImmutableList } from '@/common/Util';
import Field from '@/components/Field';
import { minEntityDateSelector } from '@/selectors/settingsSelector';
import { getSettings } from '@/actions/settingsActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';


class MoveActionBox extends Component {

  static propTypes = {
    itemId: PropTypes.string.isRequired, // eslint-disable-line react/no-unused-prop-types
    item: PropTypes.instanceOf(Immutable.Map),
    itemName: PropTypes.string.isRequired,
    revisions: PropTypes.instanceOf(Immutable.List),
    minStartDate: PropTypes.instanceOf(moment),
    onMoveItem: PropTypes.func,
    onCancelMoveItem: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    itemId: PropTypes.string,
    item: Immutable.Map(),
    revisions: Immutable.List(),
    minStartDate: moment(0), // default no date so it set to 1970
    onMoveItem: () => {},
    onCancelMoveItem: () => {},
  }

  state = {
    startDate: getItemDateValue(this.props.item, 'from', null),
    endDate: getItemDateValue(this.props.item, 'to', null),
    activeTab: this.props.item.getIn(['revision_info', 'movable_from'], true) ? 1 : 2,
    progress: false,
    showEndConfirm: false,
    showStartConfirm: false,
  }

  componentDidMount() {
    this.props.dispatch(getSettings(['minimum_entity_start_date', 'system']));
  }

  
  onClickEndMoveOk = () => {
    this.onClickOk('to');
  }

  onClickStartMoveOk = () => {
    this.onClickOk('from');
  }

  onClickOk = (type) => {
    const { startDate, endDate } = this.state;
    const { item } = this.props;
    const itemToMove = item.withMutations(listwithMutations =>
      listwithMutations
        .set('from', startDate.toISOString())
        .set('to', endDate.toISOString()),
    );
    this.onClickCloseConfirm();
    this.props.onMoveItem(itemToMove, type);
  }

  onClickCancel = () => {
    this.props.onCancelMoveItem();
  }

  onChangeDateFrom = (date) => {
    const { item, minStartDate } = this.props;
    const { startDate } = this.state;
    if (date !== null) {
      if (date.isBefore(minStartDate) && !date.isSame(getItemDateValue(item, 'from', null), 'day')) {
        this.confirmSelectedDate(date, startDate, 'startDate');
      } else {
        this.setState({ startDate: date });
      }
    } else {
      this.setState({ startDate: getItemDateValue(item, 'from', null) });
    }
  }

  onChangeDateTo = (date) => {
    const { item, minStartDate } = this.props;
    const { endDate } = this.state;
    if (date !== null) {
      if (date.isBefore(minStartDate) && !date.isSame(getItemDateValue(item, 'to', null), 'day')) {
        this.confirmSelectedDate(date, endDate, 'endDate');
      } else {
        this.setState({ endDate: date });
      }
    } else {
      this.setState({ endDate: getItemDateValue(item, 'to', null) });
    }
  }

  confirmSelectedDate = (newDate, oldDate, type) => {
    const dateString = newDate.format(getConfig('dateFormat', 'DD/MM/YYYY'));
    const confirm = {
      message: 'Billing cycle for the date you have chosen is already over.',
      children: `Are you sure you want to use ${dateString}?`,
      onOk: () => { this.setState({ [type]: newDate }); },
      onCancel: () => { this.setState({ [type]: oldDate }); },
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  handleSelectTab = (key) => {
    this.setState({ activeTab: key });
  }

  toggleStartConfirm = () => {
    const { showStartConfirm } = this.state;
    this.setState({ showStartConfirm: !showStartConfirm });
  }

  toggleEndConfirm = () => {
    const { showEndConfirm } = this.state;
    this.setState({ showEndConfirm: !showEndConfirm });
  }

  onClickCloseConfirm = () => {
    this.setState({ showEndConfirm: false, showStartConfirm: false });
  }

  onUnlimitedChanged = (e) => {
    const { item } = this.props;
    const { value } = e.target;
    const unlimited = moment().add(50, 'years').isBefore(getItemDateValue(item, 'to', null));
    if (value) { // is new value is set or infinity is checked
      if (unlimited) {
        this.setState({ endDate: getItemDateValue(item, 'to', null) });
      } else {
        this.setState({ endDate: moment().add(100, 'years') });
      }
    } else if (unlimited) { // infinity unchecked and item vas unlimited
      this.setState({ endDate: moment().add(1, 'day') });
    } else { // infinity unchecked
      this.setState({ endDate: getItemDateValue(item, 'to', null) });
    }
  }

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    if (!Immutable.is(prevProps.item, this.props.item)) {
      this.setState({
        startDate: getItemDateValue(this.props.item, 'from', null),
        endDate: getItemDateValue(this.props.item, 'to', null),
      });
    }
  }

  render() {
    const { item, revisions, itemName, minStartDate } = this.props;
    const {
      startDate,
      endDate,
      activeTab,
      progress,
      showEndConfirm,
      showStartConfirm,
    } = this.state;

    if (revisions.isEmpty() || !Immutable.Map.isMap(item) || item.isEmpty()) {
      return null;
    }

    const revisionBy = toImmutableList(getConfig(['systemItems', itemName, 'uniqueField'], '')).get(0, '');
    const title = `${item.get(revisionBy, '')} - Move`;
    const itemStartDate = getItemDateValue(item, 'from', null);
    const itemEndDate = getItemDateValue(item, 'to', null);
    const hasStartDate = moment.isMoment(startDate);
    const hasEndDate = moment.isMoment(endDate);
    const disableFromSubmit = (!hasStartDate) || (hasStartDate && startDate.isSame(itemStartDate, 'days')) || progress;
    const disableToSubmit = (!hasEndDate) || (hasEndDate && endDate.isSame(itemEndDate, 'days')) || progress;
    const dateFormat = getConfig('dateFormat', 'DD/MM/YYYY');
    const isEndDateUnlimited = hasEndDate && moment().add(50, 'years').isBefore(endDate);
    const startConfirmMessage = `Are you sure you want to set start date to be ${hasStartDate ? startDate.format(dateFormat) : ''} ?`;
    const endConfirmMessage = `Are you sure you want to set end date to be  ${isEndDateUnlimited ? 'infinite' : (hasEndDate ? endDate.format(dateFormat) : '')} ?`;
    const revisionIndex = revisions.findIndex(revision => getItemId(revision) === getItemId(item));
    const isLast = revisionIndex === 0;
    const minStart = getItemMinFromDate(revisions.get(revisionIndex + 1, null), moment(0));
    const maxStart = getItemDateValue(item, 'to');
    const minEnd = getItemMinFromDate(item, moment(0));
    const maxEnd = isLast ? moment().add(200, 'years') : getItemDateValue(revisions.get(revisionIndex - 1), 'to');
    const disableStartInput = !item.getIn(['revision_info', 'movable_from'], true);
    const disableEndInput = !item.getIn(['revision_info', 'movable_to'], true) || (isEndDateUnlimited && isLast);
    const highlightStartDates = [getItemDateValue(item, 'from', null)];
    const getStartDayClass = day => (
      minStart.isBefore(minStartDate) &&
      !day.isSame(getItemDateValue(item, 'from', null), 'days') &&
      day.isBetween(minStart, minStartDate, 'day', '[)',
    ) ? 'danger-red' : undefined);

    const highlightEndDates = [getItemDateValue(item, 'to', null)];
    const getEndDayClass = day => (
      minEnd.isBefore(minStartDate) &&
      !day.isSame(getItemDateValue(item, 'to', null), 'days') &&
      day.isBetween(minEnd, minStartDate, 'day', '[)',
    ) ? 'danger-red' : undefined);

    return (
      <ModalWrapper show={true} title={title} labelCancel="Close" onCancel={this.onClickCancel} onHide={this.onClickCancel}>
        <div className="text-center move-modal">
          <RevisionTimeline
            revisions={revisions}
            item={item}
            size={10}
          />
          <Form className="form-horizontal pt20">
            <FormGroup>
              <Tabs defaultActiveKey={activeTab} transition={false} id="move-entity" onSelect={this.handleSelectTab}>
                <Tab title="Move Start Date" eventKey={1} disabled={!item.getIn(['revision_info', 'movable_from'], true)}>
                  <div className="pt10" />
                  <Col sm={3} className="text-right pt10">
                    &nbsp;
                  </Col>
                  <Col sm={6} className="pr0">
                    <Field
                      fieldType="date"
                      value={startDate}
                      minDate={minStart}
                      maxDate={maxStart}
                      onChange={this.onChangeDateFrom}
                      isClearable={true}
                      placeholder="Select Start Date..."
                      disabled={disableStartInput}
                      dayClassName={getStartDayClass}
                      highlightDates={highlightStartDates}
                    />
                  </Col>
                  <Col sm={3} className="pl0 text-left">
                    <Button onClick={this.toggleStartConfirm} className="ml5" disabled={disableFromSubmit} variant="primary">
                      OK
                    </Button>
                    <ConfirmModal onOk={this.onClickStartMoveOk} onCancel={this.onClickCloseConfirm} show={showStartConfirm} message={startConfirmMessage} labelOk="Yes" />
                  </Col>
                </Tab>

                <Tab title="Move End Date" eventKey={2} disabled={!item.getIn(['revision_info', 'movable_to'], true)}>
                  <div className="pt10" />
                  <Col sm={3} className="text-right pt10">
                    <Field value={isEndDateUnlimited} onChange={this.onUnlimitedChanged} fieldType="checkbox" label="Infinite" disabled={!isLast} />
                  </Col>
                  <Col sm={6} className="pr0">
                    <Field
                      fieldType="date"
                      value={isEndDateUnlimited && isLast ? null : endDate}
                      onChange={this.onChangeDateTo}
                      isClearable={true}
                      minDate={minEnd}
                      maxDate={maxEnd}
                      disabled={disableEndInput}
                      placeholder="Select End Date..."
                      dayClassName={getEndDayClass}
                      highlightDates={highlightEndDates}
                    />
                  </Col>
                  <Col sm={3} className="pl0 text-left">
                    <Button onClick={this.toggleEndConfirm} className="ml5" disabled={disableToSubmit} variant="primary">
                      OK
                    </Button>
                    <ConfirmModal onOk={this.onClickEndMoveOk} onCancel={this.onClickCloseConfirm} show={showEndConfirm} message={endConfirmMessage} labelOk="Yes" />
                  </Col>
                </Tab>
              </Tabs>
            </FormGroup>
          </Form>
        </div>
      </ModalWrapper>
    );
  }
}

const mapStateToProps = (state, props) => ({
  minStartDate: minEntityDateSelector(state, props),
  item: props.revisions.find(revision => getItemId(revision) === props.itemId) || Immutable.Map(),
});
export default connect(mapStateToProps)(MoveActionBox);
