import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Tab, Panel } from 'react-bootstrap';
import { titleCase } from 'change-case';
import { TabsWrapper } from '@/components/Elements';
import EventSettings from './EventSettingsContainer';
import EventsList from './EventsListContainer';
import { getEvents } from '@/actions/eventActions';
import { getConfig } from '@/common/Util';

class Events extends Component {

  static propTypes = {
    location: PropTypes.object.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
  };

  componentWillMount() {
    this.props.dispatch(getEvents());
  }

  render() {
    const { location } = this.props;
    return (
      <div>
        <TabsWrapper id="EventsTab" location={location}>

          <Tab title={titleCase(getConfig(['events', 'entities', 'balance', 'title'], 'balance'))} eventKey={1}>
            <Panel style={{ borderTop: 'none' }}>
              <EventsList eventType="balance" />
            </Panel>
          </Tab>

          <Tab title={titleCase(getConfig(['events', 'entities', 'balancePrepaid', 'title'], 'balance repaid'))} eventKey={2}>
            <Panel style={{ borderTop: 'none' }}>
              <EventsList eventType="balancePrepaid" />
            </Panel>
          </Tab>

          <Tab title={titleCase(getConfig(['events', 'entities', 'fraud', 'title'], 'fraud'))} eventKey={3}>
            <Panel style={{ borderTop: 'none' }}>
              <EventsList eventType="fraud" />
            </Panel>
          </Tab>

          <Tab title="Settings" eventKey={4}>
            <Panel style={{ borderTop: 'none' }}>
              <EventSettings />
            </Panel>
          </Tab>

        </TabsWrapper>
      </div>
    );
  }
}

export default connect(null)(Events);
