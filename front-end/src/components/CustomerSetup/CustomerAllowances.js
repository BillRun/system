import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { debounce } from 'throttle-debounce';
import getSymbolFromCurrency from 'currency-symbol-map';
import { Form, FormGroup, ControlLabel, Col, Row, Panel } from 'react-bootstrap';
import { Actions } from '@/components/Elements';
import Field from '@/components/Field';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { entitySearchByQuery } from '@/actions/entityActions';
import {
  getFieldName,
  getFirstName,
  getLastName,
} from '@/common/Util';


class CustomerAllowances extends Component {

  static propTypes = {
    customer: PropTypes.instanceOf(Immutable.Map),
    editable: PropTypes.bool,
    currency: PropTypes.string,
    onChange: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    customer: Immutable.Map(),
    currency: '',
    editable: true,
  }

  state = {
    allSubscriptions: Immutable.Map(),
    allAccounts: Immutable.Map(),
  }

  componentDidMount() {
    const { customer } = this.props;
    const { allSubscriptions, allAccounts } = this.state;
    this.loadNames(customer, allSubscriptions, allAccounts);
  }
  componentDidUpdate(prevProps, prevState) {
    const { customer } = this.props;
    const { allSubscriptions, allAccounts } = this.state;
    this.loadNames(customer, allSubscriptions, allAccounts);
  }

  loadNames = (customer, allSubscriptions, allAccounts) => {
    const allowances = customer.get('allowances', Immutable.List());
    // Get SID names
    const sids = allowances.map(allowance => allowance.get('sid', ''));
    const missingSids = sids.reduce((acc, sid) =>
      ( allSubscriptions.keySeq().includes(sid) ? acc : acc.push(sid)), Immutable.List());
    if (!missingSids.isEmpty()) {
        const query = {
          sid: { $in: missingSids },
      };
      const project = {sid: 1, firstname: 1, lastname: 1};
      this.props.dispatch(entitySearchByQuery('subscribers', query, project))
        .then(options => {
          const newSidsNames = (options === false)
            ? missingSids.reduce((acc, sid) => acc.set(sid, ''), Immutable.Map())
            : this.formatNames(Immutable.fromJS(options), 'sid');
          this.setState((prevState) => ({ allSubscriptions: prevState.allSubscriptions.merge(newSidsNames) }));
        })
    }
    // Get AID names
    const aids = allowances.map(allowance => allowance.get('aid', ''));
    const missingAids = aids.reduce((acc, aid) =>
      ( allAccounts.keySeq().includes(aid) ? acc : acc.push(aid)) , Immutable.List());
    if (!missingAids.isEmpty()) {
      const query = {
          aid: { $in: missingAids },
      };
      const project = {aid: 1, firstname: 1, lastname: 1};
      this.props.dispatch(entitySearchByQuery('accounts', query, project))
        .then(options => {
          const newAidsNames = (options === false)
            ? missingAids.reduce((acc, aid) => acc.set(aid, ''), Immutable.Map())
            : this.formatNames(Immutable.fromJS(options), 'aid');
          this.setState((prevState) => ({ allAccounts: prevState.allAccounts.merge(newAidsNames) }));
        })
        .catch(() => {});
    }
  }

  onAskDeleteSid = sid => {
    const { customer } = this.props;
    const { allSubscriptions } = this.state;
    const onDeleteSid = () => {
      const allowances = customer.get('allowances', Immutable.List())
        .filter(allowance => allowance.get('sid', '') !== sid);
      return this.props.onChange('allowances', allowances);
    }
    const sidLabel = allSubscriptions.get(sid, '');
    const confirm = {
      message: `Are you sure you want to delete  allowance for subscriber '${sidLabel}' ID ${sid}?`,
      onOk: onDeleteSid,
      labelOk: 'Delete',
      type: 'delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  };

  onAskDeleteAisSids = aid => {
    const { customer } = this.props;
    const { allAccounts } = this.state;
    const onDeleteAisSids = () => {
      const allowances = customer.get('allowances', Immutable.List())
        .filter(allowance => allowance.get('aid', '') !== aid);
      return this.props.onChange('allowances', allowances);
    }
    const aidLabel = allAccounts.get(aid, '');
    const confirm = {
      message: `Are you sure you want to delete all allowances for customer '${aidLabel}' ID ${aid} ?`,
      onOk: onDeleteAisSids,
      labelOk: 'Delete',
      type: 'delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  };

  getAidGroupActions = () => [{
    type: 'remove',
    showIcon: true,
    actionStyle: 'danger',
    actionSize: 'xsmall',
    onClick: this.onAskDeleteAisSids,
  }];

  getSidActions = () => [{
    type: 'remove',
    showIcon: true,
    actionStyle: 'link',
    actionSize: 'xsmall',
    onClick: this.onAskDeleteSid,
  }];

  formatNames = (options, key) =>
    options.reduce((acc, option) => {
      const name = [getFirstName(option), getLastName(option)]
      .map(option => option.trim())
      .filter(option => option !== '')
      .join(' ');
      return acc.set(option.get(key, ''), name);
    }, Immutable.Map())

  createSubscriptionsSelectOption = option => {
    const name = [getFirstName(option), getLastName(option)]
      .map(option => option.trim())
      .filter(option => option !== '')
      .join(' ');
    return {
      value: `${option.get('sid', '')}`,
      label: `${name} (Subscriber ID: ${option.get('sid', '')}, Customer ID: ${option.get('aid', '')})`,
      aid: option.get('aid', ''),
      name,
    };
  }

  subscriptionsSelectOptions = (options, aid, sids) => options
    .filter(option => option.get('aid', '') !== aid)
    .filter(option => !sids.includes(option.get('sid', '')))
    .map(this.createSubscriptionsSelectOption)
    .toList()
    .toArray();

  renderGroupHeader = (aid, title) => (
    <div>
      <small>{`${title} (Customer ID: ${aid})`}</small>
      <div className="pull-right">
        <Actions actions={this.getAidGroupActions()} data={aid} />
      </div>
    </div>
  );

  onChangeSubscriptions = (sid, { option }) => {
    const { customer } = this.props;
    const allowances = customer.get('allowances', Immutable.List())
    const aidNum = isNumber(option.aid) ? parseFloat(option.aid) : option.aid;
    const sidNum = isNumber(sid) ? parseFloat(sid) : sid;
    const newAllowance = Immutable.Map({ sid: sidNum, aid: aidNum, allowance: '' });
    this.setState((prevState) => ({ allSubscriptions: prevState.allSubscriptions.set(sidNum, option.name) }));
    return this.props.onChange('allowances', allowances.push(newAllowance));
  }

  onChangeAllowanceValue = (e) => {
    const { customer } = this.props;
    const { id, value } = e.target;
    const sid = isNumber(id) ? parseFloat(id) : id;
    const allowances = customer.get('allowances', Immutable.List())
    const index = allowances.findIndex(allowance => allowance.get('sid', '') === sid, null, Immutable.Map());
    if (index !== -1) {
      const newValue = isNumber(value) ? Math.abs(parseFloat(value)) : value;
      return this.props.onChange('allowances', allowances.setIn([index, 'allowance'], newValue));
    }
  }

  findSubscribers = (inputValue, callback) => {
    const { customer } = this.props;
    if (inputValue === '') {
      return callback([]);
    }
    const query = {
        firstname: { $regex: inputValue, $options: 'i' },
        lastname: { $regex: inputValue, $options: 'i' },
        aid: parseFloat(inputValue),
        sid: parseFloat(inputValue),
    };
    const options = {or_fields: Object.keys(query)};
    const project = {sid: 1, aid: 1, firstname: 1, lastname: 1};
    const sort = {aid: 1, sid: 1, firstname: 1, lastname: 1};
    const existsSid = customer.get('allowances', Immutable.List()).map(allowance => allowance.get('sid', ''));
    return this.props.dispatch(entitySearchByQuery('subscribers', query, project, sort, options))
      .then(options =>
        callback(this.subscriptionsSelectOptions(Immutable.fromJS(options), customer.get('aid', ''), existsSid))
      )
      .catch(() => callback([]));
  }

  renderAllowancesValue = () => {
    const { customer, editable, currency } = this.props;
    const { allAccounts, allSubscriptions } = this.state;
    const groupsOfAid = customer
      .get('allowances', Immutable.List())
      .groupBy(customerAllowance => customerAllowance.get('aid', ''))
      .sort();
    return groupsOfAid.map((ids, aid) => {
      const aidLabel = allAccounts.get(aid, '');
      return (
        <Panel header={this.renderGroupHeader(aid, aidLabel)} key={`aid-${aid}`}>
          {ids.map((customerAllowance) => {
            const sid = customerAllowance.get('sid', '');
            const sidLabel = allSubscriptions.get(sid, '');
            return(
              <FormGroup key={`sid-${sid}`}>
                <Col componentClass={ControlLabel} xs={12} sm={5} smOffset={1} className="pt5">
                  {sidLabel}<br/>{`(Subscriber ID: ${sid})` }
                </Col>
                <Col xs={10} sm={5}>
                  <Field
                    id={sid}
                    value={customerAllowance.get('allowance', '')}
                    onChange={this.onChangeAllowanceValue}
                    fieldType="number"
                    min={0}
                    editable={editable}
                    suffix={getSymbolFromCurrency(currency)}
                  />
                </Col>
                <Col xs={2} sm={1} className="input-min-line-height pr0 pl0">
                  <Actions actions={this.getSidActions()} data={sid} />
                </Col>
              </FormGroup>
            );
          })}
        </Panel>
      );
    })
    .toList()
    .toArray();
  }

  render () {
    const { customer, editable } = this.props;
    const debounceFindSubscribers = debounce(500, (inputValue, callback) => {
      this.findSubscribers(inputValue, callback);
    });

    return (
      <Row>
        <Col lg={12}>
          <Form horizontal>
            <Panel header={getFieldName('Search Subscribers', 'Customer')}>
              <FormGroup>
                <Col sm={10} smOffset={1}>
                  <Field
                    fieldType="select"
                    value=''
                    onChange={this.onChangeSubscriptions}
                    editable={editable}
                    clearable={false}
                    isAsync={true}
                    cacheOptions={true}
                    placeholder="Search for allowance..."
                    loadAsyncOptions={debounceFindSubscribers}
                    noResultsText="Type to search"
                  />
                </Col>
              </FormGroup>
            </Panel>
            {( customer.get('allowances', Immutable.List()).size > 0) && (
              <Panel header={getFieldName('Allowances', 'Customer')}>
                { this.renderAllowancesValue() }
              </Panel>
            )}
          </Form>
        </Col>
      </Row>
    )
  }
}


export default connect(null)(CustomerAllowances);
