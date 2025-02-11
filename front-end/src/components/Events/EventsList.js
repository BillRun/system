import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Panel, Col, Label } from 'react-bootstrap';
import { sentenceCase } from 'change-case';
import pluralize from 'pluralize';
import { Actions, StateIcon } from '@/components/Elements';
import List from '@/components/List';
import { MiniFilter } from '@/components/EntityList/Filter';


class EventsList extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    eventType: PropTypes.string.isRequired,
    thresholdFields: PropTypes.instanceOf(Immutable.List),
    onRemove: PropTypes.func.isRequired,
    onEdit: PropTypes.func.isRequired,
    onClone: PropTypes.func.isRequired,
    onNew: PropTypes.func.isRequired,
    onDisable: PropTypes.func.isRequired,
    onEnable: PropTypes.func.isRequired,
  };

  static defaultProps = {
    items: Immutable.List(),
    thresholdFields: Immutable.List(),
  };

  state = {
    filter: '',
  };

  onChangeFilter = (e) => {
    const { value } = e.target;
    this.setState(() => ({ filter: value }));
  }

  onClearFilter = (e) => {
    this.setState(() => ({ filter: '' }));
  }

  parseShowEnable = item => !item.get('active', true);

  parseShowDisable = item => !(this.parseShowEnable(item));

  parserStatus = item => (<StateIcon status={item.get('active', true) ? 'active' : 'expired'} />);

  parserThreshold = (item) => {
    const { thresholdFields } = this.props;
    return item
      .getIn(['threshold_conditions', 0], Immutable.List())
      .map(threshold => threshold.get('field', ''))
      .map(name => thresholdFields
        .find(op => op.get('id', '') === name, null, Immutable.Map())
        .get('title', sentenceCase(name)),
      )
      .join(', ');
  }

  getListFields = () => {
    const { eventType } = this.props;
    const fields = [
      { id: 'active', title: 'Status', parser: this.parserStatus, cssClass: 'state' },
      { id: 'event_code', title: 'Event Code' },
      { id: 'event_description', title: 'Description' },
    ];
    if (eventType === 'fraud') {
      fields.push(
        { id: 'threshold', title: 'Threshold', parser: this.parserThreshold },
      );
    }
    return fields;
  };

  getListActions = () => [
    { type: 'enable', showIcon: true, helpText: 'Enable', onClick: this.props.onEnable, show: this.parseShowEnable },
    { type: 'disable', showIcon: true, helpText: 'Disable', onClick: this.props.onDisable, show: this.parseShowDisable },
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.props.onEdit },
    { type: 'clone', showIcon: true, helpText: 'Clone', onClick: this.props.onClone },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: this.props.onRemove },
  ];

  getPanelActions = () => [{
    type: 'add',
    actionStyle: 'primary',
    actionSize: 'xsmall',
    label: 'Add new',
    onClick: this.props.onNew,
  }];

  filterItems = () => {
    const { items } = this.props;
    const { filter } = this.state;
    if (!filter) {
      return items;
    }
    return items.filter(item => item.get('event_code', '').includes(filter));
  }

  renderPanelHeader = (filterDetails) => {
    const { filter } = this.state;
    return (
      <div>&nbsp;
        <div className="pull-left">
          <MiniFilter
            filter={filter}
            placeholder="Filter by event code..."
            onChange={this.onChangeFilter}
            onClear={this.onClearFilter}
          />
        </div>
        { filter !== '' && <Label className="filter-info">{filterDetails}</Label>}
        <div className="pull-right">
          <Actions actions={this.getPanelActions()} />
          </div>
      </div>
    );
  }

  render() {
    const { items } = this.props;
    const filteredItems = this.filterItems();
    const fields = this.getListFields();
    const actions = this.getListActions();
    const nameEvent = pluralize('event', items.size);
    const filterDetails = `Showing ${filteredItems.size} of ${items.size} ${nameEvent}`
    return (
      <div>
        <Col sm={12}>
          <Panel header={this.renderPanelHeader(filterDetails)}>
            <List
              items={filteredItems}
              fields={fields}
              actions={actions}
            />
          </Panel>
        </Col>
      </div>
    );
  }
}

export default EventsList;
