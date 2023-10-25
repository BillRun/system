import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { titleCase } from 'change-case';
import EntityList from '../EntityList';
import { LoadingItemPlaceholder, ModalWrapper, ConfirmModal } from '@/components/Elements';
import Importer from '../Importer';
import { getSettings } from '@/actions/settingsActions';
import { accountFieldsSelector } from '@/selectors/settingsSelector';
import { itemSelector } from '@/selectors/entitySelector';
import { getFieldName, getConfig } from '@/common/Util';


class CustomersList extends Component {

  static propTypes = {
    accountFields: PropTypes.instanceOf(Immutable.List),
    importItem: PropTypes.instanceOf(Immutable.Map),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    accountFields: null,
    importItem: Immutable.Map(),
  };

  static defaultListFields = getConfig(['systemItems', 'customer', 'defaultListFields'], Immutable.List());

  state = {
    showImport: false,
    showCloseImportConfirm: false,
    refreshString: '',
  }

  componentDidMount() {
    const { accountFields } = this.props;
    if (accountFields === null || accountFields.isEmpty()) {
      this.props.dispatch(getSettings(['subscribers']));
    }
  }

  getListFields = () => {
    const { accountFields } = this.props;
    return accountFields
      .filter(field => field.get('show_in_list', false) || CustomersList.defaultListFields.includes(field.get('field_name', '')))
      .map(field => ({
        id: field.get('field_name'),
        placeholder: field.get('title', getFieldName(field.get('field_name', ''), 'account')),
        sort: true,
        type: field.get('field_name') === 'aid' ? 'number' : 'text',
      }))
      .toJS();
  }

  getFilterOverrides = () => [{
    id: 'aid', type: 'number'
  }];

  getListActions = () => [{
    type: 'add',
  }, {
    type: 'refresh',
  }, {
    type: 'import',
    onClick: this.onClickImprt,
    show: this.getEntityOptions().size > 0
  }];

  getActions = () => [
    { type: 'edit' },
  ];

  getEntityOptions = () => getConfig(['import', 'allowed_entities'], Immutable.List())
    .reduce((acc, entity) => (
      ['customer', 'subscription'].includes(entity) ? acc.push(entity) : acc
    ), Immutable.List())

  onCloseImport = () => {
    this.setState({
      showImport: false,
      showCloseImportConfirm: false,
      refreshString: moment().format(), //refetch list items after import
    });
  }

  onClickImprt = () => {
    this.setState({
      showImport: true,
    });
  }

  onClickAskCloseImport = () => {
    this.setState({
      showCloseImportConfirm: true,
    });
  }

  onClickCancelCloseConfirm = () => {
    this.setState({
      showCloseImportConfirm: false,
    });
  }

  render() {
    const { accountFields, importItem } = this.props;
    const { showImport, refreshString, showCloseImportConfirm } = this.state;

    if (accountFields === null) {
      return (<LoadingItemPlaceholder />);
    }

    const fields = this.getListFields();
    const filterOverrides = this.getFilterOverrides();
    const listActions = this.getListActions();
    const actions = this.getActions();
    const apiDateFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
    const defaultFrom = moment().format(apiDateFormat);
    const defaultTo = moment().add(100, 'years').format(apiDateFormat);
    const closeImportConfirmMessage = 'Are you sure you want to close import ?';
    const importEntitiesOptions = this.getEntityOptions();
    const importEntityName = getConfig(['systemItems', importItem.get('entity', ''), 'itemsName'], '');
    const importDefaultValues = Immutable.Map({
      subscription: Immutable.Map({
        from: defaultFrom,
        to: defaultTo,
      }),
      customer:  Immutable.Map({
        from: defaultFrom,
        to: defaultTo,
      }),
    });

    return (
      <div>
        <EntityList
          collection="accounts"
          itemsType="customers"
          itemType="customer"
          tableFields={fields}
          filterFields={filterOverrides}
          actions={actions}
          listActions={listActions}
          refreshString={refreshString}
        />
        <ModalWrapper
          show={showImport}
          title={`Import ${titleCase(importEntityName)}`}
          onHide={this.onClickAskCloseImport}
          modalSize="large"
        >
          <Importer
            entityOptions={importEntitiesOptions}
            onFinish={this.onCloseImport}
            defaultValues={importDefaultValues}
          />
        </ModalWrapper>
        <ConfirmModal onOk={this.onCloseImport} onCancel={this.onClickCancelCloseConfirm} show={showCloseImportConfirm} message={closeImportConfirmMessage} labelOk="Yes" />
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  accountFields: accountFieldsSelector(state, props),
  importItem: itemSelector(state, props, 'importer'),
});

export default withRouter(connect(mapStateToProps)(CustomersList));
