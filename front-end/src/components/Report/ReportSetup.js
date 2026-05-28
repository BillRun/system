import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel } from 'react-bootstrap';
import { ActionButtons, Actions, LoadingItemPlaceholder } from '@/components/Elements';
import ReportEditor from './ReportEditor';
import ReportList from './ReportList';
import {
  buildPageTitle,
  getConfig,
  getItemId,
} from '@/common/Util';
import {
  buildRequestUrl,
  openWindowWithPost
} from '../../common/Api';
import {
  getReportCSVQuery,
  getReportCSV
 } from '../../common/ApiQueries';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import {
  setPageTitle,
  showConfirmModal,
} from '@/actions/guiStateActions/pageActions';
import {
  saveReport,
  deleteReport,
  getReport,
  clearReport,
  updateReport,
  deleteReportValue,
  setCloneReport,
  getReportData,
  clearReportData,
  setReportDataListPage,
  setReportDataListSize,
  reportTypes,
} from '@/actions/reportsActions';
import { clearItems } from '@/actions/entityListActions';
import { getSettings } from '@/actions/settingsActions';
import { modeSimpleSelector, itemSelector, idSelector, itemSourceSelector } from '@/selectors/entitySelector';
import { reportEntitiesFieldsSelector, reportEntitiesSelector } from '@/selectors/reportSelectors';
import { taxationTypeSelector } from '@/selectors/settingsSelector';
import { itemsSelector, pageSelector, nextPageSelector, sizeSelector } from '@/selectors/entityListSelectors';

class ReportSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    itemSource: PropTypes.instanceOf(Immutable.Map),
    reportFileds: PropTypes.instanceOf(Immutable.Map),
    reportData: PropTypes.instanceOf(Immutable.List),
    reportEntities: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    userName: PropTypes.string,
    page: PropTypes.number,
    nextPage: PropTypes.bool,
    size: PropTypes.number,
    taxType: PropTypes.string,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    location: PropTypes.shape({
      pathname: PropTypes.string,
      query: PropTypes.object,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    itemSource: Immutable.Map(),
    reportFileds: Immutable.Map(),
    reportData: Immutable.List(),
    reportEntities: Immutable.List(),
    userName: 'Unknown',
    mode: 'loading',
    page: 0,
    size: getConfig('listDefaultItems', 10),
    taxType: 'vat',
    nextPage: false,
  }

  constructor(props) {
    super(props);
    this.state = {
      showPreview: false,
      progress: false,
      listActions: this.getListActions(),
      editActions: this.getEditActions(),
    };
  }

  componentWillMount() {
    this.fetchItem();
    this.props.dispatch(getSettings([
      'file_types',
      'subscribers.subscriber',
      'subscribers.account',
      'taxation.tax_type',
      'lines',
      'bills.fields',
      'rates.fields',
      'payment_gateways',
      'payments',
    ]));
  }

  componentDidMount() {
    const { mode } = this.props;
    const { type = false } = this.props.location.query;
    if (mode === 'create' && type !== 'predefined') {
      const pageTitle = buildPageTitle(mode, 'report');
      this.props.dispatch(setPageTitle(pageTitle));
    }
    this.initDefaultValues();
  }

  componentWillReceiveProps(nextProps) {
    const { item, mode, itemId } = nextProps;
    const { item: oldItem, itemId: oldItemId, mode: oldMode } = this.props;
    if (mode !== oldMode || getItemId(item) !== getItemId(oldItem)) {
      const pageTitle = buildPageTitle(mode, 'report', item);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  componentDidUpdate(prevProps) {
    const { page, size, mode } = this.props;
    const { page: oldPage, size: oldSize, mode: oldMode } = prevProps;
    if (page !== oldPage || size !== oldSize || (mode !== oldMode && mode === 'view')) {
      this.getReportData();
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearReport());
  }

  initDefaultValues = () => {
    const { mode, userName } = this.props;
    const now = moment().toISOString();
    if (mode === 'create') {
      this.onChangeReportValue(['type'], reportTypes.SIMPLE);
    }
    if (mode === 'update') {
      // Set update default values
    }
    if (mode === 'clone') {
      this.props.dispatch(setCloneReport());
    }
    this.onChangeReportValue(['user'], userName);
    this.onChangeReportValue(['from'], now);
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.setState({ progress: true });
      this.props.dispatch(getReport(itemId)).then(this.afterItemReceived);
    }
  }

  afterItemReceived = (response) => {
    const { mode } = this.props;
    this.setState({ progress: false });
    if (response.status) {
      this.initDefaultValues();
      if (mode === 'view') {
        this.getReportData();
      }
    } else {
      this.handleBack();
    }
  }

  getReportData = () => {
    const { item, size, page } = this.props;
    const report = this.preperReport(item);
    this.setState({ progress: true });
    this.props.dispatch(getReportData({ report, page, size })).then(this.afterReportDataReceived);
  }

  afterReportDataReceived = () => {
    this.setState({ progress: false });
  }

  onChangeReportValue = (path, value, needRefetchData = false) => {
    if (needRefetchData) {
      this.props.dispatch(setReportDataListPage(0));
      this.props.dispatch(clearReportData());
      this.setState({ showPreview: false });
    }
    const stringPath = Array.isArray(path) ? path.join('.') : path;
    const deletePathOnEmptyValue = [];
    const deletePathOnNullValue = [];
    if (value === '' && deletePathOnEmptyValue.includes(stringPath)) {
      this.props.dispatch(deleteReportValue(path));
    } else if (value === null && deletePathOnNullValue.includes(stringPath)) {
      this.props.dispatch(deleteReportValue(path));
    } else {
      this.props.dispatch(updateReport(path, value));
    }
  }

  handleDelete = () => {
    const { item } = this.props;
    this.setState({ progress: true });
    this.props.dispatch(deleteReport(item)).then(this.afterDelete);
  }

  afterDelete = (response) => {
    this.setState({ progress: false });
    if (response.status) {
      const itemsType = getConfig(['systemItems', 'report', 'itemsType'], '');
      this.props.dispatch(showSuccess('The report was deleted'));
      this.props.dispatch(clearItems(itemsType)); // refetch items list because item was (changed in / added to) list
      this.handleBack();
    }
  }

  afterSave = (response) => {
    const { item, mode } = this.props;
    this.setState({ progress: false });
    if (response.status) {
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      const itemsType = getConfig(['systemItems', 'report', 'itemsType'], '');
      this.props.dispatch(showSuccess(`The report was ${action}`));
      this.props.dispatch(clearItems(itemsType)); // refetch items list because item was (changed in / added to) list
      if (mode === 'update') {
        const itemId = item.getIn(['_id', '$id']);
        const itemType = getConfig(['systemItems', 'report', 'itemType'], '');
        this.fetchItem();
        this.props.router.push(`${itemsType}/${itemType}/${itemId}`);
      } else {
        this.handleBack();
      }
    }
  }

  handleSave = () => {
    const { item, mode } = this.props;
    if (this.validate()) {
      this.setState({ progress: true });
      const filteredItem = this.preperReport(item);
      this.props.dispatch(saveReport(filteredItem, mode)).then(this.afterSave);
    }
  }

  handleEdit = () => {
    const { pathname, query } = this.props.location;
    this.props.router.push({
      pathname,
      query: Object.assign({}, query, { action: 'update' }),
    });
  }

  handleClone = () => {
    const { pathname, query } = this.props.location;
    this.props.router.push({
      pathname,
      query: Object.assign({}, query, { action: 'clone' }),
    });
  }

  handleBack = () => {
    const itemsType = getConfig(['systemItems', 'report', 'itemsType'], '');
    this.props.router.push(`/${itemsType}`);
  }

  validateEmptyAggregateOp = column => (
    column.get('field_name', '') !== ''
    && column.get('op', '') === ''
  )

  validate = (isPreview = false) => {
    const { item } = this.props;
    if (!isPreview && item.get('key', '') === '') {
      this.props.dispatch(showDanger('Please enter name'));
      return false;
    }
    if (item.get('entity', '') === '') {
      this.props.dispatch(showDanger('Please select entity'));
      return false;
    }
    if (item.get('columns', Immutable.List()).filter(this.filterColumnsEmptyRows).isEmpty()) {
      this.props.dispatch(showDanger('Please select at least one column'));
      return false;
    }
    if (item.get('type', reportTypes.SIMPLE) === reportTypes.GROPED
      && item.get('columns', Immutable.List()).some(this.validateEmptyAggregateOp)
    ) {
      this.props.dispatch(showDanger('Please select column function'));
      return false;
    }
    return true;
  }

  preperReport = (report = Immutable.Map()) =>
    report.withMutations((mapWithMutations) => {
      mapWithMutations
        .set('conditions', report.get('conditions', Immutable.List())
          .filter(this.filterConditionsEmptyRows),
        )
        .set('columns', report.get('columns', Immutable.List())
          .filter(this.filterColumnsEmptyRows)
          // Remove OP (aggregate function) if reoprt is simple
          .update(columns => (report.get('type', reportTypes.SIMPLE) === reportTypes.SIMPLE
            ? columns.map(column => column.set('op', ''))
            : columns
          )),
        )
        .set('sorts', report.get('sorts', Immutable.List())
          .filter(this.filterSortEmptyRows),
        )
        .set('formats', report.get('formats', Immutable.List())
          .filter(this.filterFormatsEmptyRows)
          .map((row) => {
            if (row.has('type')) {
              return Immutable.Map({
                field: row.get('field'),
                op: row.get('op'),
                value: `${row.get('value')} ${row.get('type')}`,
              });
            }
            return row;
          }),
        );
    });

  filterConditionsEmptyRows = row => [
    row.get('field', ''),
    row.get('op', ''),
    row.get('value', ''),
  ].every(param => param !== '');

  filterSortEmptyRows = row => [
    row.get('op', ''),
    row.get('field', ''),
  ].every(param => param !== '');

  filterFormatsEmptyRows = row => [
    row.get('field', ''),
    row.get('op', ''),
    row.get('value', ''),
  ].every(param => param !== '');

  filterColumnsEmptyRows = row => [
    row.get('field_name', ''),
  ].every(param => param !== '');

  applyFilter = () => {
    if (this.validate(true)) {
      this.setState({ showPreview: true });
      this.getReportData();
    }
  }

  isReportChanged = () => {
    const { item, itemSource } = this.props;
    return Immutable.is(item.delete('from').delete('user'), itemSource.delete('from').delete('user'));
  }

  isExportEnable = () => {
    const { itemId } = this.props;
    const { type = false } = this.props.location.query;
    if (type === 'predefined') {
      return true;
    }
    // don't allow to export new unserved report
    if (!itemId || itemId === '') {
      return false;
    }
    // Allow export only if report was not changed
    return this.isReportChanged();
  }

  getExportReportHelpText = () => {
    if (this.isExportEnable()) {
      return 'Export Report to CSV';
    }
    return 'Reports was changed, please save report before export.';
  }

  onClickExportCSV = () => {
    const { item } = this.props;
    const { type = false } = this.props.location.query;
    if (type === 'predefined') {
      // export csv report
      const csvQuery = getReportCSV({report: item, page: 0, size: 99999});
      openWindowWithPost(csvQuery);
    } else {
      // export csv report by name 
      const csvQuery = getReportCSVQuery(item.get('key', ''));
      window.open(buildRequestUrl(csvQuery));
    }
  }

  onPageChange = (page) => {
    this.props.dispatch(setReportDataListPage(page));
  }

  onSizeChange = (size) => {
    this.props.dispatch(setReportDataListPage(0));
    this.props.dispatch(setReportDataListSize(size));
  }


  onAskDelete = () => {
    const { item } = this.props;
    const confirm = {
      message: `Are you sure you want to delete "${item.get('key', '')}" report ?`,
      onOk: this.onDeleteOk,
      labelOk: 'Delete',
      type: 'delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onDeleteOk = () => {
    this.handleDelete();
  }

  onAskReset = () => {
    const { mode } = this.props;
    const action = (mode === 'create') ? 'reset report' : 'revert report changes';
    const confirm = {
      message: `Are you sure you want to ${action} ?`,
      onOk: this.onResetOk,
      labelOk: 'Yes',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onResetOk = () => {
    const { mode } = this.props;
    if (mode === 'create') {
      this.props.dispatch(clearReport());
      this.initDefaultValues();
    } else {
      this.fetchItem();
    }
  }

  getTableFields = () => Immutable.List().withMutations((columnsWithMutations) => {
    const { item } = this.props;
    item.get('columns', Immutable.List())
      .filter(this.filterColumnsEmptyRows)
      .forEach((column) => {
        columnsWithMutations.push(Immutable.Map({
          id: column.get('key', ''),
          title: column.get('label', ''),
        }));
      });
  });

  getListActions = () => [{
    type: 'export_csv',
    label: 'Export',
    helpText: 'Export Report to CSV',
    showIcon: true,
    onClick: this.onClickExportCSV,
    actionStyle: 'primary',
    actionSize: 'xsmall',
  }, {
    type: 'remove',
    label: 'Delete',
    helpText: 'Delete Report',
    showIcon: true,
    onClick: this.onAskDelete,
    actionStyle: 'danger',
    actionSize: 'xsmall',
  }, {
    type: 'clone',
    label: 'Clone',
    helpText: 'Clone Report',
    showIcon: true,
    onClick: this.handleClone,
    actionStyle: 'primary',
    actionSize: 'xsmall',
  }, {
    type: 'edit',
    label: 'Edit',
    helpText: 'Edit Report',
    showIcon: true,
    onClick: this.handleEdit,
    actionStyle: 'primary',
    actionSize: 'xsmall',
  }];

  getEditActions = () => {
    const { mode } = this.props;
    return [{
      type: 'export_csv',
      label: 'Export',
      helpText: this.getExportReportHelpText,
      showIcon: true,
      onClick: this.onClickExportCSV,
      actionStyle: 'primary',
      actionSize: 'xsmall',
      enable: this.isExportEnable,
    }, {
      type: 'reset',
      label: mode === 'create' ? 'Reset' : 'Revert Changes',
      actionStyle: 'primary',
      showIcon: false,
      onClick: this.onAskReset,
      actionSize: 'xsmall',
    }];
  }

  renderPanelHeader = () => {
    const { listActions, editActions } = this.state;
    const { mode } = this.props;
    const isReportChanged = this.isReportChanged();
    return (
      <div>&nbsp;
        <div className="pull-right">
          <Actions actions={(mode === 'view') ? listActions : editActions} data={isReportChanged} />
        </div>
      </div>
    );
  }

  render() {
    const { progress, showPreview } = this.state;
    const { item, mode, reportFileds, reportEntities, reportData, size, page, nextPage, taxType } = this.props;    
    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }
    const allowEdit = mode !== 'view';
    const tableFields = this.getTableFields();
    const onlyHeaders = allowEdit && (!showPreview || tableFields.isEmpty());
    return (
      <div className="report-setup">
        <Panel header={this.renderPanelHeader()}>
          { allowEdit &&
            <ReportEditor
              report={item}
              reportFileds={reportFileds}
              entities={reportEntities}
              mode={mode}
              taxType={taxType}
              onUpdate={this.onChangeReportValue}
              onFilter={this.applyFilter}
              progress={progress}
            />
          }

          <ReportList
            items={reportData}
            fields={tableFields}
            page={page}
            size={size}
            onlyHeaders={onlyHeaders}
            nextPage={nextPage}
            onChangePage={this.onPageChange}
            onChangeSize={this.onSizeChange}
          />

          <div className="clearfix" />
          <hr className="mb0" />

          <ActionButtons
            onClickCancel={this.handleBack}
            onClickSave={this.handleSave}
            hideSave={!allowEdit}
            progress={progress}
            disableCancel={progress}
          />
        </Panel>
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  userName: state.user.get('name'),
  itemId: idSelector(state, props, 'reports'),
  item: itemSelector(state, props, 'reports'),
  itemSource: itemSourceSelector(state, props, 'reports'),
  mode: modeSimpleSelector(state, props, 'reports'),
  reportFileds: reportEntitiesFieldsSelector(state, props),
  reportEntities: reportEntitiesSelector(state, props),
  reportData: itemsSelector(state, props, 'reportData'),
  page: pageSelector(state, props, 'reportData'),
  nextPage: nextPageSelector(state, props, 'reportData'),
  size: sizeSelector(state, props, 'reportData'),
  taxType: taxationTypeSelector(state, props),
});

export default withRouter(connect(mapStateToProps)(ReportSetup));
