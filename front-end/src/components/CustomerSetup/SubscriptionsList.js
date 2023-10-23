import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import changeCase from 'change-case';
import EntityList from '../EntityList';
import { getItemDateValue, getConfig, getFieldName } from '@/common/Util';
import { isPlaysEnabledSelector } from '@/selectors/settingsSelector';


class SubscriptionsList extends Component {

  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.List),
    isPlaysEnabled: PropTypes.bool,
    aid: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]),
    onNew: PropTypes.func.isRequired,
    onClickEdit: PropTypes.func.isRequired,
  };

  static defaultProps = {
    settings: Immutable.List(),
    isPlaysEnabled: false,
    aid: '',
  };

  static defaultListFields = getConfig(['systemItems', 'subscription', 'defaultListFields'], Immutable.List());

  planActivationParser = (subscription) => {
    const date = getItemDateValue(subscription, 'plan_activation', false);
    return date ? date.format(getConfig('dateFormat', 'DD/MM/YYYY')) : '';
  };

  servicesParser = (subscription) => {
    const services = subscription.get('services', Immutable.List()) || Immutable.List();
    return services
      .map((service) => {
        if (service.get('quantity', null) !== null) {
          return `${service.get('name', '')} (${service.get('quantity', '')})`;
        }
        return service.get('name', '');
      })
      .join(', ');
  }

  addressParser = (subscription) => {
    if (subscription.get('country', '').length > 0) {
      return `${subscription.get('address', '')} ,${subscription.get('country', '')}`;
    }
    return subscription.get('address', '');
  }

  onClickNew = (e) => {
    const { aid } = this.props;
    this.props.onNew(aid, e);
  }

  filterPlayField = (field) => {
    const { isPlaysEnabled } = this.props;
    if (field.get('field_name', '') !== 'play') {
      return true;
    }
    return isPlaysEnabled;
  }

  getFields = () => {
    const { settings } = this.props;
    return settings
      .filter(this.filterPlayField)
      .filter(field => (field.get('show_in_list', false) || SubscriptionsList.defaultListFields.includes(field.get('field_name', ''))))
      .map((field) => {
        const fieldname = field.get('field_name');
        const fieldTitle = field.get('title', getFieldName(fieldname, 'subscription', changeCase.sentenceCase(fieldname)));
        switch (fieldname) {
          case 'plan_activation':
            return { id: fieldname, title: fieldTitle, parser: this.planActivationParser, cssClass: 'long-date text-center' };
          case 'services':
            return { id: fieldname, title: fieldTitle, parser: this.servicesParser };
          case 'address':
            return { id: fieldname, title: fieldTitle, parser: this.addressParser };
          case 'sid':
            return { id: fieldname, title: fieldTitle, type: 'number', sort: true };
          case 'play':
            return { id: fieldname, title: fieldTitle, sort: true };
          default: {
            return { id: fieldname, title: fieldTitle };
          }
        }
      })
      .toArray();
  }

  getActions = () => [
    { type: 'edit', helpText: 'Edit', onClick: this.props.onClickEdit, onClickColumn: 'sid' },
  ]

  getListActions = () => [{
    type: 'add',
    onClick: this.onClickNew,
  }, {
    type: 'refresh',
  }]

  onActionEdit = (item) => {
    this.props.onClickEdit(item);
  }

  onActionClone = (item) => {
    this.props.onClickEdit(item, 'subscription', 'clone');
  }

  getSubsctiptionListProject = () => {
    const { settings } = this.props;
    return Immutable.Map().withMutations((fieldsWithMutations) => {
      fieldsWithMutations.set('from', 1);
      fieldsWithMutations.set('to', 1);
      fieldsWithMutations.set('revision_info', 1);
      fieldsWithMutations.set('aid', 1);
      SubscriptionsList.defaultListFields.forEach((defaultSubsctiptionListField) => {
        fieldsWithMutations.set(defaultSubsctiptionListField, 1);
      });
      settings
        .filter(field => field.get('show_in_list', false))
        .forEach((field) => {
          fieldsWithMutations.set(field.get('field_name', ''), 1);
        });
    }).toJS();
  }

  render() {
    const { aid } = this.props;
    const fields = this.getFields();
    const actions = this.getActions();
    const listActions = this.getListActions();
    const projectFields = this.getSubsctiptionListProject();
    const baseFilter = { aid, type: 'subscriber' };
    return (
      <div className="row">
        <div className="col-lg-12">
          <EntityList
            itemsType="subscribers"
            itemType="subscription"
            tableFields={fields}
            projectFields={projectFields}
            showRevisionBy={true}
            actions={actions}
            listActions={listActions}
            baseFilter={baseFilter}
            allowManageRevisions={false}
          />
        </div>
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  isPlaysEnabled: isPlaysEnabledSelector(state, props),
});

export default connect(mapStateToProps)(SubscriptionsList);
