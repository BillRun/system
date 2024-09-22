import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import changeCase from 'change-case';
import { Form, Tabs, Tab, Row, Col, ControlLabel, Button } from 'react-bootstrap';
import RateMapping from './RateMapping';
import Field from '@/components/Field';
import { ConfirmModal } from '@/components/Elements';
import { addRateCategory, removeRateCategory } from '@/actions/inputProcessorActions';
import { rateCategoriesSelector } from '@/selectors/settingsSelector';
import { formatSelectOptions } from '@/common/Util';
import { showDanger } from '@/actions/alertsActions';

class RateMappings extends Component {
  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    settings: PropTypes.instanceOf(Immutable.Map),
    customRatingFields: PropTypes.instanceOf(Immutable.List),
    rateCategories: PropTypes.instanceOf(Immutable.List),
  }

  static defaultProps = {
    settings: Immutable.Map(),
    customRatingFields: Immutable.List(),
    rateCategories: Immutable.List(),
  };

  state = {
    newCategory: '',
    categoryToRemove: '',
  };

  getRateCategoriesInUse = () => Immutable.List(this.props.settings.get('rate_calculators', Immutable.Map()).keySeq().map(rateCategory => (rateCategory)));

  getAvailableRateCategories = () => {
    const { rateCategories } = this.props;
    const rateCategoriesInUse = this.getRateCategoriesInUse();
    return rateCategories
      .filter(category => !rateCategoriesInUse.includes(category))
      .map(formatSelectOptions)
      .toArray();
  }

  onChangeNewRateCategory = (newCategory) => {
    this.setState({ newCategory });
  }

  onAddNewRateCategory = () => {
    const { newCategory } = this.state;
    if (newCategory === undefined || newCategory === '') {
      this.props.dispatch(showDanger('Please choose a tariff category'));
    } else {
      this.props.dispatch(addRateCategory(newCategory));
    }
    this.setState({ newCategory: '' });
  }

  onRemoveRateCategory = rateCategory => () => {
    this.props.dispatch(removeRateCategory(rateCategory));
    this.onClickCloseConfirm();
  }

  onClickCloseConfirm = () => {
    this.setState({ categoryToRemove: '' });
  }

  onClickRateCategoryRemove = categoryToRemove => () => {
    this.setState({ categoryToRemove });
  }

  renderRateCategoriesSelector = () => {
    const availableCategories = this.getAvailableRateCategories();
    const { newCategory } = this.state;
    return (
      <Row>
        <Col sm={2} componentClass={ControlLabel}>Add a new tariff category</Col>
        <Col sm={4}>
          <Field
            fieldType="select"
            onChange={this.onChangeNewRateCategory}
            value={newCategory}
            options={availableCategories}
          />
        </Col>
        <Col sm={1} style={{ marginTop: 5 }}>
          <Button bsSize="xsmall" className="btn-primary" onClick={this.onAddNewRateCategory}>
            <i className="fa fa-plus" />&nbsp;Add
          </Button>
        </Col>
      </Row>
    );
  }

  renderTabTitle = rateCategory => (
    <div>
      { changeCase.sentenceCase(rateCategory) }
      {
        this.getRateCategoriesInUse().size > 1 &&
        (<Button bsSize="xsmall" bsStyle="link" onClick={this.onClickRateCategoryRemove(rateCategory)} className="close">
          <i className="fa fa-times" style={{ color: '#222222', fontSize: 16, marginTop: -5, marginRight: -5 }} />
        </Button>)
      }
    </div>
  );

  renderRateCategory = (rateCategory, index) => {
    const { settings, customRatingFields } = this.props;
    const availableUsagetypes = settings.getIn(['rate_calculators', rateCategory], Immutable.Map()).keySeq().map(usaget => (usaget));
    return (
      <Tab eventKey={index} title={this.renderTabTitle(rateCategory)} key={`rate-category-${index}`}>
        {availableUsagetypes.map((usaget, key) => (
          <div key={key} style={{ minWidth: 150 }}>
            <div className="form-group" style={{ marginTop: 20 }}>
              <div className="col-lg-3">
                <label htmlFor={usaget}>
                  { usaget }
                </label>
              </div>
              <div className="col-lg-9">
                <div className="col-lg-1" style={{ marginTop: 8 }}>
                  <i className="fa fa-long-arrow-right" />
                </div>
                <div className="col-lg-11">
                  <RateMapping
                    rateCategory={rateCategory}
                    usaget={usaget}
                    customRatingFields={customRatingFields}
                    settings={settings}
                  />
                </div>
              </div>
            </div>
            {key < availableUsagetypes.size - 1 && <div className="separator" />}
          </div>
        )).toArray()}
      </Tab>
    );
  }

  renderRemoveCategoryConfirm = () => {
    const { categoryToRemove } = this.state;
    const removeConfirmMessage = `Are you sure you want to remove rate category "${changeCase.sentenceCase(categoryToRemove)}"?`;
    return (
      <ConfirmModal
        type="delete"
        onOk={this.onRemoveRateCategory(categoryToRemove)}
        onCancel={this.onClickCloseConfirm}
        show={categoryToRemove !== ''}
        message={removeConfirmMessage}
        labelOk="Yes"
      />);
  }

  render() {
    const rateCategoriesInUse = this.getRateCategoriesInUse();
    return (
      <Form horizontal className="rateMappings">
        <div className="form-group">
          <div className="col-lg-12">
            <h4>
              Rate by
            </h4>
          </div>
          { this.renderRateCategoriesSelector() }
        </div>
        <Tabs id="rate-mapping">
          { rateCategoriesInUse.map(this.renderRateCategory) }
        </Tabs>
        { this.renderRemoveCategoryConfirm() }
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  rateCategories: rateCategoriesSelector(state, props),
});

export default connect(mapStateToProps)(RateMappings);
