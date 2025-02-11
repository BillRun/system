import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form } from 'react-bootstrap';
import CollectionDetails from './Elements/CollectionDetails';
import CollectionTypeMessage from './Elements/CollectionTypeMessage';
import CollectionTypeHttp from './Elements/CollectionTypeHttp';


class CollectionStep extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    errors: PropTypes.instanceOf(Immutable.Map),
    onChange: PropTypes.func.isRequired,
  };

  static defaultProps = {
    item: Immutable.Map(),
    errors: Immutable.Map(),
  };

  onChangeContent = (path, value) => {
    this.props.onChange(['content', ...path], value);
  }

  renderStepByType = (item) => {
    const content = item.get('content');
    switch (item.get('type', '')) {
      case 'mail':
        return (<CollectionTypeMessage content={content} onChange={this.onChangeContent} editor="mails" />);
      case 'sms':
        return (<CollectionTypeMessage content={content} onChange={this.onChangeContent} editor="sms" />);
      case 'http':
        return (<CollectionTypeHttp content={content} onChange={this.onChangeContent} />);
      default:
        return (<p />);
    }
  }

  render() {
    const { item, errors } = this.props;
    return (
      <div className="row">
        <div className="col-lg-12">
          <Form horizontal>
            <CollectionDetails item={item} onChange={this.props.onChange} errors={errors} />
            <hr />
            {this.renderStepByType(item)}
          </Form>
        </div>
      </div>
    );
  }
}

export default CollectionStep;
