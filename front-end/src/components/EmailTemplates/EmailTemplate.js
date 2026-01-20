import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, Button, ButtonGroup } from 'react-bootstrap';
import Field from '@/components/Field';
import { ActionButtons, LoadingItemPlaceholder } from '@/components/Elements';
import { emailTemplatesSelector } from '@/selectors/settingsSelector';
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
    languages: PropTypes.arrayOf(PropTypes.string),
  };

  static defaultProps = {
    name: '',
    fields: null,
    emailTemplates: null,
    languages: [],
  };

  state = {
    activeLang: '', 
  };

  componentDidMount() {
    console.log("hi");
    // CHANGE 2: Fetch 'email_templates' and 'subscribers' (where account lives)
    this.props.dispatch(getSettings(['email_templates', 'subscribers']));
  }

  componentDidUpdate(prevProps) {
    const { languages } = this.props;
    const { activeLang } = this.state;
    // Set default active tab when languages load
    if (languages.length > 0 && activeLang === '') {
      this.setState({ activeLang: languages[0] });
    }
  }

  isDataReady = () => {
    const { fields, emailTemplates, languages } = this.props;
    if (!fields || !emailTemplates || languages.length === 0) {
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

  onSwitchLang = (lang) => {
    this.setState({ activeLang: lang });
  }

  onChangeContent = (content) => {
    const { name, emailTemplates, languages } = this.props;
    const { activeLang } = this.state;

    // 1. Get the current raw value
    const currentContent = emailTemplates.getIn([name, 'content']);

    // 2. Check if it is a legacy String
    if (!Immutable.Map.isMap(currentContent)) {
      const defaultLang = languages[0] || 'en';

      const newContentMap = Immutable.Map({
        [defaultLang]: currentContent,
        [activeLang]: content
      });

      // Update the ENTIRE 'content' field
      this.props.dispatch(updateSetting('email_templates', [name, 'content'], newContentMap));
    } else {
      // Safe to update deeply
      this.props.dispatch(updateSetting('email_templates', [name, 'content', activeLang], content));
    }
  }

  onChangeSubject = (e) => {
    const { value } = e.target;
    const { name, emailTemplates, languages } = this.props;
    const { activeLang } = this.state;

    // 1. Get the current raw value
    const currentSubject = emailTemplates.getIn([name, 'subject']);

    // 2. Check if it is a legacy String (not a Map)
    if (!Immutable.Map.isMap(currentSubject)) {
      // It's a string! We need to convert it to a Map.
      // We assume the existing string belongs to the first available language (usually English)
      const defaultLang = languages[0] || 'en';
      
      const newSubjectMap = Immutable.Map({
        [defaultLang]: currentSubject, // Preserve the old string as the default language
        [activeLang]: value            // Set the new value for the current language
      });

      // Update the ENTIRE 'subject' field with our new Map
      this.props.dispatch(updateSetting('email_templates', [name, 'subject'], newSubjectMap));
    } else {
      // It's already a Map, so we can safely update deep inside it
      this.props.dispatch(updateSetting('email_templates', [name, 'subject', activeLang], value));
    }
  }

  getContent = () => {
    const { name, emailTemplates } = this.props;
    const { activeLang } = this.state;
    const rawContent = emailTemplates.getIn([name, 'content']);

    if (Immutable.Map.isMap(rawContent)) {
      return rawContent.get(activeLang, '');
    }
    // Fallback: if string, show only on first language
    if (typeof rawContent === 'string' && activeLang === this.props.languages[0]) {
      return rawContent;
    }
    return '';
  }

  getSubject = () => {
    const { name, emailTemplates } = this.props;
    const { activeLang } = this.state;
    const rawSubject = emailTemplates.getIn([name, 'subject']);

    if (Immutable.Map.isMap(rawSubject)) {
      return rawSubject.get(activeLang, '');
    }
    // Fallback: if string, show only on first language
    if (typeof rawSubject === 'string' && activeLang === this.props.languages[0]) {
      return rawSubject;
    }
    return '';
  }

  getFields = () => {
    const { name, fields, emailTemplates } = this.props;
    const templateFields = emailTemplates.getIn([name, 'html_translation'], Immutable.List());
    return [...fields.toArray(), ...templateFields.toArray()];
  }

  render() {
    const { name, languages } = this.props;
    const { activeLang } = this.state;

    if (!this.isDataReady()) {
      return (<LoadingItemPlaceholder />);
    }

    return (
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={1}>Language</Col>
          <Col sm={11}>
            <ButtonGroup>
              {languages.map(lang => (
                <Button 
                  key={lang}
                  bsStyle={activeLang === lang ? 'primary' : 'default'}
                  onClick={() => this.onSwitchLang(lang)}
                >
                  {lang}
                </Button>
              ))}
            </ButtonGroup>
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={1}>Subject</Col>
          <Col sm={8}>
            <Field
              onChange={this.onChangeSubject}
              value={this.getSubject()}
              key={`subject-${activeLang}`} 
            />
          </Col>
        </FormGroup>
        
        <FormGroup>
          <Col sm={12}>
            <Field
              key={`editor-${activeLang}`} 
              
              fieldType="textEditor"
              value={this.getContent()}
              editorName={`editor-${name}-${activeLang}`}
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

// CHANGE 3: Update Selector to look inside subscribers -> account
const mapStateToProps = (state, props) => {
  // Path: settings -> subscribers -> account -> fields
  const accountFields = state.settings.getIn(['subscribers', 'account', 'fields'], Immutable.List());
  
  const langField = accountFields.find(field => field.get('field_name') === 'invoice_language');
  const optionsStr = langField ? langField.get('select_options', '') : '';
  
  const languages = optionsStr ? optionsStr.split(',') : ['en'];

  return {
    emailTemplates: emailTemplatesSelector(state, props),
    languages: languages,
  };
};

export default connect(mapStateToProps)(EmailTemplate);