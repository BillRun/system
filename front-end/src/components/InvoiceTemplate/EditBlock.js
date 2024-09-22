import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import { Panel, ButtonToolbar, DropdownButton, MenuItem } from 'react-bootstrap';
import Field from '@/components/Field';


class EditBlock extends PureComponent {

  static propTypes = {
    content: PropTypes.string,
    fields: PropTypes.array,
    templates: PropTypes.array,
    enabled: PropTypes.bool,
    name: PropTypes.string.isRequired,
    onChange: PropTypes.func.isRequired,
    loadTemplate: PropTypes.func,
    onChangeStatus: PropTypes.func,
  };

  static defaultProps = {
    content: '',
    fields: [],
    templates: [],
    enabled: false,
    loadTemplate: () => {},
    onChangeStatus: () => {},
  };

  loadTemplate = (index) => {
    const { name } = this.props;
    this.props.loadTemplate(name, index);
  };

  onChange = (content) => {
    const { name } = this.props;
    this.props.onChange(name, content);
  };

  onChangeStatus = (e) => {
    const { value } = e.target;
    const { name } = this.props;
    this.props.onChangeStatus(name, value);
  };

  renderPanelHeader = () => {
    const { name, templates, enabled } = this.props;
    return (
      <span>{`Invoice ${name}`}
        <div className="pull-right">
          { templates.length > 0 && (
            <ButtonToolbar className="inline" style={{ verticalAlign: 'middle' }}>
              <DropdownButton bsSize="xsmall" title="Load default" id="dropdown-size-medium" onSelect={this.loadTemplate}>
                { templates.map((template, key) => (
                  <MenuItem key={key} eventKey={key}>{template}</MenuItem>
                ))}
              </DropdownButton>
            </ButtonToolbar>
          )}
          <div className="inline" style={{ marginLeft: (templates.length) ? 10 : 0 }}>
            <Field
              fieldType="checkbox"
              value={enabled}
              onChange={this.onChangeStatus}
              label="Enable"
            />
          </div>
        </div>
      </span>
    );
  }

  render() {
    const { name, content, fields } = this.props;
    return (
      <Panel header={this.renderPanelHeader()}>
        <Field
          fieldType="textEditor"
          value={content}
          editorName={`editor-${name}`}
          name={name}
          configName="invoices"
          editorHeight={150}
          fields={fields}
          onChange={this.onChange}
        />
      </Panel>
    );
  }
}

export default EditBlock;
