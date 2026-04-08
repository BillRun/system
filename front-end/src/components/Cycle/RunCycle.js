import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Col, Row, Form } from 'react-bootstrap';
import { ControlLabel, FormGroup, Label, Panel } from '@/common/BootstrapCompat';
import { Map, List } from 'immutable';
import moment from 'moment';
import isNumber from 'is-number';
import { Actions } from '@/components/Elements';
import Field from '@/components/Field';
import CycleData from './CycleData';
import CyclesSelector from './CyclesSelector';
import { getCycleName } from './CycleUtil';
import PartialForm from './PartialForm';
import { getCycleQuery, getChargeStatusQuery, getOperationsQuery } from '@/common/ApiQueries';
import { getList, clearList } from '@/actions/listActions';
import { isWorkersSelector } from '@/selectors/appSelectors';
import {
  runBillingCycle,
  runResetCycle,
  chargeAllCycle,
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
  }

  componentDidUpdate() {
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

  isSelectedCycleWithWorkers = () => {
    const { cycleAdditionalData } = this.props;
    return cycleAdditionalData.get('job_md5', '').length > 0;
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
    const { isWorkers } = this.props;
    const selectedCycleStatus = this.getSelectedCycleStatus();
    return ([{
      label: 'Run!',
      onClick: this.onClickRun,
      show: ['to_run'].includes(selectedCycleStatus) && !isWorkers,
      type: 'start',
      actionStyle: 'primary',
      actionSize: 'small',
    }, {
      label: 'Run!',
      onClick: this.onClickPartialRun,
      show: ['to_run'].includes(selectedCycleStatus) && isWorkers,
      type: 'start',
      actionStyle: 'primary',
      actionSize: 'small',
    }, {
      label: 'Re-run',
      onClick: this.onClickRerun,
      show: ['finished', 'to_rerun'].includes(selectedCycleStatus) && !isWorkers,
      type: 're-start',
      actionStyle: 'primary',
      actionSize: 'small',
    }, {
      label: 'Re-run',
      onClick: this.onClickPartialRerun,
      show: ['finished', 'to_rerun'].includes(selectedCycleStatus) && isWorkers,
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
      children: <p className="text-center"><Label variant="danger">Cycle data will be reset (except for confirmed invoices)</Label></p>,
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
      children: <p className="text-center"><Label variant="danger">Cycle data will be reset (except for confirmed invoices)</Label></p>,
      onOk: this.onRerun,
      labelOk: 'ReRun!',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickPartialRerun = () => {
    this.onClickPartialRun(true);
  }

  onClickChargeAll = () => {
    const confirm = {
      type: 'confirm',
      message: 'Are you sure you want to run a "Charge All" request?',
      children: <p className="text-center"><Label variant="danger">The action will charge all customers</Label></p>,
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

  onClickPartialRun = (isRerun = false) => {
    const config = {
      title: `Are you sure you want run cycle ${getCycleName(this.props.cycleAdditionalData)}`,
      skipConfirmOnClose: false,
      onOk: this.onPartialRun,
      labelOk: 'Run',
    };
    return this.props.dispatch(showFormModal(Map({
      include: [],
      exclude: [],
      isRerun: isRerun
    }), PartialForm, config));
  }

  onChangeSelectedCycle = (selectedCycleName) => {
    this.props.dispatch(clearItems('billruns')); // refetch items list because item was (changed in / added to) list
    this.unsetAutoRefresh();
    this.setState(() => ({
      selectedCycle: this.getCycleData(selectedCycleName),
      selectedCycleName,
    }));
  }

  onPartialRun = (settings) => {
    const { selectedCycle } = this.state;
    const isGeneratePdf = this.getIsGeneratePdf();
    this.props.dispatch(clearItems('billruns'));
    const include = settings.get('include', []);
    const exclude = settings.get('exclude', []);
    this.props.dispatch(runBillingCycleWithWorkers(selectedCycle.get('billrun_key', ''), isGeneratePdf, include, exclude))
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
  renderPanelHeader = () => (
    <Row>
      <Col sm={12} >
        <div className="pull-left">
          <h4>
            Run a billing cycle
          </h4>
        </div>
        <div className="pull-right mt10">
          <Actions actions={this.getHeaderActions()} />
        </div>
      </Col>
    </Row>
  );

  render() {
    const { selectedCycle, selectedCycleName } = this.state;
    const { cycleAdditionalData, isWorkers } = this.props;

    const isCycleWithWorkers = this.isSelectedCycleWithWorkers();
    const completionPercentage = cycleAdditionalData.get('completion_percentage', false);
    const confirmationPercentage = cycleAdditionalData.get('confirmation_percentage', false);
    const cycleStatus = this.getSelectedCycleStatus();
    const billrunKey = selectedCycle.get('billrun_key', '');
    const shouldDisplayBillrunData = List(['running', 'finished', 'confirmed', 'to_rerun']).contains(this.getSelectedCycleStatus());
    const showConfirmAllButton = cycleStatus === 'finished';
    const showGeneratePdf = ['finished', 'to_run'].includes(cycleStatus);
    const isCycleConfirmed = cycleStatus === 'confirmed';
    const isGeneratePdf = this.getIsGeneratePdf();
    const startDate = isCycleWithWorkers
      ? moment(cycleAdditionalData.getIn(['entry', 'start_time'], ''))
      : moment(cycleAdditionalData.get('start_date', selectedCycle.get('start_date', '')));
    const endDate = isCycleWithWorkers
      ? moment(cycleAdditionalData.getIn(['entry', 'end_time'], ''))
      : moment(cycleAdditionalData.get('end_date', selectedCycle.get('end_date', '')));
    const baseFilter = {
      billrun_key: billrunKey,
    };

    return (
      <div className="run-cycle">
        <Row>
          <Col lg={12}>
            <Panel header={this.renderPanelHeader()}>
              <Form className="form-horizontal">
                <FormGroup>
                  <Col sm={3} lg={2} as={ControlLabel}>Select cycle</Col>
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
                  <Col sm={3} lg={2} as={ControlLabel}>Status</Col>
                  <Col sm={6} lg={6}>
                    {cycleStatus === '' 
                      ? <Field value="-" editable={false} />
                      : <Label variant={this.getStatusStyle(cycleStatus)} className="non-editable-field">
                          {cycleStatus.toUpperCase()}
                        </Label>
                    }
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} as={ControlLabel}>
                    {isWorkers ? 'Process start time' : 'Start date'}
                  </Col>
                  <Col sm={6} lg={6}>
                    {startDate.isValid() 
                      ? <Field fieldType="datetime" value={startDate} editable={false} />
                      : <Field value="-" editable={false} />
                    }
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} as={ControlLabel}>
                    {isWorkers ? 'Process end time' : 'End date'}
                  </Col>
                  <Col sm={6} lg={6}>
                  {endDate.isValid() 
                    ? <Field fieldType="datetime" value={endDate} editable={false} />
                    : <Field value="-" editable={false} />
                  }
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} as={ControlLabel}>Last Run Completion percentage</Col>
                  <Col sm={6} lg={6}>
                    { isNumber(completionPercentage)
                      ? <Field fieldType="percentage" value={completionPercentage / 100} editable={false} />
                      : <Field value="-" editable={false} />
                    }
                  </Col>
                </FormGroup>
                <FormGroup>
                  <Col sm={3} lg={2} as={ControlLabel}>Confirmation percentage</Col>
                  <Col sm={6} lg={6}>
                    { isNumber(confirmationPercentage)
                      ? <Field fieldType="percentage" value={confirmationPercentage / 100} editable={false} />
                      : <Field value="-" editable={false} />
                    }
                  </Col>
                </FormGroup>
                { isWorkers && (
                  <FormGroup>
                    <Col sm={3} lg={2} as={ControlLabel}>Total Generated Invoices</Col>
                    <Col sm={6} lg={6}>
                      <Field value={cycleAdditionalData.get('generated_invoices', '-')} editable={false} />
                    </Col>
                  </FormGroup>
                )}
                <FormGroup>
                  <Col sm={3} lg={2} as={ControlLabel} />
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
                  isWorkers={isWorkers}
                />
              )}
            </Panel>
          </Col>
        </Row>
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  cycleAdditionalData: state.list.get('cycle_data', List()).get(0) || Map(),
  chargeStatus: state.list.get('charge_status', List()).get(0) || Map(),
  chargeStatusRefreshed: state.list.get('charge_status_refresh', List()).get(0) || Map(),
  isWorkers: isWorkersSelector(state, props),
});

export default connect(mapStateToProps)(RunCycle);
