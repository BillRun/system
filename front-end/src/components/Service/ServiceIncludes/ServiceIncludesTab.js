import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Col, Row, Table } from 'react-bootstrap';
import { Panel } from '@/common/BootstrapCompat';
import Immutable from 'immutable';
import moment from 'moment';
import Help from '../../Help';
import { GroupsInclude } from '@/language/FieldDescriptions';
import ServiceProductsGroupEdit from './ServiceProductsGroupEdit';
import ServiceProductsGroupCreate from './ServiceProductsGroupCreate';
import { apiBillRun } from '@/common/Api';
import { getProductsGroupsRevisionsQuery } from '@/common/ApiQueries';
import { getProductsGroupsForRevision, getProductsGroupsLatestRevisions } from '@/common/Util';
import { getSettings } from '@/actions/settingsActions';
import {
  usageTypeSelector,
  propertyTypeSelector,
  currencySelector,
} from '@/selectors/settingsSelector';


class ServiceIncludesTab extends Component {

  static propTypes = {
    productsGroups: PropTypes.instanceOf(Immutable.List),
    // the service revision dates: only the products groups with a revision overlapping
    // them can be included, as the backend validates on save
    serviceFrom: PropTypes.object,
    serviceTo: PropTypes.object,
    usageTypes: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    currency: PropTypes.string,
    onChangeFieldValue: PropTypes.func.isRequired,
    onGroupAdd: PropTypes.func.isRequired,
    onGroupRemove: PropTypes.func.isRequired,
    onGroupCreated: PropTypes.func,
    // the group names already taken in the balance groups namespace, including the
    // counters and groups added on this service before it was saved
    existingGroupsNames: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    dispatch: PropTypes.func.isRequired,
    plays: PropTypes.string,
  }

  static defaultProps = {
    productsGroups: Immutable.List(),
    serviceFrom: null,
    serviceTo: null,
    usageTypes: Immutable.List(),
    propertyTypes: Immutable.List(),
    currency: 'USD',
    onGroupCreated: () => {},
    existingGroupsNames: Immutable.List(),
    mode: 'create',
    plays: '',
  };

  state = {
    // every revision of every products group, with its dates
    productsGroupsRevisions: Immutable.List(),
  };

  componentDidMount() {
    const { usageTypes, propertyTypes } = this.props;
    if (usageTypes.isEmpty() || propertyTypes.isEmpty()) {
      this.props.dispatch(getSettings(['usage_types', 'property_types']));
    }
    this.fetchProductsGroups();
  }

  fetchProductsGroups = () => {
    apiBillRun(getProductsGroupsRevisionsQuery())
      .then((success) => {
        this.setState({ productsGroupsRevisions: Immutable.fromJS(success.data[0].data.details) });
      })
      .catch(() => {
        this.setState({ productsGroupsRevisions: Immutable.List() });
      });
  }

  onProductsGroupCreated = (productsGroup) => {
    const { productsGroupsRevisions } = this.state;
    // the create popup starts the new group now, so its revision is known without a refetch
    const revision = productsGroup.set('from', moment().toISOString());
    this.setState({ productsGroupsRevisions: productsGroupsRevisions.push(revision) });
    this.props.onGroupCreated(productsGroup);
  }

  /**
   * the products groups that can be included by this service revision
   */
  getAvailableProductsGroups = () => {
    const { serviceFrom, serviceTo } = this.props;
    const { productsGroupsRevisions } = this.state;
    return getProductsGroupsForRevision(productsGroupsRevisions, serviceFrom || moment(), serviceTo);
  }

  getUsedKeys = () => this.props.productsGroups
    .map(productsGroup => productsGroup.get('key', ''))
    .toList();

  /**
   * the details of an included products group: the revision covering the service, else
   * the latest one, so a group none of whose revisions covers the service is still shown
   */
  getProductsGroupData = (key) => {
    const { productsGroupsRevisions } = this.state;
    const byKey = productsGroup => productsGroup.get('key', '') === key;
    return this.getAvailableProductsGroups().find(byKey)
      || getProductsGroupsLatestRevisions(productsGroupsRevisions).find(byKey)
      || Immutable.Map();
  }

  renderGroups = () => {
    const { productsGroups, mode, propertyTypes, currency } = this.props;

    if (productsGroups.isEmpty()) {
      return (<tr><td colSpan="8" className="text-center">No Products Groups</td></tr>);
    }

    return productsGroups.map((productsGroup, index) => (
      <ServiceProductsGroupEdit
        key={productsGroup.get('key', index)}
        index={index}
        mode={mode}
        productsGroup={productsGroup}
        productsGroupData={this.getProductsGroupData(productsGroup.get('key', ''))}
        propertyTypes={propertyTypes}
        currency={currency}
        onChangeFieldValue={this.props.onChangeFieldValue}
        onGroupRemove={this.props.onGroupRemove}
      />
    ))
    .toArray();
  }

  renderHeader = () => (
    <tr>
      <th style={{ width: 200 }}>Products Group</th>
      <th style={{ width: 120 }}>Type</th>
      <th style={{ width: 100 }}>Include</th>
      <th style={{ width: 100 }}>Unit</th>
      <th className="text-center" style={{ width: 80 }}>Shared</th>
      <th className="text-center" style={{ width: 80 }}>Pooled</th>
      <th className="text-center" style={{ width: 80 }}>Quantitative</th>
      <th style={{ width: 60 }} />
    </tr>
  );

  render() {
    const { mode, plays, existingGroupsNames } = this.props;
    const allowCreate = mode !== 'view';

    return (
      <Row>
        <Col lg={12}>
          <Panel header={<h3>Products Groups <Help contents={GroupsInclude.products_group} /></h3>}>
            <Table style={{ tableLayout: 'fixed' }}>
              <thead>
                { this.renderHeader() }
              </thead>
              <tbody>
                { this.renderGroups() }
              </tbody>
            </Table>
          </Panel>
          { allowCreate &&
            <ServiceProductsGroupCreate
              availableProductsGroups={this.getAvailableProductsGroups()}
              usedKeys={this.getUsedKeys()}
              addGroup={this.props.onGroupAdd}
              onGroupCreated={this.onProductsGroupCreated}
              existingGroupsNames={existingGroupsNames}
              plays={plays}
            />
          }
        </Col>
      </Row>
    );
  }

}

const mapStateToProps = (state, props) => ({
  usageTypes: usageTypeSelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
  currency: currencySelector(state, props),
});
export default connect(mapStateToProps)(ServiceIncludesTab);
