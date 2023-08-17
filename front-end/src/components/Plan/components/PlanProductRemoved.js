import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Panel, Button } from 'react-bootstrap';
import Immutable from 'immutable';
import Help from '../../Help';

export default class PlanProductRemoved extends Component {

  static propTypes = {
    onProductUndoRemove: PropTypes.func.isRequired,
    usaget: PropTypes.string.isRequired,
    item: PropTypes.instanceOf(Immutable.Map),
  }

  static defaultProps = {
    item: Immutable.Map(),
  };


  onProductUndoRemove = () => {
    const { item } = this.props;
    const productKey = item.get('key');
    this.props.onProductUndoRemove(productKey);
  }

  render() {
    const { item, usaget } = this.props;
    const header = (
      <h3 className="product-removed">
        {item.get('key', '')} ({usaget}) <i>{item.get('code', '')}</i>
        <Help contents={item.get('description', '')} />
        <Button onClick={this.onProductUndoRemove} bsSize="xsmall" className="pull-right" style={{ minWidth: 80 }}><i className="fa fa-mail-reply" />&nbsp;Undo</Button>
      </h3>
    );
    return (<Panel header={header} />);
  }
}
