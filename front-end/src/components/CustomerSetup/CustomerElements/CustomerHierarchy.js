import React, { useEffect } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { FormGroup, Col, ControlLabel, Button, InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';
import { EntityField } from '@/components/Entity';
import { getList } from '@/actions/listActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { accountsOptionsSelector } from '@/selectors/listSelectors';
import { entityFieldSelector } from '@/selectors/settingsSelector';
import { getAccountsQuery } from '@/common/ApiQueries';
import {
  getFieldName,
} from '@/common/Util';

const CustomerHierarchy = ({
  customer,
  fields,
  accountsOptions,
  onChange,
  onRemoveField,
  isDirty,
  dispatch,
}) => {

  useEffect(() => {
    dispatch(getList('available_accounts', getAccountsQuery()));
  }, []);

  const onChangeParentAccount = (aid) => {
    if (isNumber(aid)) {
      onChange('parent_aid', parseFloat(aid));
    } else {
      onChange('parent_aid', '');
    }
  }

  const onChangeIssuesInvoiceField = (path, value) => {
    onChange(path, value);
    if (value === false) {
      onChange('is_payer', false);
    }
  }

  const showTreeView = () => {
    const confirm = {
      message: getFieldName('account_hierarchy_tree_view', 'account', null, {aid: customer.get('aid', '')}),
      children: <p>Tree</p>,
      type: 'alert',
      labelOk: getFieldName('close'),
    };
    dispatch(showConfirmModal(confirm));
  }

  const filteredAccountsOptions = accountsOptions.filter(obj => obj.value !== customer.get('aid', ''));
  const parentAidField = fields.find(f => f.get('field_name') === 'parent_aid', null, null);
  const issuesInvoiceField = fields.find(f => f.get('field_name') === 'issues_invoice', null, null);
  const isPayerField = fields.find(f => f.get('field_name') === 'is_payer', null, null);

  return (
    <div className="hierarchy-account">
      {parentAidField !== null && (
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            {parentAidField.get('title', 'Parent Account')}
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
                  disabled={isDirty || customer.get('parent_aid', '') === '' || customer.get('aid', '') === ''}
                  onClick={showTreeView}
                >
                  <i className="fa fa-sitemap" aria-hidden="true"></i>
                  &nbsp;
                  {getFieldName('show_tree_view', 'account')}
                </Button>
              </InputGroup.Addon>
            </InputGroup>
          </Col>

        </FormGroup>
      )}

      { issuesInvoiceField !== null && (
        <EntityField
          field={issuesInvoiceField}
          entity={customer}
          onChange={onChangeIssuesInvoiceField}
          onRemove={onRemoveField}
        />
      )}
      { isPayerField !== null && (
        <EntityField
          field={isPayerField}
          entity={customer}
          onChange={onChange}
          onRemove={onRemoveField}
          disabled={customer.get('issues_invoice', false) === false}
        />
      )}
    </div>
  )
}

CustomerHierarchy.propTypes = {
  customer: PropTypes.instanceOf(Immutable.Map),
  accountsOptions: PropTypes.array,
  onChange: PropTypes.func.isRequired,
  onRemoveField: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

CustomerHierarchy.defaultProps = {
  customer: Immutable.Map(),
  accountsOptions: [],
};

const mapStateToProps = (state, props) => ({
  accountsOptions: accountsOptionsSelector(state, props),
  fields: entityFieldSelector(state, {entityName: ['subscribers', 'account']}),
});

export default connect(mapStateToProps)(CustomerHierarchy);