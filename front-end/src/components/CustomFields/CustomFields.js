import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { List, Map } from 'immutable';
import { titleCase } from 'change-case';
import { Tab, Panel } from 'react-bootstrap';
import TabsWrapper from '../Elements/TabsWrapper';
import CustomFieldsListContainer from './CustomFieldsListContainer';
import CustomFieldsListUsageContainer from './CustomFieldsListUsageContainer';
import { getConfig } from '../../common/Util';


class CustomFields extends Component {

  static propTypes = {
    location: PropTypes.object.isRequired,
    fieldsSettings: PropTypes.instanceOf(Map),
    orderChanged: PropTypes.instanceOf(Map),
    tabs: PropTypes.instanceOf(List),
    onReorder: PropTypes.func.isRequired,
    onNew: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
    onEdit: PropTypes.func.isRequired,
    onCancel: PropTypes.func.isRequired,
    onSave: PropTypes.func.isRequired,
    loadFields: PropTypes.func.isRequired,
    clearFlags: PropTypes.func.isRequired,
  };

  static defaultProps = {
    tabs: List(),
    fieldsSettings: Map(),
    orderChanged: Map(),
  };

  componentWillMount() {
    const { loadFields, tabs } = this.props;
    loadFields(tabs.toJS());
  }

  componentWillUnmount() {
    const { clearFlags } = this.props;
    clearFlags();
  }

  renderUsageTab = () => {
    const { tabs } = this.props;
    const entity = 'usage';
    const label = titleCase(getConfig(['systemItems', entity, 'itemName'], entity));
    return (
      <Tab title={label} eventKey={tabs.size} key={`custom-fields-tab-${entity}`}>
        <Panel style={{ borderTop: 'none' }}>
          <CustomFieldsListUsageContainer />
        </Panel>
      </Tab>
    );
  }

  renderTab = (entity, index) => {
    const {
      fieldsSettings, onReorder, onNew, onRemove, onEdit, onSave, onCancel, orderChanged,
    } = this.props;
    const label = titleCase(getConfig(['systemItems', entity, 'itemName'], entity));
    return (
      <Tab title={label} eventKey={index} key={`custom-fields-tab-${entity}`}>
        <Panel style={{ borderTop: 'none' }}>
          <CustomFieldsListContainer
            entity={entity}
            fieldsSettings={fieldsSettings}
            onReorder={onReorder}
            onNew={onNew}
            onRemove={onRemove}
            onEdit={onEdit}
            onReorederSave={onSave}
            onReorederCancel={onCancel}
            orderChanged={orderChanged.get(entity, false)}
          />
        </Panel>
      </Tab>
    );
  }

  render() {
    const { location, tabs } = this.props;
    const entitiesTabs = tabs
      .filter(tab => tab !== 'usage')
      .map(this.renderTab)
      .push(this.renderUsageTab());
    return (
      <div>
        <TabsWrapper id="CustomFieldsTabs" location={location}>
          {entitiesTabs}
        </TabsWrapper>
      </div>
    );
  }
}

export default CustomFields;
