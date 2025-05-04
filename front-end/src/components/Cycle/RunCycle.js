import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Col, Row, Panel, Form, FormGroup, ControlLabel, Label } from 'react-bootstrap';
import { Map, List } from 'immutable';
import { Actions } from '@/components/Elements';
import Field from '@/components/Field';
import CycleData from './CycleData';
import CyclesSelector from './CyclesSelector';
import { getCycleName } from './CycleUtil';
import PartialForm from './PartialForm';
import { getCycleQuery, getChargeStatusQuery, getOperationsQuery } from '@/common/ApiQueries';
import { getList, clearList } from '@/actions/listActions';
import {
  runBillingCycle,
  runResetCycle,
  chargeAllCycle,
  getWorkersStatus,
  runBillingCycleWithWorkers
} from '@/actions/cycleActions';
import {
  showFormModal,
  showConfirmModal,
} from "@/actions/guiStateActions/pageActions";
import { clearItems } from '@/actions/entityListActions';


class RunCycle extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    cycleAdditionalData: PropTypes.instanceOf(Map),
    chargeStatus: PropTypes.oneOfType([
      PropTypes.instanceOf(Map),
      PropTypes.instanceOf(List),
    ]),
    chargeStatusRefreshed: PropTypes.oneOfType([
      PropTypes.instanceOf(Map),
      PropTypes.instanceOf(List),
    ]),
  };

  static defaultProps = {
    cycleAdditionalData: Map(),
    chargeStatus: Map(),
    chargeStatusRefreshed: Map(),
  };

  static refreshSteps = [
    { timeout: 10000, iterations: 6 },
    { timeout: 60000, iterations: 60 },
  ];

  constructor(props) {
    super(props);
    this.timerAutoRefresh = null;
    this.timerAutoRefreshChargingStatus = null;
    this.timerRefreshAfterRun = null;
  }

  state = {
    isWorkers: false,
    selectedCycle: Map(),
    selectedCycleName: '',
    autoRefreshRunning: false,
    autoRefreshStep: 0,
    autoRefreshIterations: 0,
    ChargedAllClicked: false,
    generatePdf: null,
    showRefreshButton: false,
    showChargeAllButton: false
  }

  componentDidMount() {
    this.props.dispatch(getList('charge_status', getChargeStatusQuery()));
    this.props.dispatch(getWorkersStatus()).then((isWorkers) => {
      this.setState(() => ({ isWorkers }));
    });
  }

  componentWillReceiveProps() {
    const { cycleAdditionalData } = this.props;
    const { autoRefreshRunning, showRefreshButton } = this.state;
    if (!showRefreshButton && !autoRefreshRunning && cycleAdditionalData.get('cycle_status', '') === 'running') {
      this.initAutoRefresh();
    }
  }

  componentWillUnmount() {
    this.unsetAutoRefresh();
    clearTimeout(this.timerAutoRefreshChargingStatus);
    clearTimeout(this.timerRefreshAfterRun);
    this.clearData();
  }

  /** Init Functions */
  initAutoRefresh = () => {
    this.unsetAutoRefresh();
    this.setState(() => ({
      autoRefreshRunning: true,
      autoRefreshIterations: 0,
      autoRefreshStep: 0,
    }));
    this.timerAutoRefresh = setTimeout(this.runAutoRefresh, RunCycle.refreshSteps[0].timeout);
  }

  initAutoRefreshChargingStatus = () => {
    this.setState(() => ({ ChargedAllClicked: true }));
    clearTimeout(this.timerAutoRefreshChargingStatus);
    this.timerAutoRefreshChargingStatus = setTimeout(() =>
      this.runAutoRefreshChargingStatus(true), 10000);
  }

  /** Functions */
  clearData = () => {
    this.props.dispatch(clearList('cycles_list'));
    this.props.dispatch(clearList('cycle_data'));
    this.props.dispatch(clearList('charge_status'));
    this.props.dispatch(clearList('charge_status_refresh'));
    this.props.dispatch(clearList('billrunInvoices'));
  }

  unsetAutoRefresh = () => {
    clearTimeout(this.timerAutoRefresh);
  }

  runAutoRefreshChargingStatus = (firstTime = false) => {
    const { chargeStatusRefreshed } = this.props;
    clearTimeout(this.timerAutoRefreshChargingStatus);
    if (!firstTime && chargeStatusRefreshed.get('start_date', null) === null) {
      this.setState(() => ({ ChargedAllClicked: false }));
      return;
    }
    this.props.dispatch(getList('charge_status_refresh', getOperationsQuery()));
    this.timerAutoRefreshChargingStatus = setTimeout(this.runAutoRefreshChargingStatus, 10000);
  }

  runAutoRefresh = () => {
    const { cycleAdditionalData } = this.props;
    this.props.dispatch(clearItems('billruns'));
    if (cycleAdditionalData.get('cycle_status', '') !== 'running') {
      this.unsetAutoRefresh();
      this.setState(() => ({ autoRefreshRunning: false }));
      if (cycleAdditionalData.get('cycle_status', '') === 'finished') {
        const { selectedCycleName } = this.state;
        this.onChangeSelectedCycle(selectedCycleName);
      }
      return;
    }
    this.reloadCycleData();
    const { autoRefreshIterations, autoRefreshStep } = this.state;
    const refreshStep = RunCycle.refreshSteps[autoRefreshStep];
    this.unsetAutoRefresh();
    if (autoRefreshIterations < refreshStep.iterations - 1) {
      this.setState(() => ({ autoRefreshIterations: autoRefreshIterations + 1 }));
      this.timerAutoRefresh = setTimeout(this.runAutoRefresh, refreshStep.timeout);
    } else {
      const newAutoRefreshStep = autoRefreshStep + 1;
      if (newAutoRefreshStep >= RunCycle.refreshSteps.length) {
        this.setState(() => ({ showRefreshButton: true, autoRefreshRunning: false }));
        return;
      }
      const newRefreshStep = RunCycle.refreshSteps[newAutoRefreshStep];
      this.timerAutoRefresh = setTimeout(this.runAutoRefresh, newRefreshStep.timeout);
      this.setState(() => ({
        autoRefreshStep: newAutoRefreshStep,
        autoRefreshIterations: 0,
      }));
    }
  }

  runCycle = (rerun = false) => {
    this.props.dispatch(clearItems('billruns'));
    const { selectedCycle } = this.state;
    const isGeneratePdf = this.getIsGeneratePdf();
    this.props.dispatch(runBillingCycle(selectedCycle.get('billrun_key', ''), rerun, isGeneratePdf))
      .then((response) => {
        if (response.status) {
          this.timerRefreshAfterRun = setTimeout(this.reloadCycleData, 1000);
        }
      });
  }

  resetCycle = () => {
    this.props.dispatch(clearItems('billruns'));
    const { selectedCycle } = this.state;
    this.props.dispatch(runResetCycle(selectedCycle.get('billrun_key', '')))
      .then((response) => {
        if (response.status) {
          this.timerRefreshAfterRun = setTimeout(this.reloadCycleData, 1000);
        }
      });
  }

  clearCycleData = () => {
    this.props.dispatch(clearList('cycle_data'));
  }

  reloadCycleData = (selectedCycle = this.state.selectedCycle) => {
    this.clearCycleData();
    const selectedBillrunKey = selectedCycle.get('billrun_key', '');
    if (selectedBillrunKey === '') {
      return;
    }
    this.props.dispatch(getList('cycle_data', getCycleQuery(selectedBillrunKey)));
  }

  isChargingStatusProcessing = () => {
    const { chargeStatusRefreshed } = this.props;
    const { ChargedAllClicked } = this.state;
    const processing = chargeStatusRefreshed.get('start_date', null) !== null;
    return ChargedAllClicked || processing;
  }

  /** Getters Functions */

  getIsGeneratePdf = () => {
    const { generatePdf } = this.state;
    const { cycleAdditionalData } = this.props;
    return (generatePdf !== null) ? generatePdf : cycleAdditionalData.get('generate_pdf', true);
  }

  getCycleData = (cycleName) => {
    const selectedCycle = Map({ billrun_key: cycleName });
    this.reloadCycleData(selectedCycle);
    return selectedCycle;
  }

  getStatusStyle = (status) => {
    switch (status) {
      case 'to_run':
      case 'to_rerun':
        return 'info';
      case 'running':
      case 'current':
        return 'primary';
      case 'future':
        return 'warning';
      case 'finished':
      case 'confirmed':
        return 'success';
      default:
        return 'default';
    }
  }

  getSelectedCycleStatus = () => {
    const { cycleAdditionalData } = this.props;
    const { selectedCycle } = this.state;
    return cycleAdditionalData.get('cycle_status', selectedCycle.get('cycle_status', ''));
  }

  getHeaderActions = () => {
    const { chargeStatus } = this.props;
    const { showRefreshButton, showChargeAllButton } = this.state;
    const selectedCycleStatus = this.getSelectedCycleStatus();
    let disabledChargeAllButton = true;
    let titleChargeAllButton = 'Charge is running...';
    if (this.isChargingStatusProcessing()) {
      disabledChargeAllButton = true;
      titleChargeAllButton = 'Processing...';
    } else if (chargeStatus.get('status', false)) {
      disabledChargeAllButton = false;
      titleChargeAllButton = 'Charge All';
    }

    return ([{
      label: 'Refresh',
      onClick: this.reloadCycleData,
      show: showRefreshButton && ['running'].includes(selectedCycleStatus),
      type: 'refresh',
      actionStyle: 'primary',
      actionSize: 'xsmall'
    }, {
      label: titleChargeAllButton,
      onClick: this.onClickChargeAll,
      enable: !disabledChargeAllButton,
      show: showChargeAllButton,
      showIcon: false,
      actionStyle: 'primary',
      actionSize: 'xsmall'
    }]);
  }

  getRunCycleActions = () => {
    const { isWorkers } = this.state;
    const selectedCycleStatus = this.getSelectedCycleStatus();
    return ([{
      label: 'Run!',
      onClick: this.onClickRun,
      show: ['to_run'].includes(selectedCycleStatus) && !isWorkers,
      type: 'start',
      actionStyle: 'primary',
      actionSize: 'small',
    }, {
      label: 'Run! (with Partial option)',
      onClick: this.onClickPartialRun,
      show: ['to_run'].includes(selectedCycleStatus) && isWorkers,
      type: 'start',
      actionStyle: 'primary',
      actionSize: 'small',
    }, {
      label: 'Re-run',
      onClick: this.onClickRerun,
      show: ['finished', 'to_rerun'].includes(selectedCycleStatus),
      type: 're-start',
      actionStyle: 'primary',
      actionSize: 'small',
    }, {
      label: 'Reset',
      onClick: this.onClickReset,
      show: ['finished'].includes(selectedCycleStatus),
      type: 'reset',
      actionStyle: 'primary',
      actionSize: 'small',
    }]);
  }

  /** Action (On..) Functions */
  onClickReset = () => {
    const { cycleAdditionalData } = this.props;
    const confirm = {
      type: 'confirm',
      message: `Are you sure you want to reset ${getCycleName(cycleAdditionalData)}?`,
      children: <p className="text-center"><Label bsStyle="danger">Cycle data will be reset (except for confirmed invoices)</Label></p>,
      onOk: this.resetCycle,
      labelOk: 'Reset',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickRerun = () => {
    const { cycleAdditionalData } = this.props;
    const confirm = {
      type: 'confirm',
      message: `Are you sure you want to re-run ${getCycleName(cycleAdditionalData)}?`,
      children: <p className="text-center"><Label bsStyle="danger">Cycle data will be reset (except for confirmed invoices)</Label></p>,
      onOk: this.onRerun,
      labelOk: 'ReRun!',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickChargeAll = () => {
    const confirm = {
      type: 'confirm',
      message: 'Are you sure you want to run a "Charge All" request?',
      children: <p className="text-center"><Label bsStyle="danger">The action will charge all customers</Label></p>,
      onOk: this.onChargeAll,
      labelOk: 'Run',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickRun = () => {
    const confirm = {
      type: 'confirm',
      message: `Are you sure you want run cycle ${getCycleName(this.props.cycleAdditionalData)}`,
      onOk: this.runCycle,
      labelOk: 'Run',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickPartialRun = () => {
    const config = {
      title: `Are you sure you want run cycle ${getCycleName(this.props.cycleAdditionalData)}`,
      skipConfirmOnClose: false,
      onOk: this.onPartialRun,
      labelOk: 'Run',
    };
    return this.props.dispatch(showFormModal(Map({
      include: [],
      exclude: [],
    }), PartialForm, config));
  }

  onChangeSelectedCycle = (selectedCycleName) => {
    this.props.dispatch(clearItems('billruns')); // refetch items list because item was (changed in / added to) list
    this.setState(() => ({
      selectedCycle: this.getCycleData(selectedCycleName),
      selectedCycleName,
    }));
  }

  onPartialRun = (item) => {
    const { selectedCycle } = this.state;
    this.props.dispatch(clearItems('billruns'));
    const include = item.get('include', []);
    const exclude = item.get('exclude', []);
    this.props.dispatch(runBillingCycleWithWorkers(selectedCycle.get('billrun_key', ''), include, exclude))
      .then((response) => {
        if (response.status) {
          this.timerRefreshAfterRun = setTimeout(this.reloadCycleData, 1000);
        }
      });
  }

  onChangePdfGenerate = (e) => {
    const { value } = e.target;
    this.setState(() => ({ generatePdf: value }));
  }

  onChargeAll = () => {
    this.initAutoRefreshChargingStatus();
    this.props.dispatch(chargeAllCycle());
  }

  onRerun = () => {
    this.runCycle(true);
  }

  /** Render Functions */
  renderPanelHeader = () => {
    const { isWorkers } = this.state;
    return (
      <Row>
        <Col sm={12} >
          <div className="pull-left">
            <h4>
              Run a billing cycle
              {isWorkers && (<Label bsStyle="success" className="ml10">Workers is ON</Label>)}
            </h4>
          </div>
          <div className="pull-right mt10">
            <Actions actions={this.getHeaderActions()} />
          </div>
        </Col>
      </Row>
    );
  }

  render() {
    const { selectedCycle, selectedCycleName } = this.state;
    const { cycleAdditionalData } = this.props;

    const completionPercentage = cycleAdditionalData.get('completion_percentage', false);
    const confirmationPercentage = cycleAdditionalData.get('confirmation_percentage', false);
    const cycleStatus = this.getSelectedCycleStatus();
    const billrunKey = selectedCycle.get('billrun_key', '');
    const shouldDisplayBillrunData = List(['running', 'finished', 'confirmed', 'to_rerun']).contains(this.getSelectedCycleStatus());
    const showConfirmAllButton = cycleStatus === 'finished';
    const showGeneratePdf = ['finished', 'to_run'].includes(cycleStatus);
    const isCycleConfirmed = cycleStatus === 'confirmed';
    const isGeneratePdf = this.getIsGeneratePdf();
    const baseFilter = {
      billrun_key: billrunKey,
    };

    return (
      <div className="run-cycle">
        <Row>
          <Col lg={12}>
            <Panel header={this.renderPanelHeader()}>
              <Form horizontal>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel}>Select cycle</Col>
                  <Col sm={6} lg={6}>
                    <CyclesSelector
                      onChange={this.onChangeSelectedCycle}
                      statusesToDisplay={List(['past'])}
                      selectedCycles={selectedCycleName}
                      timeStatus={true}
                    />
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel}>Status</Col>
                  <Col sm={6} lg={6}>
                    <Label bsStyle={this.getStatusStyle(cycleStatus)} className="non-editable-field">
                      {cycleStatus.toUpperCase()}
                    </Label>
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel}>Start date</Col>
                  <Col sm={6} lg={6}>
                    <div className="non-editable-field">
                      {cycleAdditionalData.get('start_date', selectedCycle.get('start_date', '-'))}
                    </div>
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel}>End date</Col>
                  <Col sm={6} lg={6}>
                    <div className="non-editable-field">
                      {cycleAdditionalData.get('end_date', selectedCycle.get('end_date', '-'))}
                    </div>
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel}>Completion percentage</Col>
                  <Col sm={6} lg={6}>
                    <div className="non-editable-field">
                      {completionPercentage ? `${completionPercentage}%` : '-'}
                    </div>
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel}>Confirmation percentage</Col>
                  <Col sm={6} lg={6}>
                    <div className="non-editable-field">
                      {confirmationPercentage ? `${confirmationPercentage}%` : '-'}
                    </div>
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} componentClass={ControlLabel} />
                  <Col sm={6} lg={6}>
                    <div className="pull-left">
                      <Actions actions={this.getRunCycleActions()} />
                    </div>
                    <div className="pull-right mt10">
                      { showGeneratePdf && (
                        <Field fieldType="checkbox" value={isGeneratePdf} onChange={this.onChangePdfGenerate} label="Generate PDF invoices" />
                      )}
                    </div>
                  </Col>
                </FormGroup>
              </Form>
              {shouldDisplayBillrunData && (
                <CycleData
                  billrunKey={billrunKey}
                  selectedCycle={cycleAdditionalData}
                  baseFilter={baseFilter}
                  reloadCycleData={this.reloadCycleData}
                  showConfirmAllButton={showConfirmAllButton}
                  isCycleConfirmed={isCycleConfirmed}
                />
              )}
            </Panel>
          </Col>
        </Row>
      </div>
    );
  }
}

const mapStateToProps = state => ({
  cycleAdditionalData: state.list.get('cycle_data', List()).get(0) || Map(),
  chargeStatus: state.list.get('charge_status', List()).get(0) || Map(),
  chargeStatusRefreshed: state.list.get('charge_status_refresh', List()).get(0) || Map(),
});

export default connect(mapStateToProps)(RunCycle);
