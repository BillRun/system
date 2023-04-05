import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import { templateTokenSettingsSelectorForEditor } from '@/selectors/settingsSelector';


class CollectionTypeMessage extends Component {

  static propTypes = {
    content: PropTypes.instanceOf(Immutable.Map),
    templateToken: PropTypes.instanceOf(Immutable.List),
    editor: PropTypes.string,
    onChange: PropTypes.func.isRequired,
  };

  static defaultProps = {
    content: Immutable.Map(),
    templateToken: Immutable.List(),
    editor: 'mails',
  };

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    const { content, templateToken } = this.props;
    return !Immutable.is(content, nextProps.content)
          || !Immutable.is(templateToken, nextProps.templateToken);
  }

  onChangeSubject = (e) => {
    const { value } = e.target;
    this.props.onChange(['subject'], value);
  }

  onChangeBody = (value) => {
    this.props.onChange(['body'], value);
  }

  render() {
    const { content, templateToken, editor } = this.props;
    return (
      <div>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>Subject</Col>
          <Col sm={8} lg={9}>
            <Field onChange={this.onChangeSubject} value={content.get('subject', '')} />
          </Col>
        </FormGroup>
        <div>
          <Field
            fieldType="textEditor"
            value={content.get('body')}
            editorName="editor"
            fields={templateToken.toArray()}
            onChange={this.onChangeBody}
            configName={editor}
          />
        </div>
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  templateToken: templateTokenSettingsSelectorForEditor(state, props, ['general', 'account', 'collection']),
});

export default connect(mapStateToProps)(CollectionTypeMessage);
