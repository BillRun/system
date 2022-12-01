import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form } from 'react-bootstrap';
import { ActionButtons } from '@/components/Elements';
import EditBlock from './EditBlock';
import {
  templateSelector,
  suggestionsSelector,
} from './invoiceTemplateSelector';


class InvoiceTemplate extends Component {

  static propTypes = {
    header: PropTypes.string,
    footer: PropTypes.string,
    suggestions: PropTypes.instanceOf(Immutable.List),
    templates: PropTypes.instanceOf(Immutable.Map),
    status: PropTypes.instanceOf(Immutable.Map),
    getData: PropTypes.func,
    onChange: PropTypes.func,
    onSave: PropTypes.func,
    onCancel: PropTypes.func,
    onChangeStatus: PropTypes.func,
  };

  static defaultProps = {
    header: '',
    footer: '',
    suggestions: Immutable.List(),
    templates: Immutable.Map(),
    status: Immutable.Map(),
    onChange: () => {},
    onSave: () => {},
    onCancel: () => {},
    getData: () => {},
    onChangeStatus: () => {},
  };

  componentDidMount() {
    this.props.getData();
  }

  loadTemplate = (name, index) => {
    const { templates } = this.props;
    const newContent = templates.getIn([name, index, 'content']);
    this.props.onChange(name, newContent);
  }

  render() {
    const { header, footer, suggestions, templates, status } = this.props;
    const fieldsList = suggestionsSelector(suggestions);
    const headerTemplates = templateSelector(templates, 'header');
    const footerTemplates = templateSelector(templates, 'footer');
    const headerStatus = status.get('header', false);
    const footerStatus = status.get('footer', false);
    return (
      <div>
        <div className="row">
          <div className="col-lg-12">
            <Form horizontal>
              { header !== null && (
                <EditBlock
                  name="header"
                  content={header}
                  onChange={this.props.onChange}
                  fields={fieldsList}
                  templates={headerTemplates}
                  loadTemplate={this.loadTemplate}
                  enabled={headerStatus}
                  onChangeStatus={this.props.onChangeStatus}
                />
              )}
              { footer !== null && (
                <EditBlock
                  name="footer"
                  content={footer}
                  onChange={this.props.onChange}
                  fields={fieldsList}
                  templates={footerTemplates}
                  loadTemplate={this.loadTemplate}
                  enabled={footerStatus}
                  onChangeStatus={this.props.onChangeStatus}
                />
              )}
            </Form>
          </div>
        </div>
        <ActionButtons
          onClickSave={this.props.onSave}
          cancelLabel="Rollback"
          onClickCancel={this.props.onCancel}
        />
      </div>
    );
  }
}

export default InvoiceTemplate;
