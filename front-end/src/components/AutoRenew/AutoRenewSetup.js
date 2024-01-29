import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel, Col, Form, FormGroup, ControlLabel, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import { ActionButtons } from '@/components/Elements';
import {
  getConfig,
  getFieldName,
  buildPageTitle,
} from '@/common/Util';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { showSuccess } from '@/actions/alertsActions';
import {
  getAutoRenew,
  saveAutoRenew,
  updateAutoRenew,
  clearAutoRenew,
} from '@/actions/autoRenewActions';
import {
  clearItems,
} from '@/actions/entityListActions';
import {
  getList,
} from '@/actions/listActions';
import {
  itemSelector,
  idSelector,
  modeSelector,
} from '@/selectors/entitySelector';
import {
  getBucketGroupsQuery,
} from '../../common/ApiQueries';
import { lastRenewParser } from './AutoRenewUtil';


class AutoRenewSetup extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    item: PropTypes.instanceOf(Immutable.Map),
    itemId: PropTypes.string,
    mode: PropTypes.string,
    bucketGroups: PropTypes.instanceOf(Immutable.List),
  }

  static defaultProps = {
    item: Immutable.Map(),
    itemId: '',
    mode: '',
    bucketGroups: Immutable.List(),
  };

  componentWillMount() {
    this.props.dispatch(getList('bucket_groups', getBucketGroupsQuery()));
    this.fetchItem();
  }

  componentDidMount() {
    const { mode } = this.props;
    this.initDefaultValues();
    const pageTitle = buildPageTitle(mode, 'auto_renew');
    this.props.dispatch(setPageTitle(pageTitle));
  }

  componentWillReceiveProps(nextProps) {
    const { itemId } = nextProps;
    const { itemId: oldItemId } = this.props;
    if (itemId !== oldItemId) {
      this.fetchItem(itemId);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearAutoRenew());
  }

  initDefaultValues = () => {
    const { mode } = this.props;
    if (mode !== 'create') {
      this.props.dispatch(updateAutoRenew('immediate', false));
    }
  }

  fetchItem = (itemId = this.props.itemId) => {
    if (itemId) {
      this.props.dispatch(getAutoRenew(itemId)).then(this.afterItemReceived);
    }
  }

  afterItemReceived = (response) => {
    if (response.status) {
      this.initDefaultValues();
    } else {
      this.handleBack();
    }
  }

  onChangeFieldValue = (path, value) => {
    this.props.dispatch(updateAutoRenew(path, value));
  }

  onChangeInputField = (e) => {
    const { id, value } = e.target;
    this.onChangeFieldValue(id, value);
  };

  onChangeSelectField = id => (value) => {
    this.onChangeFieldValue(id, value);
  }

  onChangeDateField = id => (value) => {
    this.onChangeFieldValue(id, value.toISOString());
  }

  onChangeImmediate = (e) => {
    this.onChangeFieldValue('next_renew', undefined);
    this.onChangeInputField(e);
  }

  afterSave = (response) => {
    if (response.status) {
      this.props.dispatch(showSuccess('The auto renew charge was saved'));
      this.handleBack(true);
    }
  }

  handleSave = () => {
    const { item, mode } = this.props;
    this.props.dispatch(saveAutoRenew(item, mode)).then(this.afterSave);
  };

  handleBack = (itemWasChanged = false) => {
    const itemsType = getConfig(['systemItems', 'auto_renew', 'itemsType'], '');
    if (itemWasChanged) {
      this.props.dispatch(clearItems(itemsType)); // refetch items list because item was (changed in / added to) list
    }
    this.props.router.push(`/${itemsType}`);
  }

  getBucketGroupsOptions = () => {
    const { bucketGroups } = this.props;
    return bucketGroups.map(bucketGroup => ({
      value: bucketGroup.get('name', ''),
      label: bucketGroup.get('description', bucketGroup.get('name', '')),
    })).toArray();
  }

  getIntervalOptions = () => [
    { value: 'month', label: 'Monthly' },
    { value: 'day', label: 'Daily' },
  ]

  render() {
    const { item, mode } = this.props;
    const bucketGroupsOptions = this.getBucketGroupsOptions();
    const intervalOptions = this.getIntervalOptions();
    const isNew = (mode === 'create');
    const nextRenew = moment(new Date(item.get('next_renew')));
    const minNextRenew = moment(new Date()).add(1, 'days').startOf('day');
    const checkboxStyle = { marginTop: 10 };

    return (
      <div className="AutoRenewSetup">
        <Panel>
          <Form horizontal>

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('aid', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  id="aid"
                  value={item.get('aid', '')}
                  onChange={this.onChangeInputField}
                  fieldType="number"
                />
              </Col>
            </FormGroup>

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('sid', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  id="sid"
                  value={item.get('sid', '')}
                  onChange={this.onChangeInputField}
                  fieldType="number"
                />
              </Col>
            </FormGroup>

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('bucket_group', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  fieldType="select"
                  value={item.get('bucket_group', '')}
                  onChange={this.onChangeSelectField('bucket_group')}
                  options={bucketGroupsOptions}
                />
              </Col>
            </FormGroup>

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('cycles', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  id="cycles"
                  value={item.get('cycles', '')}
                  onChange={this.onChangeInputField}
                  fieldType="number"
                  disabled={!isNew}
                />
              </Col>
            </FormGroup>

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('cycles_remaining', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  id="cycles_remaining"
                  value={item.get('cycles_remaining', item.get('cycles', ''))}
                  onChange={this.onChangeInputField}
                  fieldType="number"
                  disabled={isNew}
                />
              </Col>
            </FormGroup>

            {
              isNew &&
              (<FormGroup>
                <Col lg={2} md={2} componentClass={ControlLabel}>Immediate Charge</Col>
                <Col lg={7} md={7} style={checkboxStyle}>
                  <Field
                    id="immediate"
                    value={item.get('immediate', false)}
                    onChange={this.onChangeImmediate}
                    fieldType="checkbox"
                  />
                  {
                    item.get('immediate', false) &&
                    (<HelpBlock>
                      The cusomter first charge will occur as soon as you will click save
                    </HelpBlock>)
                }
                </Col>
              </FormGroup>)
            }

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('next_renew', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  id="next_renew"
                  value={nextRenew}
                  minDate={minNextRenew}
                  onChange={this.onChangeDateField('next_renew')}
                  disabled={item.get('immediate', false)}
                  fieldType="date"
                />
              </Col>
            </FormGroup>

            {
              !isNew &&
              (<FormGroup>
                <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('last_renew', 'autorenew')}</Col>
                <Col lg={7} md={7}>
                  <Field
                    id="last_renew"
                    value={lastRenewParser(item)}
                    editable={false}
                  />
                </Col>
              </FormGroup>)
            }

            <FormGroup>
              <Col lg={2} md={2} componentClass={ControlLabel}>{getFieldName('interval', 'autorenew')}</Col>
              <Col lg={7} md={7}>
                <Field
                  fieldType="select"
                  value={item.get('interval', '')}
                  onChange={this.onChangeSelectField('interval')}
                  options={intervalOptions}
                />
              </Col>
            </FormGroup>

          </Form>
        </Panel>
        <ActionButtons onClickCancel={this.handleBack} onClickSave={this.handleSave} />
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  itemId: idSelector(state, props, 'autorenew'),
  item: itemSelector(state, props, 'autorenew'),
  mode: modeSelector(state, props, 'autorenew'),
  bucketGroups: state.list.get('bucket_groups'),
});

export default withRouter(connect(mapStateToProps)(AutoRenewSetup));
