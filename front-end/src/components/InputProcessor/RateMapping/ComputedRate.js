import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Help from '@/components/Help';
import Field from '@/components/Field';
import { getConfig, formatSelectOptions, parseConfigSelectOptions } from '@/common/Util';
import { getSettings } from '@/actions/settingsActions';
import {
  inputProcessorComputedlineKeyOptionsSelector,
  getFieldsWithPreFunctions,
} from '@/selectors/settingsSelector';

class ComputedRate extends Component {
  static propTypes = {
    computedLineKey: PropTypes.instanceOf(Immutable.Map),
    settings: PropTypes.instanceOf(Immutable.Map),
    computedlineKeyOptions: PropTypes.array,
    onChangeComputedLineKeyType: PropTypes.func,
    onChangeComputedLineKey: PropTypes.func,
    onChangeComputedMustMet: PropTypes.func,
    onChangeHardCodedValue: PropTypes.func,
  }
  static defaultProps = {
    computedLineKey: Immutable.Map(),
    settings: Immutable.Map(),
    computedlineKeyOptions: [],
    onChangeComputedLineKeyType: () => {},
    onChangeComputedLineKey: () => {},
    onChangeComputedMustMet: () => {},
    onChangeHardCodedValue: () => {},
  };

  componentWillMount() {
    this.props.dispatch(getSettings([
      'lines',
    ]));
  }

  getConditionResultProjectOptions = () => [
    'condition_result',
    'hard_coded',
  ].map(formatSelectOptions);

  getRateConditions = () => getConfig(['rates', 'conditions'], Immutable.List())
    .map(parseConfigSelectOptions)
    .toArray();

  onChangeComputedLineKeyHardCodedKey = (e) => {
    const { value } = e.target;
    const callback = this.props.onChangeComputedLineKey(['line_keys', 1, 'key']);
    callback(value);
  }

  render() {
    const { computedLineKey, computedlineKeyOptions } = this.props;
    if (!computedLineKey) {
      return null;
    }
    const regexHelper = 'In case you want to run a regular expression on the computed field before calculating the rate';
    const mustMetHelper = 'This means than in case the condition is not met - a rate will not be found';
    const computedTypeRegex = computedLineKey.get('type', 'regex') === 'regex';
    const operatorExists = computedLineKey.get('operator', '') === '$exists' || computedLineKey.get('operator', '') === '$existsFalse';
    const preFunction = getFieldsWithPreFunctions().find(preFunctionField => (
      preFunctionField.get('preFunction') === computedLineKey.getIn(['line_keys', 0, 'preFunction'], '')
      && preFunctionField.get('preFunctionValue') === computedLineKey.getIn(['line_keys', 0, 'key'], '')
    ), null, false);
    const lineKeyValue = preFunction === false ? computedLineKey.getIn(['line_keys', 0, 'key'], '') : preFunction.get('value', computedLineKey.getIn(['line_keys', 0, 'preFunction'], ''));
    const checkboxStyle = { marginTop: 10 };
    const conditionOption = this.getConditionResultProjectOptions().concat(computedlineKeyOptions);
    return (
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>
            Computation Type
          </Col>
          <Col sm={10}>
            <span>
              <span style={{ display: 'inline-block', marginRight: 20 }}>
              <Field
                fieldType="radio"
                name="computed-type"
                id="computed-type-regex"
                value="regex"
                checked={computedTypeRegex}
                onChange={this.props.onChangeComputedLineKeyType}
                label="Regex"
              />
            </span>
            <span style={{ display: 'inline-block' }}>
              <Field
                fieldType="radio"
                name="computed-type"
                id="computed-type-condition"
                value="condition"
                checked={!computedTypeRegex}
                onChange={this.props.onChangeComputedLineKeyType}
                label="Condition"
              />
            </span>
          </span>
          </Col>
        </FormGroup>
        <div className="separator" />
        <FormGroup key="computed-field-1">
          <Col sm={2} componentClass={ControlLabel}>{computedTypeRegex ? 'Field' : 'First Field' }</Col>
          <Col sm={5}>
            <Field
              fieldType="select"
              onChange={this.props.onChangeComputedLineKey(['line_keys', 0, 'key'])}
              value={lineKeyValue}
              options={computedlineKeyOptions}
              allowCreate={true}
            />
          </Col>
          <Col sm={5}>
            <Field
              value={computedLineKey.getIn(['line_keys', 0, 'regex'], '')}
              disabledValue={''}
              onChange={this.props.onChangeComputedLineKey(['line_keys', 0, 'regex'])}
              disabled={computedLineKey.getIn(['line_keys', 0, 'key'], '') === '' || operatorExists || preFunction !== false}
              label={<span>Regex<Help contents={regexHelper} /></span>}
              fieldType="toggeledInput"
            />
          </Col>
        </FormGroup>
        { !computedTypeRegex && (
          <>
            <FormGroup key="computed-operator">
              <Col sm={2} componentClass={ControlLabel}>Operator</Col>
              <Col sm={5}>
                <Field
                  fieldType="select"
                  onChange={this.props.onChangeComputedLineKey(['operator'])}
                  value={computedLineKey.get('operator', '')}
                  options={this.getRateConditions()}
                />
              </Col>
            </FormGroup>
            <FormGroup key="computed-field-2">
              <Col sm={2} componentClass={ControlLabel}>Second Field</Col>
              <Col sm={5}>
                { computedLineKey.get('operator', '') === '$regex' && (
                  <Field
                    value={computedLineKey.getIn(['line_keys', 1, 'key'], '')}
                    onChange={this.onChangeComputedLineKeyHardCodedKey}
                  />
                )}
                { computedLineKey.get('operator', '') !== '$regex' && (
                  <Field
                    fieldType="select"
                    onChange={this.props.onChangeComputedLineKey(['line_keys', 1, 'key'])}
                    value={computedLineKey.getIn(['line_keys', 1, 'key'], '')}
                    options={computedlineKeyOptions}
                    disabled={operatorExists}
                  />
                )}
              </Col>
              <Col sm={5}>
                <Field
                  value={computedLineKey.getIn(['line_keys', 1, 'regex'], '')}
                  disabledValue={''}
                  onChange={this.props.onChangeComputedLineKey(['line_keys', 1, 'regex'])}
                  disabled={computedLineKey.getIn(['line_keys', 1, 'key'], '') === '' || computedLineKey.get('operator', '') === '$regex'}
                  label={<span>Regex<Help contents={regexHelper} /></span>}
                  fieldType="toggeledInput"
                />
              </Col>
            </FormGroup>
            <FormGroup key="computed-must-met">
              <Col componentClass={ControlLabel} sm={2}>
                Must met?
                <Help contents={mustMetHelper} />
              </Col>
              <Col sm={5} style={checkboxStyle}>
                <div className="inline">
                  <Field
                    fieldType="checkbox"
                    id="computed-must-met"
                    value={computedLineKey.get('must_met', false)}
                    onChange={this.props.onChangeComputedMustMet}
                  />
                </div>
              </Col>
            </FormGroup>
            <FormGroup key="computed-cond-project-true">
              <Col sm={2} componentClass={ControlLabel}>Value when True</Col>
              <Col sm={5}>
                <Field
                  fieldType="select"
                  onChange={this.props.onChangeComputedLineKey(['projection', 'on_true', 'key'])}
                  value={computedLineKey.getIn(['projection', 'on_true', 'key'], 'condition_result')}
                  options={conditionOption}
                />
              </Col>
              {['hard_coded'].includes(computedLineKey.getIn(['projection', 'on_true', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={computedLineKey.getIn(['projection', 'on_true', 'value'], '')}
                    onChange={this.props.onChangeHardCodedValue(['projection', 'on_true', 'value'])}
                  />
                </Col>
              )}
              {!['', 'hard_coded', 'condition_result'].includes(computedLineKey.getIn(['projection', 'on_true', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={computedLineKey.getIn(['projection', 'on_true', 'regex'], '')}
                    disabledValue={''}
                    onChange={this.props.onChangeComputedLineKey(['projection', 'on_true', 'regex'])}
                    disabled={computedLineKey.getIn(['projection', 'on_true', 'key'], '') === ''}
                    label="Regex"
                    fieldType="toggeledInput"
                  />
                </Col>
              )}
            </FormGroup>
            <FormGroup key="computed-cond-project-false">
              <Col sm={2} componentClass={ControlLabel}>Value when False</Col>
              <Col sm={5}>
                <Field
                  fieldType="select"
                  onChange={this.props.onChangeComputedLineKey(['projection', 'on_false', 'key'])}
                  value={computedLineKey.getIn(['projection', 'on_false', 'key'], 'condition_result')}
                  options={conditionOption}
                  disabled={computedLineKey.get('must_met', false)}
                />
              </Col>
              {['hard_coded'].includes(computedLineKey.getIn(['projection', 'on_false', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={computedLineKey.getIn(['projection', 'on_false', 'value'], '')}
                    onChange={this.props.onChangeHardCodedValue(['projection', 'on_false', 'value'])}
                  />
                </Col>
              )}
              {!['', 'hard_coded', 'condition_result'].includes(computedLineKey.getIn(['projection', 'on_false', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={computedLineKey.getIn(['projection', 'on_false', 'regex'], '')}
                    disabledValue={''}
                    onChange={this.props.onChangeComputedLineKey(['projection', 'on_false', 'regex'])}
                    disabled={computedLineKey.getIn(['projection', 'on_false', 'key'], '') === ''}
                    label="Regex"
                    fieldType="toggeledInput"
                  />
                </Col>
              )}
            </FormGroup>
          </>
        )}
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  computedlineKeyOptions: inputProcessorComputedlineKeyOptionsSelector(state, props),
});

export default connect(mapStateToProps)(ComputedRate);
