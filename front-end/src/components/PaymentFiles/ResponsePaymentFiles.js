import React, { Component } from "react";
import PropTypes from "prop-types";
import { connect } from "react-redux";
import { withRouter } from "react-router";
import { List, Map, fromJS } from "immutable";
import moment from "moment";
import uuid from 'uuid';
import pluralize from "pluralize";
import { titleCase, pascalCase } from "change-case";
import { Form, FormGroup, ControlLabel, Col, Panel } from "react-bootstrap";
import { WithTooltip, CreateButton } from "@/components/Elements";
import EntityList from "@/components/EntityList";
import Field from "@/components/Field";
import UploadTransactionsFile from "./UploadPaymentFileForm";
import PaymentFileDetails from "@/components/PaymentFiles/PaymentFileDetails";
import {
  paymentResponseGatewayOptionsSelector,
  responseFileTypeOptionsOptionsSelector,
  isRunningResponsePaymentFilesSelector,
  selectedPaymentGatewaySelector,
  selectedFileTypeSelector,
} from "@/selectors/paymentFilesSelectors";
import { getSettings } from "@/actions/settingsActions";
import { showFormModal } from "@/actions/guiStateActions/pageActions";
import {
  getRunningResponsePaymentFiles,
  cleanResponsePaymentFilesTable,
  cleanRunningResponsePaymentFiles,
  setPaymentGateway,
  setFileType,
  sendTransactionsReceiveFile,
} from "@/actions/paymentFilesActions";
import { gotEntity } from '@/actions/entityActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { getFieldName } from "@/common/Util";
import { reportBillsFieldsSelector} from '@/selectors/reportSelectors';

class ResponsePaymentFiles extends Component {

  static propTypes = {
    reportBillsFields: PropTypes.instanceOf(List),
    paymentGateway: PropTypes.string,
    fileType: PropTypes.string,
    paymentGatewayOptions: PropTypes.array,
    fileTypeOptionsOptions: PropTypes.instanceOf(Map),
    isRunningPaymentFiles: PropTypes.number,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    paymentGateway: '',
    fileType: '',
    reportBillsFields: List(),
    paymentGatewayOptions: [],
    fileTypeOptionsOptions: Map(),
    isRunningPaymentFiles: 0,
  };

  static secReloadDelayAfterGenerateFile = 2;
  static maxAutoReloadData = 30;
  static secAutoReloadData = [1, 2, 4, 8, 16, 32, 60]; // last delay will repeat until reached the the limit (maxAutoReloadData)

  state = {
    refreshString: '',
    autoReloadCount: 0,
  };

  componentDidMount() {
    const { paymentGateway, fileType } = this.props;
    if (paymentGateway !== '' && fileType !== '') {
      this.props.dispatch(getRunningResponsePaymentFiles(paymentGateway, fileType));
    }
    this.props.dispatch(getSettings("payment_gateways"));
  }

  componentDidUpdate(prevProps, prevState) {
    // eslint-disable-line no-unused-vars
    const { paymentGateway, fileType, isRunningPaymentFiles } = this.props;
    const { autoReloadCount } = this.state;
    const isRunningPaymentFilesChanged = isRunningPaymentFiles !== prevProps.isRunningPaymentFiles;
    const isRequiredSelected = paymentGateway !== "" && fileType !== "";
    const isRequiredChanged = `${paymentGateway}_${fileType}` !== `${prevProps.paymentGateway}_${prevProps.fileType}`;
    const isAutoReloadCountChanged = autoReloadCount !== prevProps.autoReloadCount;
    if (isRequiredChanged) {
      this.props.dispatch(cleanRunningResponsePaymentFiles());
      this.props.dispatch(cleanResponsePaymentFilesTable());
    }
    // if Payment Gateway or File Type was changed, stop auto reload
    if (isRequiredChanged && this.reloadTableTimeout) {
      this.autoReloadResetCounter();
    }
    // if Payment Gateway or File Type was set to new values, reload screen data
    if (isRequiredSelected && isRequiredChanged) {
      this.reloadData();
    }
    // new running file added - start auto reload
    if (isRequiredSelected && isRunningPaymentFiles > 0 && prevProps.isRunningPaymentFiles === 0) {
      const resetAutoReloadCount = 1;
      this.setState(() => ({ autoReloadCount: resetAutoReloadCount }));
    }
    // All running file finished - stop auto reload
    if (isRunningPaymentFilesChanged && isRunningPaymentFiles === 0) {
      this.autoReloadResetCounter();
    }
    // Auto Reload counter changed, set next auto rerun if not reseted and less then Max reloads
    if (isAutoReloadCountChanged) {
      clearTimeout(this.reloadTableTimeout);
      const isAutoReloadCountLessThenMax = autoReloadCount < ResponsePaymentFiles.maxAutoReloadData;
      if (isRequiredSelected && isAutoReloadCountLessThenMax && autoReloadCount > 0) {
        const delay = this.getAutoReloadNextDelay();
        this.reloadTableTimeout = setTimeout(this.autoReloadData, delay, autoReloadCount);
      }
    }
  }

  componentWillUnmount() {
    this.props.dispatch(cleanRunningResponsePaymentFiles());
    if (this.reloadTableTimeout) {
      clearTimeout(this.reloadTableTimeout);
    }
  }

  // class variable for auto reload timer
  reloadTableTimeout = null;

  autoReloadResetCounter = () => {
    this.setState(() => ({ autoReloadCount: 0 }));
  };

  autoReloadData = (currCount) => {
    this.reloadData();
    this.setState(() => ({ autoReloadCount: currCount + 1 }));
  };

  getAutoReloadNextDelay = () => {
    const { autoReloadCount } = this.state;
    const index = autoReloadCount - 1;
    const sec = index < ResponsePaymentFiles.secAutoReloadData.length
      ? ResponsePaymentFiles.secAutoReloadData[index]
      : ResponsePaymentFiles.secAutoReloadData[ResponsePaymentFiles.secAutoReloadData.length - 1];
    return sec * 1000;
  };

  fetchRunningPaymentFiles = (paymentGateway, fileType) => {
    this.props.dispatch(getRunningResponsePaymentFiles(paymentGateway, fileType));
  };

  onChangePaymentGatewayValue = (value) => {
    const paymentGateway = value && value.length ? value : "";
    this.props.dispatch(setPaymentGateway(paymentGateway, 'response'));
  };

  onChangeFileTypeValue = (value) => {
    const fileType = value && value.length ? value : "";
    this.props.dispatch(setFileType(fileType, 'response'));
  };

  onShowDetails = (data) => {
    const config = {
      title: `Details of ${this.getLabel("stamp").toLowerCase()} ${data.get("stamp", "")}`,
      labelCancel: "Hide",
      showOnOk: false,
      skipConfirmOnClose: true,
    };
    const fields = fromJS(this.getDetailsFields()).map(this.fixDetailsFields);
    const values = data.map(this.fixDetailsValues);
    const item = Map({ values, fields: fields });
    return this.props.dispatch(showFormModal(item, PaymentFileDetails, config));
  };

  onListRefresh = () => {
    const { paymentGateway, fileType } = this.props;
    this.fetchRunningPaymentFiles(paymentGateway, fileType);
  };

  getListActions = () => [{ type: "refresh" }];

  getRowActions = () => {
    return [
      { type: "view", onClick: this.onShowDetails, helpText: "Details", onClickColumn: "stamp" },
      { type: "report", onClick: this.goToReport, helpText: this.getHelpTextForReport, show: this.isFinished, enable: this.getAffectsBills},
    ];
  }

  getFilterFields = () => [{ id: "creation_time", placeholder: this.getLabel("creation_time") }];

  getLabel = (key) => getFieldName(key, "payment_files", titleCase(key));

  getPredefinedReportConfiguration = (line) => {
    const { reportBillsFields, paymentGateway } = this.props;
    const keyCreationTime = uuid.v4();
    const entity = "bills";
    let report = {
      key: `File name: ${line.get('file_name', '')}`,
      entity: entity,
      type: 0,
      columns: [
        { key: keyCreationTime, field_name: "urt", label: "Creation Time", op: "", entity: entity},
        { key: uuid.v4(), field_name: "aid", label: "Customer ID", op: "", entity: entity},
        { key: uuid.v4(), field_name: "txid", label: "BillRun Transaction ID", op: "", entity: entity},
        { key: uuid.v4(), field_name: "amount", label: "Original Absolute Due Amount", op: "", entity: entity},
        { key: uuid.v4(), field_name: "rejected", label: "Rejected Payment?", op: "", entity: entity},
        { key: uuid.v4(), field_name: "waiting_for_confirmation", label: "Waiting For Confirmation?", op: "", entity: entity},
        { key: uuid.v4(), field_name: "type", label: "Type", op: "", entity: entity},
        { key: uuid.v4(), field_name: "generated_pg_file_log", label: "Payment Gateway File ID", op: "", entity: entity},
        { key: uuid.v4(), field_name: "due", label: "Due Amount (Sum)", op: "", entity: entity},
      ],
      conditions: [
        { field: "generated_pg_file_log", op: "in", value: line.get('stamp', ''), type: "string", entity: entity },
        { field: "rejection", op: "ne", value: true, type: "boolean", entity: "bills" },
      ],
      sorts: [
        { "field": keyCreationTime, "op": -1 },
      ],
      formats: [
        { field: keyCreationTime, op: "datetime_format", value: "d/m/Y H:i" },
      ],
    };

    //add addition field to columns array
    reportBillsFields.forEach((reportBillsFiled) => {
      if(!reportBillsFiled.get('payment_gateway', false) || reportBillsFiled.get('payment_gateway') === paymentGateway){
        let column = {
          'key': uuid.v4(),
          'field_name': reportBillsFiled.get('field_name', ''),
          'label': reportBillsFiled.get('title', ''),
          'entity': entity
        };
        report.columns.push(column);
      }
    });
    return report;
  }

  goToReport = (data) => {
    this.props.dispatch(gotEntity('reports', this.getPredefinedReportConfiguration(data)));
    this.props.dispatch(setPageTitle('Transactions Request File Report'));
    this.props.router.push({
      pathname: 'reports/report',
      query: {
        type: 'predefined',
      }
    });
  };

  isRunning = (item) => item.get("start_process_time", "") !== "" && item.get("process_time", "") === "";

  isFinished = (item) => item.get("start_process_time", "") !== "" && item.get("process_time", "") !== "";

  isFailed = (item) => item.get("start_process_time", "") === "" && item.get("process_time", "") === "" && item.get("errors", "") !== "";

  parserStatus = (item) => {
    if (this.isRunning(item)) {
      return <i className='fa fa-spinner fa-pulse' title={this.getLabel("status_running_files")} />;
    }
    if (this.isFinished(item)) {
      return <i className='fa fa-check-circle success-green' title={this.getLabel("status_finished_files")} />;
    }
    if (this.isFailed(item)) {
      return <i className='fa fa-exclamation-circle danger-red' title={this.getLabel("status_failed_files")} />;
    }
    return "-";
  };

  parserError = (item) => {
    const errors = item.get('errors', List());
    if (!errors.isEmpty()) {
      const data = errors.join(', ');
      const maxLen = 25;
      
      if (typeof data === 'string' && maxLen < data.length) {
        return <span title={data}>{data.slice(0, maxLen)}...</span>;
      }
    }
    return null; 
  };

  getDetailsFields = () => [
    { field_name: 'stamp' },
    { field_name: 'creation_time', type: 'datetime' },
    { field_name: 'parameters_string', multiple: true },
    { field_name: 'transactions' },
    { field_name: 'start_process_time', type: 'datetime' },
    { field_name: 'process_time', type: 'datetime' },
    { field_name: 'file_name' },
    { field_name: 'created_by' },
    { field_name: 'errors', multiple: true, lineBreaks: true },
    { field_name: 'warnings', multiple: true },
    { field_name: 'info', multiple: true },
    { field_name: 'affects_bills' },
  ];

  getTableFields = () => [
    { id: "status", title: this.getLabel("status"), parser: this.parserStatus, cssClass: "state text-center" },
    { id: "stamp", title: this.getLabel("stamp") },
    { id: "creation_time", title: this.getLabel("creation_time"), type: "datetime", cssClass: "long-date" },
    { id: "parameters_string", title: this.getLabel("parameters_string") },
    { id: "transactions", title: this.getLabel("transactions") },
    { id: "start_process_time", title: this.getLabel("start_process_time"), type: "mongodatetime", cssClass: "long-date" },
    { id: "process_time", title: this.getLabel("process_time"), type: "mongodatetime", cssClass: "long-date" },
    { id: "file_name", title: this.getLabel("file_name") },
    { id: "created_by", title: this.getLabel("created_by") },
    { id: "errors", title: this.getLabel("errors"), parser: this.parserError },
  ];

  getProjectFields = () => ({
    creation_time: 1,
    parameters_string: 1,
    transactions: 1,
    start_process_time: 1,
    process_time: 1,
    file_name: 1,
    stamp: 1,
    created_by: 1,
    errors: 1,
    warnings: 1,
    info: 1,
    affects_bills:1
  });

  getDefaultSort = () => Map({ creation_time: -1 });

  getGeneratePaymentFileTooltipText = () => {
    const { isRunningPaymentFiles, paymentGateway, fileType } = this.props;
    if (paymentGateway === "") {
      return `Please select ${this.getLabel("payment_gateway")}`;
    }
    if (fileType === "") {
      return `Please select ${this.getLabel("file_type")}`;
    }
    if (isRunningPaymentFiles > 0) {
      return `${isRunningPaymentFiles} files is running...`;
    }
    return "Upload Transactions Receive File";
  };

  fixDetailsFields = (field) => this.fixGeneratePaymentFileFields(field);

  fixDetailsValues = (value) => Map.isMap(value) && value.has('sec') ? moment.unix(value.get('sec')) : value;

  fixGeneratePaymentFileFields = (field) =>
    field.withMutations((fieldWithMutations) => {
      if (!field.has("field_name")) {
        fieldWithMutations.set("field_name", field.get("name", ""));
      }
      if (!field.has("editable")) {
        fieldWithMutations.set("editable", true);
      }
      if (!field.has("display")) {
        fieldWithMutations.set("display", true);
      }
      if (!field.has("title")) {
        const label = this.getLabel(fieldWithMutations.get("field_name", ""));
        fieldWithMutations.set("title", titleCase(label));
      }
    });

  onClickUploadTransactionsFile = () => {
    const config = {
      title: "Upload Transactions Response File",
      labelOk: "Upload",
      onOk: this.onUploadTransactionsFileClickOK,
      skipConfirmOnClose: true, 
    };

    const item = Map({});
    return this.props.dispatch(showFormModal(item, UploadTransactionsFile, config));
  };

  onUploadTransactionsFileClickOK = (paymentFile) => {
    const { paymentGateway, fileType } = this.props;
    const file = paymentFile.get("file", null);
    return this.props
      .dispatch(sendTransactionsReceiveFile(paymentGateway, fileType, file, 'transactions_response'))
      .then(this.afterSuccessUploadTransactionsFile)
      .catch((error) => Promise.reject());
  };

  afterSuccessUploadTransactionsFile = () => {
    const reloadAfter = ResponsePaymentFiles.secReloadDelayAfterGenerateFile * 1000;
    setTimeout(this.reloadData, reloadAfter);
    return Promise.resolve();
  };

  reloadData = () => {
    const { paymentGateway, fileType } = this.props;
    this.setState(() => ({ refreshString: moment().format() }));
    this.fetchRunningPaymentFiles(paymentGateway, fileType);
  };

  renderPanelHeader = () => {
    const { isRunningPaymentFiles, paymentGateway, fileType } = this.props;
    const isRequiredFieldsSelected = paymentGateway !== "" && fileType !== "";
    const showGeneratePaymentFile = isRunningPaymentFiles === 0 && isRequiredFieldsSelected;
    const label = isRequiredFieldsSelected ? `${isRunningPaymentFiles} running ${pluralize("file", isRunningPaymentFiles)}` : "\u00A0";
    return (
      <div>
        {label}
        <div className='pull-right'>
          <WithTooltip helpText={this.getGeneratePaymentFileTooltipText()}>
            <CreateButton
              onClick={this.onClickUploadTransactionsFile}
              buttonStyle={{}}
              action=''
              label='Upload Transactions Response File'
              disabled={!showGeneratePaymentFile}
            />
          </WithTooltip>
        </div>
      </div>
    );
  };

  render() {
    const { paymentGateway, fileType, paymentGatewayOptions, fileTypeOptionsOptions } = this.props;
    const { refreshString } = this.state;
    const fileTypeOptions = fileTypeOptionsOptions.get(paymentGateway, []);
    const disabledFileType = paymentGateway === '';
    const showTable = paymentGateway !== '' && fileType !== '';

    return (
      <Panel header={this.renderPanelHeader()}>
        <Col lg={12}>
          <Form horizontal>
            <FormGroup>
              <Col componentClass={ControlLabel} sm={3}>
                {this.getLabel('payment_gateway')}
              </Col>
              <Col sm={5} lg={4}>
                <Field fieldType="select" value={paymentGateway} options={paymentGatewayOptions} onChange={this.onChangePaymentGatewayValue} />
              </Col>
            </FormGroup>
            <FormGroup>
              <Col componentClass={ControlLabel} sm={3}>
                {this.getLabel('file_type')}
              </Col>
              <Col sm={5} lg={4}>
                <Field fieldType="select" value={fileType} options={fileTypeOptions} onChange={this.onChangeFileTypeValue} disabled={disabledFileType} />
              </Col>
            </FormGroup>
          </Form>
        </Col>
        {showTable && (
          <Col lg={12}>
            <EntityList
              fetchOnMount={false}
              entityKey="paymentsTransactionsResponse"
              api="get"
              showRevisionBy={false}
              baseFilter={{
                source: pascalCase(paymentGateway) + 'TransactionsResponse',
                pg_file_type: fileType,
              }}
              // filterFields={this.getFilterFields()}
              tableFields={this.getTableFields()}
              projectFields={this.getProjectFields()}
              listActions={this.getListActions()}
              actions={this.getRowActions()}
              defaultSort={this.getDefaultSort()}
              refreshString={refreshString}
              onListRefresh={this.onListRefresh}
            />
          </Col>
        )}
      </Panel>
    );
  }
}

const mapStateToProps = (state, props) => ({
  paymentGatewayOptions: paymentResponseGatewayOptionsSelector(state, props) || undefined,
  fileTypeOptionsOptions: responseFileTypeOptionsOptionsSelector(state, props) || undefined,
  isRunningPaymentFiles: isRunningResponsePaymentFilesSelector(state, props) || undefined,
  reportBillsFields: reportBillsFieldsSelector(state, props) || undefined,
  paymentGateway: selectedPaymentGatewaySelector(state, props, 'response'),
  fileType: selectedFileTypeSelector(state, props, 'response'),
});

export default withRouter(connect(mapStateToProps)(ResponsePaymentFiles));
