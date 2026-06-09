import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { FormGroup, Col, Row, Panel, Button } from 'react-bootstrap';
import changeCase from 'change-case';
import ComputedRate from './ComputedRate';
import Field from '@/components/Field';
import Help from '../../Help';
import { getConfig, parseConfigSelectOptions } from '@/common/Util';
import { updateSetting, saveSettings } from '@/actions/settingsActions';
import { showWarning } from '@/actions/alertsActions';
import { ModalWrapper } from '@/components/Elements';
import {
  setRatingField,
  setLineKey,
  setComputedLineKey,
  unsetComputedLineKey,
  addRatingField,
  addRatingPriorityField,
  removeRatingPriorityField,
  removeRatingField,
} from '@/actions/inputProcessorActions';
import {
  customerIdentificationFieldsPlaySelector,
} from '@/selectors/inputProcessorSelector';
import {
  inputProcessorlineKeyOptionsSelector,
  getFieldsWithPreFunctions,
  inputProcessorComputedlineKeyOptionsSelector,
} from '@/selectors/settingsSelector';


class RateMapping extends Component {
  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    usaget: PropTypes.string.isRequired,
    rateCategory: PropTypes.string.isRequired,
    settings: PropTypes.instanceOf(Immutable.Map),
    lineKeyOptions: PropTypes.instanceOf(Immutable.List),
    customRatingFields: PropTypes.instanceOf(Immutable.List),
    rateCalculators: PropTypes.instanceOf(Immutable.List),
    plays: PropTypes.instanceOf(Immutable.Set),
    computedlineKeyOptions: PropTypes.array,
  }
  static defaultProps = {
    settings: Immutable.Map(),
    lineKeyOptions: Immutable.List(),
    customRatingFields: Immutable.List(),
    rateCalculators: Immutable.List(),
    plays: Immutable.Set(),
    computedlineKeyOptions: [],
  };

  state = {
    computedLineKey: null,
    openRateCalculators: [0],
  };

  componentDidMount = () => {
    const { settings, rateCategory } = this.props;
    const availableUsagetypes = settings.getIn(['rate_calculators', rateCategory], Immutable.Map()).keySeq().map(usaget => (usaget));
    availableUsagetypes.forEach((usaget) => {
      const calcs = settings.getIn(['rate_calculators', rateCategory, usaget], Immutable.List());
      if (calcs.size === 0) {
        this.onAddRating({ target: { dataset: { ratecategory: rateCategory, usaget } } });
      }
    });
  };

  sortFiledByPlay = field => (field.get('plays', Immutable.List()).isEmpty() ? 1 : -1)

  filterFiledByPlay = (field) => {
    const { plays } = this.props;
    const fieldPlays = field.get('plays', Immutable.List());
    if (fieldPlays.isEmpty()) {
      return true;
    }
    return plays.intersect(Immutable.Set(fieldPlays)).size > 0;
  }

  getCustomRatingFields = () => {
    const { customRatingFields } = this.props;
    return customRatingFields
      .filter(field => (field.get('field_name', '').startsWith('params.')))
      .filter(this.filterFiledByPlay)
      .sort(this.sortFiledByPlay)
      .map(field => ({
        value: field.get('field_name', ''),
        label: field.get('title', ''),
      }))
      .toJS();
  }

  getRatingTypes = () => getConfig(['rates', 'paramsConditions'], Immutable.List())
    .map(parseConfigSelectOptions)
    .toArray();

  onChangeAdditionalParamRating = (rateCategory, usaget, priority, index, type) => (value) => {
    const eModified = {
      target: {
        dataset: {
          rate_key: value,
          ratecategory: rateCategory,
          usaget,
          priority,
          index,
        },
        value: type,
        custom: true,
      },
    };
    this.onSetRating(eModified);
  }

  onChangeAdditionalParamRatingType = (value, rateCategory, usaget, priority, index) => (type) => {
    this.onChangeAdditionalParamRating(rateCategory, usaget, priority, index, type)(value);
  }

  getRateCalculatorFields = () => {
    const { lineKeyOptions } = this.props;
    return lineKeyOptions
      .map(field => ({
        value: field.get('value', ''),
        label: field.get('label', ''),
      }))
      .toJS();
  }

  addNewRatingCustomField = (fieldName, title, type) => {
    const { customRatingFields } = this.props;
    this.props.dispatch(updateSetting('rates', ['fields'], customRatingFields.push(Immutable.Map({
      field_name: fieldName,
      title,
      multiple: type === 'longestPrefix',
      display: true,
      editable: true,
    }))));
    return this.props.dispatch(saveSettings('rates'));
  };

  onSetRating = (e) => {
    const { customRatingFields } = this.props;
    const { dataset: { ratecategory, usaget, priority, index }, value, custom } = e.target;
    let { dataset: { rate_key: rateKey } } = e.target;
    let newRateKey = changeCase.snakeCase(rateKey);
    newRateKey = newRateKey.replace('params.', '');
    newRateKey = newRateKey.replace('params_', '');
    newRateKey = `params.${newRateKey}`;
    const isNewField = custom && (rateKey !== '') && !(customRatingFields.find(field => field.get('field_name', '') === newRateKey));
    if (isNewField) {
      this.addNewRatingCustomField(newRateKey, rateKey, value);
      rateKey = newRateKey;
    } else {
      const rateExists = customRatingFields.find(field => field.get('field_name', '') === newRateKey);
      if (rateExists) {
        if (custom && rateKey !== '' && rateKey !== rateExists.title && rateKey !== newRateKey) {
          this.props.dispatch(showWarning(`Product param ${rateKey} already exists as custom field ${newRateKey}`));
          rateKey = rateExists.title;
        }
      }
    }
    const idx = parseInt(index);
    this.props.dispatch(setRatingField(ratecategory, usaget, priority, idx, rateKey, value));
  }

  onAddRating = (e) => {
    const { dataset: { ratecategory, usaget, priority } } = e.target;
    this.props.dispatch(addRatingField(ratecategory, usaget, priority));
  }

  onRemoveRating = (e) => {
    const { dataset: { ratecategory, usaget, priority, index } } = e.target;
    this.props.dispatch(removeRatingField(ratecategory, usaget, priority, index));
  }

  onRemoveRatingPriority = (rateCategory, usaget, priority) => () => {
    this.props.dispatch(removeRatingPriorityField(rateCategory, usaget, priority));
  }

  onSetLineKey = (rateCategory, usaget, priority, index, value) => {
    this.props.dispatch(setLineKey(rateCategory, usaget, priority, index, value));
  }

  onSetComputedLineKey = (rateCategory, usaget, priority, index, paths, values) => {
    this.props.dispatch(setComputedLineKey(rateCategory, usaget, priority, index, paths, values));
  }

  onUnsetComputedLineKey = (rateCategory, usaget, priority, index) => {
    this.props.dispatch(unsetComputedLineKey(rateCategory, usaget, priority, index));
  }

  getComputedLineKeyObject = (rateCategory, usaget, priority, index, calc = Immutable.Map()) => (
    Immutable.Map({
      rateCategory,
      usaget,
      priority,
      index,
      type: calc.getIn(['computed', 'type'], 'regex'),
      line_keys: calc.getIn(['computed', 'line_keys'], Immutable.List()),
      operator: calc.getIn(['computed', 'operator'], ''),
      must_met: calc.getIn(['computed', 'must_met'], false),
      projection: Immutable.Map({
        on_true: Immutable.Map({
          key: calc.getIn(['computed', 'projection', 'on_true', 'key'], 'condition_result'),
          regex: calc.getIn(['computed', 'projection', 'on_true', 'regex'], ''),
          value: calc.getIn(['computed', 'projection', 'on_true', 'value'], ''),
        }),
        on_false: Immutable.Map({
          key: calc.getIn(['computed', 'projection', 'on_false', 'key'], 'condition_result'),
          regex: calc.getIn(['computed', 'projection', 'on_false', 'regex'], ''),
          value: calc.getIn(['computed', 'projection', 'on_false', 'value'], ''),
        }),
      }),
    })
  );

  onChangeLineKey = (rateCategory, usaget, priority, index) => (value) => {
    if (value === 'computed') {
      this.setState({
        computedLineKey: this.getComputedLineKeyObject(rateCategory, usaget, priority, index),
      });
    } else {
      this.setState({ computedLineKey: null });
      this.onUnsetComputedLineKey(rateCategory, usaget, priority, index);
    }
    this.onSetLineKey(rateCategory, usaget, priority, index, value);
  }

  onEditComputedLineKey = (calc, rateCategory, usaget, priority, index) => () => {
    this.setState({
      computedLineKey: this.getComputedLineKeyObject(rateCategory, usaget, priority, index, calc),
    });
  }

  onSaveComputedLineKey = () => {
    const { computedLineKey } = this.state;
    const basePath = ['computed'];
    const paths = [
      [...basePath, 'line_keys'],
      [...basePath, 'operator'],
      [...basePath, 'type'],
      [...basePath, 'must_met'],
      [...basePath, 'projection'],
    ];
    const values = [
      computedLineKey.get('line_keys', Immutable.List()),
      computedLineKey.get('operator', ''),
      computedLineKey.get('type', ''),
      computedLineKey.get('must_met', false),
      computedLineKey.get('projection', Immutable.Map()),
    ];
    this.onSetComputedLineKey(computedLineKey.get('rateCategory'), computedLineKey.get('usaget'), computedLineKey.get('priority'), computedLineKey.get('index'), paths, values);
    this.setState({ computedLineKey: null });
  }

  onChangeComputedMustMet = (e) => {
    const { value } = e.target;
    const { computedLineKey } = this.state;
    const newComputedLineKey = computedLineKey.withMutations((computedLineKeyWithMutations) => {
      computedLineKeyWithMutations.set('must_met', value);
      if (value) {
        computedLineKeyWithMutations.setIn(['projection', 'on_false'], Immutable.Map());
      }
    });
    this.setState({
      computedLineKey: newComputedLineKey,
    });
  }

  onChangeHardCodedValue = path => (e) => {
    const { value } = e.target;
    this.onChangeComputedLineKey(path)(value);
  }

  onChangeComputedLineKey = path => (value) => {
    const { computedLineKey } = this.state;
    const newComputedLineKey = computedLineKey.withMutations((computedLineKeyWithMutations) => {
      computedLineKeyWithMutations.setIn(path, value);
      const key = path[1];
      if (path[0] === 'operator') {
        const changeFromRegex = computedLineKey.get('operator', '') === '$regex' && value !== '$regex';
        const changeToRegex = computedLineKey.get('operator', '') !== '$regex' && value === '$regex';
        const operatorExists = value === '$exists' || value === '$existsFalse';
        if (changeFromRegex || changeToRegex || operatorExists) {
          computedLineKeyWithMutations.deleteIn(['line_keys', 1]);
          computedLineKeyWithMutations.deleteIn(['line_keys', 0, 'regex']);
        }
      }
      if (value === 'hard_coded') {
        computedLineKeyWithMutations.deleteIn(['projection', key, 'value']);
      } else if (value === 'condition_result') {
        computedLineKeyWithMutations.deleteIn(['projection', key, 'value']);
        computedLineKeyWithMutations.deleteIn(['projection', key, 'regex']);
      }
      if (path[2] === 'key') {
        let preFunctionPath = [...path];
        preFunctionPath[preFunctionPath.length-1] = 'preFunction';
        const preFunctionFields = getFieldsWithPreFunctions().find(preFunctionField => (
          preFunctionField.get('value') === value
        ), null, false);
          if (preFunctionFields === false) {
            computedLineKeyWithMutations.deleteIn(preFunctionPath);
          } else {
            let regexPath = [...path];
            regexPath[regexPath.length-1] = 'regex';
            computedLineKeyWithMutations.setIn(path, preFunctionFields.get('preFunctionValue'));
            computedLineKeyWithMutations.setIn(preFunctionPath, preFunctionFields.get('preFunction'));
            computedLineKeyWithMutations.deleteIn(regexPath);
          }
      }
    });
    this.setState({
      computedLineKey: newComputedLineKey,
    });
  }

  onChangeComputedLineKeyType = (e) => {
    const { value } = e.target;
    const { computedLineKey } = this.state;
    const newComputedLineKey = computedLineKey.withMutations((computedLineKeyWithMutations) => {
      computedLineKeyWithMutations.set('type', value);
      if (value === 'regex') {
        computedLineKeyWithMutations.deleteIn(['line_keys', 1]);
        computedLineKeyWithMutations.delete('operator');
        computedLineKeyWithMutations.delete('must_met');
        computedLineKeyWithMutations.delete('projection');
      } else {
        const preFunction = getFieldsWithPreFunctions().find(preFunctionField => (
          preFunctionField.get('preFunction') === computedLineKey.getIn(['line_keys', 0, 'preFunction'], '')
          && preFunctionField.get('preFunctionValue') === computedLineKey.getIn(['line_keys', 0, 'key'], '')
        ), null, false);
        if (preFunction !== false) {
          computedLineKeyWithMutations.deleteIn(['line_keys', 0, 'key']);
          computedLineKeyWithMutations.deleteIn(['line_keys', 0, 'preFunction']);
        }
      }
    });
    this.setState({
      computedLineKey: newComputedLineKey,
    });
  }

  onHideComputedLineKey = () => {
    this.setState({ computedLineKey: null });
  }

  renderComputedLineKeyDesc = (calc, rateCategory, usaget, priority, index) => {
    const { computedlineKeyOptions } = this.props;
    if (calc.get('line_key', '') !== 'computed') {
      return null;
    }
    const op = calc.getIn(['computed', 'operator'], '');
    const opLabel = getConfig(['rates', 'conditions'], Immutable.Map())
      .find(cond => cond.get('id', '') === op, null, Immutable.Map())
      .get('title', '');
    const selectedOptionFirst = computedlineKeyOptions.find(computedlineKeyOption => computedlineKeyOption.value === calc.getIn(['computed', 'line_keys', 0, 'key'], ''))
    const defaultLabelFirst = (typeof selectedOptionFirst !== 'undefined') ? selectedOptionFirst.label : calc.getIn(['computed', 'line_keys', 0, 'key'], '')
    const lineKeyLabel_first = getFieldsWithPreFunctions().find(preFunctionField => (
      preFunctionField.get('preFunction') === calc.getIn(['computed', 'line_keys', 0, 'preFunction'], '')
      && preFunctionField.get('preFunctionValue') === calc.getIn(['computed', 'line_keys', 0, 'key'], '')
    ), null, Immutable.Map()).get('label', defaultLabelFirst);
    const selectedOptionSecond = computedlineKeyOptions.find(computedlineKeyOption => computedlineKeyOption.value === calc.getIn(['computed', 'line_keys', 1, 'key'], ''))
    const defaultLabelSecond = (typeof selectedOptionSecond !== 'undefined') ? selectedOptionSecond.label : calc.getIn(['computed', 'line_keys', 1, 'key'], '')
    return (
      <h4>
        <small>
          {`${lineKeyLabel_first} ${opLabel} ${defaultLabelSecond}`}
          <Button onClick={this.onEditComputedLineKey(calc, rateCategory, usaget, priority, index)} bsStyle="link">
            <i className="fa fa-fw fa-pencil" />
          </Button>
        </small>
      </h4>
    );
  }

  openRateCalculator = priority => () => {
    const { openRateCalculators } = this.state;
    openRateCalculators.push(priority);
    this.setState({ openRateCalculators });
  }

  closeRateCalculator = priority => () => {
    const { openRateCalculators } = this.state;
    openRateCalculators.splice(openRateCalculators.indexOf(priority), 1);
    this.setState({ openRateCalculators });
  }

  onAddRatingPriority = (rateCategory, usaget) => () => {
    const { rateCalculators } = this.props;
    this.openRateCalculator(rateCalculators.size)();
    this.props.dispatch(addRatingPriorityField(rateCategory, usaget));
  }
  
  getLineKeyValue = (calc) => {
    const preFunctionFields = getFieldsWithPreFunctions().find(preFunctionField => (
      preFunctionField.get('preFunction') === calc.get('preFunction', '')
      && preFunctionField.get('preFunctionValue') === calc.get('line_key', '')
    ), null, Immutable.Map());
    return preFunctionFields.get('value', calc.get('line_key', ''));
  }

  getRateCalculatorsForPriority = (rateCategory, usaget, priority, calcs) => {
    return calcs.map((calc, calcKey) => {
      let selectedRadio = 3;
      if (calc.get('rate_key', '') === 'key') {
        selectedRadio = 1;
      } else if (calc.get('rate_key', '') === 'usaget') {
        selectedRadio = 2;
      }
      return (
        <div key={`rate-calc-${rateCategory}-${priority}-${calcKey}`}>
          <Row key={`rate-calc-row-${rateCategory}-${priority}-${calcKey}`}>
            <Col sm={3} style={{ paddingRight: 0 }}>
              <FormGroup style={{ margin: 0 }}>
                <Field
                  fieldType="select"
                  onChange={this.onChangeLineKey(rateCategory, usaget, priority, calcKey)}
                  value={this.getLineKeyValue(calc)}
                  options={this.getRateCalculatorFields()}
                />
                { this.renderComputedLineKeyDesc(calc, rateCategory, usaget, priority, calcKey) }
              </FormGroup>
            </Col>

            <Col sm={7} style={{ paddingRight: 0 }}>
              <FormGroup style={{ margin: 0, paddingLeft: 13 }}>
                <input
                  type="radio"
                  name={`${rateCategory}-${usaget}-${priority}-${calcKey}-type`}
                  id={`${rateCategory}-${usaget}-${priority}-${calcKey}-by-rate-key`}
                  value="match"
                  data-ratecategory={rateCategory}
                  data-usaget={usaget}
                  data-rate_key="key"
                  data-index={calcKey}
                  data-priority={priority}
                  checked={selectedRadio === 1}
                  onChange={this.onSetRating}
                />&nbsp;
                <label htmlFor={`${rateCategory}-${usaget}-${priority}-${calcKey}-by-rate-key`} style={{ verticalAlign: 'middle' }}>By product key</label>
              </FormGroup>

              <FormGroup style={{ margin: 0, paddingLeft: 13 }}>
                <input
                  type="radio"
                  name={`${rateCategory}-${usaget}-${priority}-${calcKey}-type`}
                  id={`${rateCategory}-${usaget}-${priority}-${calcKey}-by-rate-usaget`}
                  value="match"
                  data-ratecategory={rateCategory}
                  data-usaget={usaget}
                  data-rate_key="usaget"
                  data-index={calcKey}
                  data-priority={priority}
                  checked={selectedRadio === 2}
                  onChange={this.onSetRating}
                />&nbsp;
                <label htmlFor={`${rateCategory}-${usaget}-${priority}-${calcKey}-by-rate-usaget`} style={{ verticalAlign: 'middle' }}>By product unit type</label>
              </FormGroup>

              <FormGroup style={{ margin: 0 }}>
                <div className="input-group">
                  <div className="input-group-addon">
                    <input
                      type="radio"
                      name={`${rateCategory}-${usaget}-${priority}-${calcKey}-type`}
                      id={`${rateCategory}-${usaget}-${priority}-${calcKey}-by-param`}
                      value="match"
                      data-ratecategory={rateCategory}
                      data-usaget={usaget}
                      checked={selectedRadio === 3}
                      data-rate_key=""
                      data-index={calcKey}
                      data-priority={priority}
                      onChange={this.onSetRating}
                    />&nbsp;
                    <label htmlFor={`${rateCategory}-${usaget}-${priority}-${calcKey}-by-param`} style={{ verticalAlign: 'middle' }}>By product param</label>
                    <Help contents="This field needs to be configured in the 'Additional Parameters' of a Product" />
                  </div>
                  <Field
                    fieldType="select"
                    onChange={this.onChangeAdditionalParamRating(rateCategory, usaget, priority, calcKey, calc.get('type', ''))}
                    value={selectedRadio !== 3 ? '' : calc.get('rate_key', '')}
                    disabled={selectedRadio !== 3}
                    options={this.getCustomRatingFields()}
                    allowCreate={true}
                  />
                  <Field
                    fieldType="select"
                    onChange={this.onChangeAdditionalParamRatingType(calc.get('rate_key', ''), rateCategory, usaget, priority, calcKey)}
                    disabled={selectedRadio !== 3}
                    value={selectedRadio !== 3 ? '' : calc.get('type', '')}
                    options={this.getRatingTypes()}
                  />
                </div>
              </FormGroup>
            </Col>

            <Col xs={2}>
              { calcKey > 0 &&
                <FormGroup style={{ margin: 0 }}>
                  <div style={{ width: '100%', height: 39 }}>
                    <Button onClick={this.onRemoveRating} data-ratecategory={rateCategory} data-usaget={usaget} data-index={calcKey} data-priority={priority} bsSize="small" className="pull-left" ><i className="fa fa-trash-o danger-red" />&nbsp;Remove</Button>
                  </div>
                </FormGroup>
              }
            </Col>
          </Row>
          <hr />
        </div>
      );
    }).toArray();
  }

  getAddRatingButton = (rateCategory, usaget, priority) => (
    <Button
      bsSize="xsmall"
      className="btn-primary"
      data-ratecategory={rateCategory}
      data-usaget={usaget}
      data-priority={priority}
      onClick={this.onAddRating}
    >
      <i className="fa fa-plus" />&nbsp;Add
    </Button>
  );

  getAddRatingPriorityButton = (rateCategory, usaget) => (
    <Button
      bsSize="xsmall"
      className="btn-primary"
      onClick={this.onAddRatingPriority(rateCategory, usaget)}
    >
      <i className="fa fa-plus" />&nbsp;Add Next Priority
    </Button>
  );

  getRemoveRatingPriorityButton = (rateCategory, usaget, priority) => (
    <Button
      bsStyle="link"
      bsSize="xsmall"
      onClick={this.onRemoveRatingPriority(rateCategory, usaget, priority)}
    >
      <i className="fa fa-fw fa-trash-o danger-red" />
    </Button>
  );

  renderComputedRatePopup = () => {
    const { settings } = this.props;
    const { computedLineKey } = this.state;
    if (!computedLineKey) {
      return null;
    }
    const title = 'Computed Rate Key';
    return (
      <ModalWrapper title={title} show={true} onOk={this.onSaveComputedLineKey} onHide={this.onHideComputedLineKey} labelOk="OK" modalSize="large">
        <ComputedRate
          computedLineKey={computedLineKey}
          settings={settings}
          onChangeComputedLineKeyType={this.onChangeComputedLineKeyType}
          onChangeComputedLineKey={this.onChangeComputedLineKey}
          onChangeComputedMustMet={this.onChangeComputedMustMet}
          onChangeHardCodedValue={this.onChangeHardCodedValue}
        />
      </ModalWrapper>
    );
  }

  render() {
    const { rateCategory, usaget, rateCalculators } = this.props;
    const { openRateCalculators } = this.state;  
    const noRemoveStyle = { paddingLeft: 45 };
    return (
      <div>
        { this.renderComputedRatePopup() }
        { rateCalculators.map((calcs, priority) => {
          const showRemove = priority > 0;
          const actionsStyle = showRemove ? {} : noRemoveStyle;
          const filters = calcs.get('filters', Immutable.List());
          return (
            <div key={`rate-calculator-${usaget}-${priority}`}>
              <Row>
                <Col sm={10}>{`Priority ${priority + 1}`}</Col>
                <Col sm={2} style={actionsStyle}>
                  {showRemove && this.getRemoveRatingPriorityButton(rateCategory, usaget, priority)}
                  {openRateCalculators.includes(priority) ? (
                    <Button onClick={this.closeRateCalculator(priority)} bsStyle="link">
                      <i className="fa fa-fw fa-minus" />
                    </Button>
                  ) : (
                    <Button onClick={this.openRateCalculator(priority)} bsStyle="link">
                      <i className="fa fa-fw fa-plus" />
                    </Button>
                  )}
                </Col>
              </Row>
              <Panel collapsible expanded={this.state.openRateCalculators.includes(priority)}>
                { this.getRateCalculatorsForPriority(rateCategory, usaget, priority, filters) }
                { this.getAddRatingButton(rateCategory, usaget, priority) }
              </Panel>
            </div>
          );
        }) }
        { this.getAddRatingPriorityButton(rateCategory, usaget) }
      </div>);
  }
}

const mapStateToProps = (state, props) => ({
  plays: customerIdentificationFieldsPlaySelector(state, props),
  rateCalculators: props.settings.getIn(['rate_calculators', props.rateCategory, props.usaget, 'priorities']),
  lineKeyOptions: inputProcessorlineKeyOptionsSelector(state, props),
  computedlineKeyOptions: inputProcessorComputedlineKeyOptionsSelector(state, props),
});

export default connect(mapStateToProps)(RateMapping);
