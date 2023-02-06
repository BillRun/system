import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import { Form, FormGroup, ControlLabel, HelpBlock, Col } from 'react-bootstrap';
import { TaxDescription } from '@/language/FieldDescriptions';
import Help from '../../Help';
import EntityFields from '../../Entity/EntityFields';
import Field from '@/components/Field';
import {
  getConfig,
  getFieldName,
  getFieldNameType,
} from '@/common/Util';

class TaxDetails extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map).isRequired,
    mode: PropTypes.string.isRequired,
    onFieldUpdate: PropTypes.func.isRequired,
    onFieldRemove: PropTypes.func.isRequired,
    errorMessages: PropTypes.object,
  }

  static defaultProps = {
    errorMessages: {
      key: {
        allowedCharacters: 'Key contains illegal characters, key should contain only alphabets, numbers and underscores (A-Z, 0-9, _)',
      },
    },
  };

  state = {
    errors: {
      key: '',
    },
  }

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    return !Immutable.is(this.props.item, nextProps.item) || this.props.mode !== nextProps.mode;
  }

  onChangeKey = (e) => {
    const { errorMessages: { key: { allowedCharacters } } } = this.props;
    const { errors } = this.state;
    const value = e.target.value.toUpperCase();
    const newError = (!getConfig('keyUppercaseRegex', /./).test(value)) ? allowedCharacters : '';
    this.setState({ errors: Object.assign({}, errors, { key: newError }) });
    this.props.onFieldUpdate(['key'], value);
  }

  onChangeDescription = (e) => {
    const { value } = e.target;
    this.props.onFieldUpdate(['description'], value);
  }

  render () {
    const { errors } = this.state;
    const { item, mode } = this.props;
    const editable = (mode !== 'view');
    return (
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            { getFieldName('description', getFieldNameType('tax'), sentenceCase('title'))}
            <span className="danger-red"> *</span>
            <Help contents={TaxDescription.description} />
          </Col>
          <Col sm={8} lg={9}>
            <Field value={item.get('description', '')} onChange={this.onChangeDescription} editable={editable} />
          </Col>
        </FormGroup>

        {['clone', 'create'].includes(mode) &&
          <FormGroup validationState={errors.key.length > 0 ? 'error' : null} >
            <Col componentClass={ControlLabel} sm={3} lg={2}>
              { getFieldName('key', getFieldNameType('tax'), sentenceCase('key'))}
              <span className="danger-red"> *</span>
              <Help contents={TaxDescription.key} />
            </Col>
            <Col sm={8} lg={9}>
              <Field value={item.get('key', '')} onChange={this.onChangeKey} editable={editable} />
              { errors.key.length > 0 && <HelpBlock>{errors.key}</HelpBlock> }
            </Col>
          </FormGroup>
        }
        <EntityFields
          entityName="taxes"
          entity={item}
          onChangeField={this.props.onFieldUpdate}
          onRemoveField={this.props.onFieldRemove}
          editable={editable}
        />
      </Form>
    );
  }
}

export default TaxDetails
