import React, { useState, useEffect } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { Form, FormGroup, Col, ControlLabel, Button, Modal } from 'react-bootstrap';
import Field from '@/components/Field';
import { EntityField } from '@/components/Entity';
import { getList } from '@/actions/listActions';
import { accountsOptionsSelector } from '@/selectors/listSelectors';
import { entityFieldSelector } from '@/selectors/settingsSelector';
import { getAccountsQuery } from '@/common/ApiQueries';
import { getFieldName, buildPageTitle } from '@/common/Util';
import HierarchyTreeView from './HierarchyTreeView';

/**
 * Resolves the root AID of the hierarchy for the given customer.
 * hierarchy_ancestors contains all AIDs from root to current parent (confirmed with Ofer),
 * so first() is the root. Falls back to parent_aid for shallow hierarchies,
 * and to aid itself when the account is the root (no parent).
 */
const getRootAid = (customer) => {
  const ancestors = customer.get('hierarchy_ancestors');
  if (ancestors && ancestors.size > 0) {
    const root = ancestors.first();
    return isNumber(root) ? root : null;
  }
  // Fallback: parent_aid is one level up; works for shallow hierarchies
  const parentAid = customer.get('parent_aid');
  return isNumber(parentAid) ? parentAid : customer.get('aid', null);
};

const CustomerHierarchy = ({
  customer,
  fields,
  accountsOptions,
  onChangeField,
  onRemoveField,
  isParentChanged,
  dispatch,
}) => {
  const [showTree, setShowTree] = useState(false);

  useEffect(() => {
    dispatch(getList('available_accounts', getAccountsQuery()));
  }, [dispatch]);

  const onChangeParentAccount = (aid) => {
    if (isNumber(aid)) {
      onChangeField('parent_aid', parseFloat(aid));
    } else {
      onChangeField('parent_aid', '');
    }
  };

  const onToggleIssuesInvoice = (path, value) => {
    onChangeField(path, value);
    if (value === false) {
      onChangeField('is_payer', false);
    }
  };

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

  const disabledTreeView = isParentChanged || customer.get('aid', '') === '';

  const rootAid = getRootAid(customer);
  const currentAid = customer.get('aid', null);

  return (
    <Form horizontal className="hierarchy-account">
      <FormGroup>
        <Col smOffset={3} lgOffset={2} sm={8} lg={9}>
          <Button
            bsSize="small"
            bsStyle="primary"
            onClick={() => setShowTree(true)}
            disabled={disabledTreeView}
          >
            <i className="fa fa-sitemap" aria-hidden="true" />
            &nbsp;
            {getFieldName('show_tree_view', 'account')}
          </Button>
        </Col>
      </FormGroup>
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3} lg={2}>
          {getFieldName('parent_aid', 'account')}
        </Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            value={customer.get('parent_aid', '')}
            options={filteredAccountsOptions}
            onChange={onChangeParentAccount}
            placeholder="Select parent account"
            clearable={true}
          />
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

      {/* Hierarchy tree modal — uses local state to avoid storing React elements in Redux */}
      <Modal show={showTree} onHide={() => setShowTree(false)} bsSize="large">
        <Modal.Header closeButton>
          <Modal.Title>
            <i className="fa fa-sitemap" aria-hidden="true" />
            &nbsp;
            {getFieldName('account_hierarchy_tree_view', 'account', null, { customer_title: buildPageTitle('view', 'customer', customer) })}
          </Modal.Title>
        </Modal.Header>
        <Modal.Body style={{ padding: 0 }}>
          {showTree && (
            <HierarchyTreeView rootAid={rootAid} currentAid={currentAid} />
          )}
        </Modal.Body>
        <Modal.Footer>
          <Button onClick={() => setShowTree(false)}>{getFieldName('close')}</Button>
        </Modal.Footer>
      </Modal>
    </Form>
  );
};

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
