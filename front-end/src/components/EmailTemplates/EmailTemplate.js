import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import {
  emailTemplatesSelector,
} from '@/selectors/settingsSelector';
import {
  getSettings,
  saveSettings,
  updateSetting,
} from '@/actions/settingsActions';

class EmailTemplate extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    name: PropTypes.string,
    fields: PropTypes.instanceOf(Immutable.List),
    emailTemplates: PropTypes.instanceOf(Immutable.Map),
  };

  static defaultProps = {
    name: '',
    fields: null,
    emailTemplates: null,
  };

  componentDidMount() {
    this.props.dispatch(getSettings(['email_templates', 'subscribers']));
  }

  isDataReady = () => {
    const { fields, emailTemplates } = this.props;
    if (!fields || !emailTemplates) {
      return false;
    }
    return true;
  }

  onSave = () => {
    const afterSave = (response) => {
      if (response && response.status === 1) {
        this.props.dispatch(getSettings('email_templates'));
      }
    };
    this.props.dispatch(saveSettings('email_templates')).then(afterSave);
  }

  onCancel = () => {
    this.props.dispatch(getSettings('email_templates'));
  }

  onChangeContent = (content) => {
    const { name } = this.props;
    this.props.dispatch(updateSetting('email_templates', [name, 'content'], content));
  }

  onChangeSubject = (e) => {
    const { value } = e.target;
    const { name } = this.props;
    this.props.dispatch(updateSetting('email_templates', [name, 'subject'], value));
  }

  getContent = () => {
    const { name, emailTemplates } = this.props;
    return emailTemplates.getIn([name, 'content'], '');
  }

  getSubject = () => {
    const { name, emailTemplates } = this.props;
    return emailTemplates.getIn([name, 'subject'], '');
  }

  getFields = () => {
    const { name, fields, emailTemplates } = this.props;
    const templateFields = emailTemplates.getIn([name, 'html_translation'], Immutable.List());
    return [...fields.toArray(), ...templateFields.toArray()];
  }

  render() {
    const { name } = this.props;

    if (!this.isDataReady()) {
      return (<LoadingItemPlaceholder />);
    }

    return (
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={1}>Subject</Col>
          <Col sm={8}>
            <Field
              onChange={this.onChangeSubject}
              value={this.getSubject()}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={12}>
            <Field
              fieldType="textEditor"
              value={this.getContent()}
              editorName={`editor-${name}`}
              name={name}
              configName="invoices"
              editorHeight={150}
              fields={this.getFields()}
              onChange={this.onChangeContent}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col sm={12}>
            <ActionButtons
              onClickSave={this.onSave}
              cancelLabel="Rollback"
              onClickCancel={this.onCancel}
            />
          </Col>
        </FormGroup>
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  emailTemplates: emailTemplatesSelector(state, props),
});

export default connect(mapStateToProps)(EmailTemplate);
