import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, Col, Button, ButtonGroup } from 'react-bootstrap';
import { ControlLabel, FormGroup } from '@/common/BootstrapCompat';
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
    // CHANGE 1: We now expect a list of template definitions (name, label, conditions)
    templateConfig: PropTypes.instanceOf(Immutable.List),
    placeholders: PropTypes.instanceOf(Immutable.List),
  };

  static defaultProps = {
    name: '',
    fields: null,
    emailTemplates: null,
    templateConfig: Immutable.List(),
    placeholders: Immutable.List(),
  };

  state = {
    activeTemplate: '', // Stores the 'name' of the active template (e.g., 'en_template')
  };

  componentDidMount() {
    this.props.dispatch(getSettings('email_templates'));
  }

  componentDidUpdate(prevProps) {
    const { templateConfig } = this.props;
    const { activeTemplate } = this.state;

    // CHANGE 2: Set default active tab to the first template in the list
    if (templateConfig.size > 0 && activeTemplate === '') {
      this.setState({ activeTemplate: templateConfig.first().get('name') });
    }
  }

  isDataReady = () => {
    const { fields, emailTemplates, templateConfig } = this.props;
    const { activeTemplate } = this.state;
    if (!fields || !emailTemplates || templateConfig.size === 0 || activeTemplate === '') {
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

  // CHANGE 3: Switch tab based on template 'name'
  onSwitchTemplate = (templateName) => {
    this.setState({ activeTemplate: templateName });
  }

  // CHANGE 4: Update content using the active template name as the key
  onChangeContent = (content) => {
    const { name, templateConfig } = this.props;
    const { activeTemplate } = this.state;

    const index = templateConfig.findIndex(t => t.get('name') === activeTemplate);

    if (index !== -1) {
      this.props.dispatch(updateSetting(
        'email_templates', 
        [name, 'templates', index, 'content'], 
        content
      ));
    }
  }

  // CHANGE 5: Update subject using the active template name
  onChangeSubject = (e) => {
    const { value } = e.target;
    const { name, templateConfig } = this.props;
    const { activeTemplate } = this.state;

    const index = templateConfig.findIndex(t => t.get('name') === activeTemplate);

    if (index !== -1) {
      this.props.dispatch(updateSetting(
        'email_templates', 
        [name, 'templates', index, 'subject'], 
        value
      ));
    }
  }

  getContent = () => {
    const { templateConfig } = this.props;
    const { activeTemplate } = this.state;
    
    const template = templateConfig.find(t => t.get('name') === activeTemplate);
    return template ? template.get('content', '') : '';
  }

  getSubject = () => {
    const { templateConfig } = this.props;
    const { activeTemplate } = this.state;
    
    const template = templateConfig.find(t => t.get('name') === activeTemplate);
    return template ? template.get('subject', '') : '';
  }

  getFields = () => {
    const { name, fields, placeholders } = this.props;
    const placeholderPaths = placeholders.map(p => p.get('path', ''));
    return [...fields.toArray(), ...placeholderPaths.toArray()];
  }

  render() {
    const { name, templateConfig } = this.props;
    const { activeTemplate } = this.state;

    if (!this.isDataReady()) {
      return (<LoadingItemPlaceholder />);
    }

    return (
      <Form>
        
        {/* CHANGE 7: Render Tabs based on the 'templates' array in config */}
        <FormGroup>
          <Col as={ControlLabel} sm={1}>Template</Col>
          <Col sm={11}>
            <ButtonGroup>
              {templateConfig.map((tmpl, index) => {
                const tmplName = tmpl.get('name');
                const tmplLabel = tmpl.get('label', tmplName);
                const isActive = activeTemplate === tmplName;
                
                return (
                  <Button 
                    key={tmplName}
                    variant={isActive ? 'primary' : 'default'}
                    onClick={() => this.onSwitchTemplate(tmplName)}
                  >
                    {tmplLabel}
                  </Button>
                );
              })}
            </ButtonGroup>
          </Col>
        </FormGroup>

        <FormGroup>
          <Col as={ControlLabel} sm={1}>Subject</Col>
          <Col sm={8}>
            <Field
              onChange={this.onChangeSubject}
              value={this.getSubject()}
              key={`subject-${activeTemplate}`} 
            />
          </Col>
        </FormGroup>
        
        <FormGroup>
          <Col sm={12}>
            <Field
              key={`editor-${activeTemplate}`} // Important for full re-render
              fieldType="textEditor"
              value={this.getContent()}
              editorName={`editor-${name}-${activeTemplate}`}
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

const mapStateToProps = (state, props) => {
  const { name } = props;
  const emailTemplates = emailTemplatesSelector(state, props) || Immutable.Map();
  const templateConfig = emailTemplates.getIn([name, 'templates'], Immutable.List());

  const placeholders = emailTemplates.getIn([name, 'placeholders'], Immutable.List());

  return {
    emailTemplates,
    templateConfig,
    placeholders
  };
};

export default connect(mapStateToProps)(EmailTemplate);