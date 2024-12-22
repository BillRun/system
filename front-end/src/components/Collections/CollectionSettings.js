import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { Panel, Col, Form, FormGroup, ControlLabel } from 'react-bootstrap';
import { ActionButtons } from '@/components/Elements';
import Field from '@/components/Field';
import {
  getConfig,
  formatSelectOptions,
} from '@/common/Util';
import {
  getCollectionSettings,
  saveCollectionSettings,
  updateCollectionSettings,
} from '@/actions/collectionsActions';
import { collectionSettingsSelector } from '@/selectors/settingsSelector';


class CollectionSettings extends Component {

  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.Map),
    httpMethods: PropTypes.instanceOf(Immutable.List),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    settings: Immutable.Map(),
    httpMethods: getConfig(['collections', 'http', 'mthods'], Immutable.List()),
  };

  componentWillMount() {
    this.props.dispatch(getCollectionSettings());
  }

  onChangeMinDeb = (e) => {
    const { value } = e.target;
    this.props.dispatch(updateCollectionSettings('min_debt', value));
  }

  onChangeChangeStateUrl = (e) => {
    const { value } = e.target;
    this.props.dispatch(updateCollectionSettings('change_state_url', value));
  }

  onChangeChangeStateMethod = (value) => {
    this.props.dispatch(updateCollectionSettings('change_state_method', value));
  }

  onSave = () => {
    this.props.dispatch(saveCollectionSettings());
  }

  render() {
    const { settings, httpMethods } = this.props;
    const minDebt = settings.get('min_debt', '');
    const changeStateUrl = settings.get('change_state_url', '');
    const changeStateMethod = settings.get('change_state_method', '');
    const methodOptions = httpMethods.map(formatSelectOptions).toArray();
    return (
      <div>
        <Col sm={12}>
          <Panel header="General settings">
            <Form horizontal>
              <FormGroup>
                <Col sm={2} componentClass={ControlLabel}>
                  Minimum debt
                </Col>
                <Col sm={6}>
                  <Field value={minDebt} onChange={this.onChangeMinDeb} fieldType="number" />
                </Col>
              </FormGroup>
            </Form>
          </Panel>

          <Panel header={
            <h4>Collection State Change<br />
              <small>
                HTTP requests will be triggered to this URL when a customer
                enters / exits from collection
              </small>
            </h4>}
          >
            <Form horizontal>
              <FormGroup>
                <Col sm={2} componentClass={ControlLabel}>
                  URL
                </Col>
                <Col sm={6}>
                  <Field value={changeStateUrl} onChange={this.onChangeChangeStateUrl} />
                </Col>
              </FormGroup>
              <FormGroup>
                <Col sm={2} componentClass={ControlLabel}>
                  HTTP Method
                </Col>
                <Col sm={6}>
                  <Field
                    fieldType="select"
                    options={methodOptions}
                    onChange={this.onChangeChangeStateMethod}
                    value={changeStateMethod}
                    clearable={false}
                  />
                </Col>
              </FormGroup>
            </Form>
          </Panel>
        </Col>
        <Col sm={12}>
          <ActionButtons onClickSave={this.onSave} hideCancel={true} />
        </Col>
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  settings: collectionSettingsSelector(state, props),
});

export default connect(mapStateToProps)(CollectionSettings);
