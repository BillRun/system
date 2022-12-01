import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Panel, Form, Col, Row } from 'react-bootstrap';
import { List } from 'immutable';
import Field from '@/components/Field';
import Help from '../Help';
import { PlanDescription } from '../../language/FieldDescriptions';
import { getIncludedServicesKeysQuery } from '../../common/ApiQueries';
import { getList } from '@/actions/listActions';


class PlanIncludedServicesTab extends Component {

  static propTypes = {
    includedServices: PropTypes.instanceOf(List),
    mode: PropTypes.string,
    services: PropTypes.instanceOf(List),
    dispatch: PropTypes.func.isRequired,
    onChangeFieldValue: PropTypes.func.isRequired,
    plays: PropTypes.instanceOf(List),
  };

  static defaultProps = {
    includedServices: List(),
    mode: 'create',
    services: List(),
    plays: List(),
  };

  componentWillMount() {
    this.props.dispatch(getList('services_keys', getIncludedServicesKeysQuery()));
  }

  onChangeServices = (services) => {
    const servicesList = (services.length) ? services.split(',') : [];
    this.props.onChangeFieldValue(['include', 'services'], List(servicesList));
  }

  filterByPlay = (option) => {
    const { plays } = this.props;
    const servicePlays = option.get('play', List());
    if (plays && !plays.isEmpty() && !servicePlays.isEmpty()) {
      return !plays.filter(entityPlay => servicePlays.includes(entityPlay)).isEmpty();
    }
    return true;
  }

  getServicesOptions = () => {
    const { services } = this.props;
    return services
      .filter(this.filterByPlay)
      .map(service => ({
        value: service.get('name', ''),
        label: service.get('name', ''),
      })).toArray();
  }

  renderEditableServices = () => {
    const { includedServices } = this.props;
    const panelTitle = (
      <h3>Select included services <Help contents={PlanDescription.included_services} /></h3>
    );

    return (
      <Panel header={panelTitle}>
        <Field
          fieldType="select"
          multi={true}
          value={includedServices.join(',')}
          onChange={this.onChangeServices}
          options={this.getServicesOptions()}
        />
      </Panel>
    );
  }

  renderNonEditableServices = () => {
    const { includedServices } = this.props;
    const panelTitle = (
      <h3>Services <Help contents={PlanDescription.included_services} /></h3>
    );

    return (
      <Panel header={panelTitle}>
        <div>
          {includedServices.size ? includedServices.join(', ') : 'No included services'}
        </div>
      </Panel>
    );
  }

  renderServices = () => {
    const { mode } = this.props;
    const editable = (mode !== 'view');
    return editable ? this.renderEditableServices() : this.renderNonEditableServices();
  }

  render() {
    return (
      <Row>
        <Col lg={12}>
          <Form>
            {this.renderServices()}
          </Form>
        </Col>
      </Row>
    );
  }
}

const mapStateToProps = state => ({
  services: state.list.get('services_keys'),
});
export default connect(mapStateToProps)(PlanIncludedServicesTab);
