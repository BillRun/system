import React, { useEffect } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { Form, FormGroup, Col, ControlLabel, Button, InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';
import { EntityField } from '@/components/Entity';
import { getList } from '@/actions/listActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { accountsOptionsSelector } from '@/selectors/listSelectors';
import { entityFieldSelector } from '@/selectors/settingsSelector';
import { getAccountsQuery } from '@/common/ApiQueries';
import {
  getFieldName,
  buildPageTitle,
} from '@/common/Util';

const CustomerHierarchy = ({
  customer,
  fields,
  accountsOptions,
  onChangeField,
  onRemoveField,
  isParentChanged,
  dispatch,
}) => {

  useEffect(() => {
    dispatch(getList('available_accounts', getAccountsQuery()));
  }, []);

  const onChangeParentAccount = (aid) => {
    if (isNumber(aid)) {
      onChangeField('parent_aid', parseFloat(aid));
    } else {
      onChangeField('parent_aid', '');
    }
  }

  const onToggleIssuesInvoice = (path, value) => {
    onChangeField(path, value);
    if (value === false) {
      onChangeField('is_payer', false);
    }
  }

  const showTreeView = () => {
    const confirm = {
      message: getFieldName('account_hierarchy_tree_view', 'account', null,
        {customer_title: buildPageTitle('view', 'customer', customer)}
      ),
      children: <p>Tree</p>,
      type: 'alert',
      labelOk: getFieldName('close'),
    };
    dispatch(showConfirmModal(confirm));
  }

  const filteredAccountsOptions = accountsOptions
    .filter(obj => obj.value !== customer.get('aid', ''));
  const issuesInvoiceField = fields
    .find(f => f.get('field_name') === 'issues_invoice', null, Immutable.Map())
    .set('description', getFieldName('issues_invoice_help', 'account'))
    .set('title', getFieldName('issues_invoice', 'account'));
  const isPayerField = fields
    .find(f => f.get('field_name') === 'is_payer', null, Immutable.Map())
    .set('description', getFieldName('is_payer_help', 'account'))
    .set('title', getFieldName('is_payer', 'account'));

  const disabledTreeView = isParentChanged || customer.get('parent_aid', '') === '' || customer.get('aid', '') === '';

  return (
    <Form horizontal className="hierarchy-account">
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            {getFieldName('parent_aid', 'account')}
          </Col>
          <Col sm={8} lg={9}>

            <InputGroup>
              <Field
                fieldType="select"
                value={customer.get('parent_aid', '')}
                options={filteredAccountsOptions}
                onChange={onChangeParentAccount}
                placeholder="Select parent account"
                clearable={true}
              /> 
              <InputGroup.Addon style={{padding: 1}}>
                <Button
                  bsSize="small"
                  bsStyle="primary"
                  onClick={showTreeView}
                  disabled={disabledTreeView}
                >
                  <i className="fa fa-sitemap" aria-hidden="true"></i>
                  &nbsp;
                  {getFieldName('show_tree_view', 'account')}
                </Button>
              </InputGroup.Addon>
            </InputGroup>
          </Col>

        </FormGroup>

      <EntityField
        field={issuesInvoiceField}
        entity={customer}
        onChange={onToggleIssuesInvoice}
        onRemove={onRemoveField}
      />

      <EntityField
        field={isPayerField}
        entity={customer}
        onChange={onChangeField}
        onRemove={onRemoveField}
        disabled={customer.get('issues_invoice', false) === false}
      />
    </Form>
  )
}

CustomerHierarchy.propTypes = {
  customer: PropTypes.instanceOf(Immutable.Map),
  isParentChanged: PropTypes.bool,
  accountsOptions: PropTypes.array,
  onChangeField: PropTypes.func.isRequired,
  onRemoveField: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

CustomerHierarchy.defaultProps = {
  customer: Immutable.Map(),
  isParentChanged: false,
  accountsOptions: [],
};

const mapStateToProps = (state, props) => ({
  accountsOptions: accountsOptionsSelector(state, props),
  fields: entityFieldSelector(state, {entityName: ['subscribers', 'account']}),
});

export default connect(mapStateToProps)(CustomerHierarchy);