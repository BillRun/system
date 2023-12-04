import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { noCase, upperCaseFirst } from 'change-case';
import { Col, Row, Panel } from 'react-bootstrap';
import { LoadingItemPlaceholder, Actions } from '@/components/Elements';
import List from '../List';
import Pager from './Pager';
import State from './State';
import Filter from './Filter';
import StateDetails from './StateDetails';
import {
  getList,
  clearRevisions,
  setListSort,
  setListFilter,
  setListPage,
  setListSize,
  setListState,
  // clearList,
  // clearItem,
} from '@/actions/entityListActions';
import {
  exportEntities,
} from '@/actions/entityActions';
import {
  importTypesOptionsSelector,
} from '@/selectors/importSelectors';
import {
  entityFieldSelector,
} from '@/selectors/settingsSelector';
import {
  getSettings,
} from '@/actions/settingsActions';
import { getConfig } from '@/common/Util';


class EntityList extends Component {

  static propTypes = {
    itemType: PropTypes.string.isRequired,
    itemsType: PropTypes.string.isRequired,
    collection: PropTypes.string.isRequired,
    entityKey: PropTypes.string,
    api: PropTypes.string,
    items: PropTypes.instanceOf(Immutable.List),
    entityFields: PropTypes.instanceOf(Immutable.List),
    tableFields: PropTypes.array,
    filterFields: PropTypes.array,
    typeSelectOptions: PropTypes.array,
    baseFilter: PropTypes.object,
    baseOrFilter: PropTypes.array,
    projectFields: PropTypes.object,
    page: PropTypes.number,
    nextPage: PropTypes.bool,
    editable: PropTypes.bool,
    allowManageRevisions: PropTypes.bool,
    showRevisionBy: PropTypes.oneOfType([
      PropTypes.bool,
      PropTypes.string,
    ]),
    size: PropTypes.number,
    inProgress: PropTypes.bool,
    forceRefetchItems: PropTypes.oneOfType([
      PropTypes.bool,
      PropTypes.number,
    ]),
    fetchOnMount: PropTypes.bool,
    filter: PropTypes.instanceOf(Immutable.Map),
    sort: PropTypes.instanceOf(Immutable.Map),
    defaultSort: PropTypes.instanceOf(Immutable.Map),
    state: PropTypes.instanceOf(Immutable.List),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    onListRefresh: PropTypes.func,
    onClearFilters: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
    listActions: PropTypes.arrayOf(PropTypes.object),
    refreshString: PropTypes.string,
    actions: PropTypes.arrayOf(PropTypes.object),
  }

  static defaultProps = {
    entityKey: undefined,
    items: null,
    api: 'uniqueget',
    page: 0,
    size: getConfig('listDefaultItems', 10),
    nextPage: false,
    editable: true,
    allowManageRevisions: true,
    showRevisionBy: false,
    inProgress: false,
    forceRefetchItems: false,
    fetchOnMount: true,
    baseFilter: {},
    baseOrFilter: [],
    tableFields: [],
    filterFields: [],
    typeSelectOptions: [],
    entityFields: Immutable.List(),
    projectFields: {},
    sort: Immutable.Map(),
    defaultSort: Immutable.Map(),
    filter: Immutable.Map(),
    state: Immutable.List([0, 1, 2]),
    refreshString: '',
    actions: [],
    listActions: null,
    onListRefresh: null,
    onClearFilters: null,
  }

  componentWillMount() {
    const { entityKey } = this.props;
    const settingsKey = getConfig(['systemItems', entityKey, 'settingsKey'], entityKey);
    this.props.dispatch(getSettings(`${settingsKey}.fields`));
    const { forceRefetchItems, items, sort, defaultSort, itemsType, fetchOnMount } = this.props;
    if ((forceRefetchItems || items == null || items.isEmpty()) && fetchOnMount) {
      this.fetchItems(this.props);
    }
    if (sort.isEmpty() && !defaultSort.isEmpty()) {
      this.props.dispatch(setListSort(itemsType, defaultSort));
    }
  }

  componentDidMount() {
    const { itemsType } = this.props;
    if (this.isImportEnabled()) {
      const importName = `import${upperCaseFirst(itemsType)}`;
      this.props.dispatch(getSettings('plugin_actions', { actions: [importName] }));
    }
  }

  // shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
  //   // return (
  //   //   this.props.page !== nextProps.page
  //   //   || this.props.nextPage !== nextProps.nextPage
  //   //   || this.props.size !== nextProps.size
  //   //   || !Immutable.is(this.props.filter, nextProps.filter)
  //   //   || !Immutable.is(this.props.sort, nextProps.sort)
  //   //   || !Immutable.is(this.props.items, nextProps.items)
  //   // );
  // }

  componentWillUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    const pageChanged = this.props.page !== nextProps.page;
    const sizeChanged = this.props.size !== nextProps.size;
    const filterChanged = !Immutable.is(this.props.filter, nextProps.filter);
    const sortChanged = !Immutable.is(this.props.sort, nextProps.sort);
    const stateChanged = !Immutable.is(this.props.state, nextProps.state);
    const baseFilterMap = (Immutable.fromJS(this.props.baseFilter));
    const baseFilterNextMap = (Immutable.fromJS(nextProps.baseFilter));
    const baseFilterChanged = !Immutable.is(baseFilterMap, baseFilterNextMap);
    const refreshStringChanged = this.props.refreshString !== nextProps.refreshString;
    if (pageChanged || sizeChanged || filterChanged ||
      sortChanged || stateChanged || baseFilterChanged || refreshStringChanged) { 
      this.fetchItems(nextProps);
    }
  }

  componentWillUnmount() {
    // const { itemsType } = this.props;
    // TODO: decide what to do after leaving list page
    // clear all list props - Items, sort, filter, page
    // this.props.dispatch(clearList(itemsType));
    // OR clear only items, and refetch them on back to list with same props
    // this.props.dispatch(clearItem(itemsType));
  }

  onClickNew = () => {
    const { itemsType, itemType } = this.props;
    this.props.router.push(`${itemsType}/${itemType}`);
  }

  onClickRefresh = () => {
    const { collection } = this.props;
    this.fetchItems(this.props);
    this.props.dispatch(clearRevisions(collection));
    if (this.props.onListRefresh) {
      this.props.onListRefresh();
    }
  }

  onClickImport = () => {
    const { itemType } = this.props;
    this.props.router.push(`/import/${itemType}`);
  }

  onClickExport = () => {
    const { itemType } = this.props;
    const now = moment().format('YYYYMMDD');
    const options = Immutable.Map({
      file_name: `export_${itemType}_${now}`,
    });
    const data = Immutable.fromJS(this.getExportQuery(this.props)).merge(options);
    this.props.dispatch(exportEntities(itemType, data));
  }

  onSort = (sort) => {
    const { itemsType } = this.props;
    this.props.dispatch(setListPage(itemsType, 0));
    this.props.dispatch(setListSort(itemsType, sort));
  }

  onFilter = (filter) => {
    const { itemsType } = this.props;
    this.props.dispatch(setListPage(itemsType, 0));
    this.props.dispatch(setListFilter(itemsType, filter));
  }

  onClearFilters = (filter) => {
    const { onClearFilters } = this.props;
    if (onClearFilters) {
      onClearFilters();
    }
  }

  onPageChange = (page) => {
    const { itemsType } = this.props;
    this.props.dispatch(setListPage(itemsType, page));
  }

  onSizeChange = (size) => {
    const { itemsType } = this.props;
    this.props.dispatch(setListPage(itemsType, 0));
    this.props.dispatch(setListSize(itemsType, size));
  }

  onStateChange = (states) => {
    const { itemsType } = this.props;
    this.props.dispatch(setListPage(itemsType, 0));
    this.props.dispatch(setListState(itemsType, states));
  }

  onClickEditItem = (item) => {
    const { itemsType, itemType } = this.props;
    const itemId = item.getIn(['_id', '$id']);
    this.props.router.push(`${itemsType}/${itemType}/${itemId}`);
  }

  onClickViewItem = (item) => {
    const { itemsType, itemType } = this.props;
    const itemId = item.getIn(['_id', '$id']);
    this.props.router.push(`${itemsType}/${itemType}/${itemId}?action=view`);
  }

  buildQuery = (props) => {
    const {
      collection,
      page,
      size,
      sort,
      filter,
      state,
      baseFilter,
      baseOrFilter,
      projectFields,
      api,
      showRevisionBy,
    } = props;
    const project = showRevisionBy ? { ...projectFields, ...{ to: 1, from: 1, revision_info: 1 } } : projectFields;
    const query = { ...filter.toObject(), ...baseFilter };
    const options = { or_fields: [...Object.keys(filter.toObject()), ...baseOrFilter] };
    const request = {
      action: api,
      entity: collection,
      params: [
        { sort: JSON.stringify(sort) },
        { query: JSON.stringify(query) },
        { project: JSON.stringify(project) },
        { page },
        { size },
        { options: JSON.stringify(options) },
      ],
    };
    if (showRevisionBy) {
      request.params.push(
          { states: JSON.stringify(state) },
      );
    }
    return request;
  }

  fetchItems = (props) => {
    const { itemsType } = props;
    this.props.dispatch(getList(itemsType, this.buildQuery(props)));
  }

  parserState = (item) => {
    const { itemType, allowManageRevisions } = this.props;
    return (
      <StateDetails item={item} itemName={itemType} allowManageRevisions={allowManageRevisions} />
    );
  };

  addStateColumn = fields => ([
    { id: 'state', parser: this.parserState, cssClass: 'state' },
    ...fields,
  ]);

  isImportEnabled = () => {
    const { itemType } = this.props;
    if (window.import_export === true) {
      return true;
    }
    return getConfig(['import', 'allowed_entities'], Immutable.List()).includes(itemType);
  }

  showImportEnabled = () => {
    const { typeSelectOptions } = this.props;
    return this.isImportEnabled() && typeSelectOptions.length > 0;
  }

  isExportEnabled = () => {
    const { itemType } = this.props;
    if (window.import_export === true) {
      return true;
    }
    return getConfig(['export', 'allowed_entities'], Immutable.List()).includes(itemType);
  }

  getListActions = () => {
    const { listActions } = this.props;
    const defaultActions = [{
      type: 'export',
      label: 'Export',
      onClick: this.onClickExport,
      show: this.isExportEnabled,
      actionStyle: 'primary',
      actionSize: 'xsmall'
    }, {
      type: 'import',
      label: 'Import',
      onClick: this.onClickImport,
      show: this.showImportEnabled(),
      actionStyle: 'primary',
      actionSize: 'xsmall'
    }, {
      type: 'refresh',
      label: 'Refresh',
      actionStyle: 'primary',
      showIcon: true,
      onClick: this.onClickRefresh,
      actionSize: 'xsmall',
    }, {
      type: 'add',
      label: 'Add New',
      actionStyle: 'primary',
      showIcon: true,
      onClick: this.onClickNew,
      actionSize: 'xsmall',
    }];
    if (listActions === null) {
      return defaultActions;
    }
    return listActions.map((listAction) => {
      const defaultAction = defaultActions.find(action => action.type === listAction.type);
      if (defaultAction) {
        return Object.assign(defaultAction, listAction);
      }
      return listAction;
    }).reverse();
  }

  getExportQuery = (props) => {
    const requestData = this.buildQuery(props);
    return requestData.params.reduce((acc, val, key) => {
      if (!['page', 'size'].includes(key)) {
        Object.assign(acc, val);
      }
      return acc;
    }, {});
  }

  renderPanelHeader = () => {
    const { entityKey, itemsType } = this.props;
    const itemsTypeName = getConfig(['systemItems', entityKey, 'itemsName'], noCase(itemsType));
    return (
      <div>
        List of all available {itemsTypeName}
        <div className="pull-right">
          <Actions actions={this.getListActions()} />
        </div>
      </div>
    );
  }

  renderFilter = () => {
    const { filter, filterFields, entityFields, customFilters } = this.props;
    const allFilterFields = entityFields
      .filter(field => field.get('searchable', false))
      .map(field => Immutable.Map({
        id: field.get('field_name', ''),
        placeholder: field.get('title', field.get('field_name', '')),
      }))
      .withMutations((accWithMutations) => {
        Immutable.fromJS(filterFields).forEach((field) => {
          if (accWithMutations.findIndex(acc => acc.get('id', '') === field.get('id', '')) < 0) {
            accWithMutations.push(field);
          }
        });
      })
      .reverse()
      .toJS();

    if (allFilterFields.length === 0) {
      return null;
    }
    return (
      <Filter
        filter={filter}
        fields={allFilterFields}
        onFilter={this.onFilter}
        onClear={this.onClearFilters}
        customFilters={customFilters}
      >
        { this.renderStateFilter() }
      </Filter>
    );
  }

  renderStateFilter = () => {
    const { state, showRevisionBy } = this.props;
    if (!showRevisionBy) {
      return null;
    }
    return (
      <div className="pull-right">
        <State states={state} onChangeState={this.onStateChange} />
      </div>
    );
  }

  renderPager = () => {
    const { items, size, page, nextPage } = this.props;
    return (
      <Pager
        page={page}
        size={size}
        count={items.size}
        nextPage={nextPage}
        onChangePage={this.onPageChange}
        onChangeSize={this.onSizeChange}
      />
    );
  }

  getActions = () => {
    const { actions, showRevisionBy } = this.props;
    const editColumn = showRevisionBy ? 1 : 0;
    const editAction = { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.onClickEditItem, show: true, onClickColumn: editColumn };
    const viewAction = { type: 'view', showIcon: true, helpText: 'View', onClick: this.onClickViewItem, show: true, onClickColumn: editColumn };

    return actions.map((action) => {
      switch (action.type) {
        case 'edit': return Object.assign(editAction, action);
        case 'view': return Object.assign(viewAction, action);
        default: return action;
      }
    });
  }

  renderList = () => {
    const { items, sort, tableFields, showRevisionBy } = this.props;
    const actions = this.getActions();
    const fields = (!showRevisionBy) ? tableFields : this.addStateColumn(tableFields);
    return (
      <List
        sort={sort}
        items={items}
        fields={fields}
        onSort={this.onSort}
        actions={actions}
      />
    );
  }

  render() {
    const { items } = this.props;
    if (items === null) {
      return (<LoadingItemPlaceholder />);
    }
    return (
      <Row>
        <Col lg={12} >
          <Panel header={this.renderPanelHeader()}>
            { this.renderFilter() }
            { this.renderList() }
          </Panel>
          { this.renderPager() }
        </Col>
      </Row>
    );
  }
}

const mapStateToProps = (state, props) => {
  let itemType = props.itemType;
  let itemsType = props.itemsType;
  let entityKey = props.itemType;
  let collection = props.collection || props.itemsType;
  let showRevisionBy = props.showRevisionBy;
  if (typeof props.entityKey !== 'undefined') {
    entityKey = props.entityKey;
    const config = getConfig(['systemItems', props.entityKey], Immutable.Map());
    itemType = config.get('itemType', itemType);
    itemsType = config.get('itemsType', itemsType);
    collection = config.get('collection', itemsType);
    // Allow to disable revisions by passing FALSE
    if (props.showRevisionBy !== false) {
      showRevisionBy = config.get('uniqueField', props.showRevisionBy);
    }
  }
  return ({
    collection,
    itemType,
    itemsType,
    entityKey,
    showRevisionBy,
    items: state.entityList.items.get(itemsType),
    page: state.entityList.page.get(itemsType),
    state: state.entityList.state.get(itemsType),
    nextPage: state.entityList.nextPage.get(itemsType),
    sort: state.entityList.sort.get(itemsType),
    filter: state.entityList.filter.get(itemsType),
    size: state.entityList.size.get(itemsType),
    inProgress: state.progressIndicator > 0,
    entityFields: entityFieldSelector(state, {entityName: getConfig(['systemItems', entityKey, 'settingsKey'], entityKey).split('.')}),
    typeSelectOptions: importTypesOptionsSelector(state, props, 'importer', itemType),
  })
}
export default withRouter(connect(mapStateToProps)(EntityList));
