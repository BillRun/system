import React, { Component } from 'react';
import { Panel, Tabs, Tab } from 'react-bootstrap';
import Modal from 'react-bootstrap/lib/Modal';

export default class GatewayParamsModal extends Component {
  constructor(props) {
    super(props);

    this.state = {
      gateway: {
        params: {},
        transactions: {},
        denials: {},
        export: {},
      },
      transactionsConnections: [],
      denialsConnections: [],
      activeTab: 1,
      transactionsConnection: {},
      denialsConnection: {},
    };
  }

  componentWillReceiveProps(nextProps) {
    const { gateway, settings } = nextProps;
    if (gateway) {
      const currentTransactionsConnection = gateway.getIn(['transactions', 'receiver', 'connections', 0]) === undefined ? {} :
        gateway.getIn(['transactions', 'receiver', 'connections', 0]).toJS();
      const currentDenialsConnection = gateway.getIn(['denials', 'receiver', 'connections', 0]) === undefined ? {} :
        gateway.getIn(['denials', 'receiver', 'connections', 0]).toJS();

      return this.setState({
        transactionsConnection: currentTransactionsConnection,
        denialsConnection: currentDenialsConnection,
        gateway: gateway.toJS(),
      });
    }
    return this.setState({gateway: {name: settings.get('name'), params: {}}});
  }

  onChangeParam = () => {
    const { onChangeParam, gateway } = this.props;
    onChangeParam(gateway.get('name'));
  };

  onChangeParamValue = (e) => {
    const { id, value } = e.target;
    const { gateway } = this.state;

    this.setState({gateway: Object.assign({}, gateway, {
      params: Object.assign({}, gateway.params, {
	[id]: value
      })
    })});
  };

  onChangeTransactionsReceiverValue = (e) => {
    const { id, value } = e.target;
    const { gateway, transactionsConnections, transactionsConnection } = this.state;

    transactionsConnection[id] = value;
    if (transactionsConnections.length > 0) {
      transactionsConnections[0][id] = value;
    } else {
      transactionsConnections.push(transactionsConnection);
    }

    this.setState({ transactionsConnection,
      gateway: Object.assign({}, gateway, {
        transactions: Object.assign({}, gateway.transactions, {
          receiver: {connections: transactionsConnections},
        }),
      }) });
  };

  onChangeDenialsReceiverValue = (e) => {
    const { id, value } = e.target;
    const { gateway, denialsConnections, denialsConnection } = this.state;

    denialsConnection[id] = value;
    if (denialsConnections.length > 0) {
      denialsConnections[0][id] = value;
    } else {
      denialsConnections.push(denialsConnection);
    }

    this.setState({ denialsConnection,
      gateway: Object.assign({}, gateway, {
        denials: Object.assign({}, gateway.denials, {
          receiver: {connections: denialsConnections},
        }),
      }) });
  };

  onChangeExportValue = (e) => {
    const { id, value } = e.target;
    const { gateway } = this.state;

    this.setState({ gateway: Object.assign({}, gateway, {
      export: Object.assign({}, gateway.export, {
        [id]: value,
      }),
    }) });
  };

  onSave = () => {
    const { gateway } = this.state;
    const { onSave } = this.props;
    onSave(gateway);
  };

  onClose = () => {
    this.props.onClose();
    this.setState({gateway: {params: {}}});
  };

  handleSelectTab = (key) => {
    this.setState({ activeTab: key });
  }

  renderTabsBody = () => {
    const { settings } = this.props;
    const { gateway, activeTab } = this.state;
    const exportValue = gateway.export !== undefined ? gateway.export : [];
    const gatewayTransactions = gateway.transactions !== undefined ? gateway.transactions : {};
    const transactionsReceiverValue = gatewayTransactions.receiver !== undefined ? gatewayTransactions.receiver : [];
    const transactionsConnections = transactionsReceiverValue.connections !== undefined
      ? transactionsReceiverValue.connections 
      : [];
    const transactionsConnection = transactionsConnections[0] !== undefined ? transactionsConnections[0] : [];
    const gatewayDenials = gateway.denials !== undefined ? gateway.denials : {};
    const denialsReceiverValue = gatewayDenials.receiver !== undefined ? gatewayDenials.receiver : [];
    const denialsConnections = denialsReceiverValue.connections !== undefined ? denialsReceiverValue.connections : [];
    const denialsConnection = denialsConnections[0] !== undefined ? denialsConnections[0] : [];
    const secretFields = settings.get('secret_fields') !== undefined ? settings.get('secret_fields') : [];
    return (
      <Tabs activeKey={activeTab} animation={false} id="PaymentGatewayTab" onSelect={this.handleSelectTab}>
        <Tab title="API Parameters" eventKey={1}>
          <Panel style={{ borderTop: 'none' }}>
            <form className="form-horizontal">
              {settings.get('params').keySeq().map((param, paramKey) => (
                <div className="form-group" key={paramKey}>
                  <label className="col-lg-3 control-label">{param}</label>
                  <div className="col-lg-4">
                    <input type={secretFields.includes(param) ? 'password': 'text'}
                      id={param}
                      autoComplete="new-password"
                      onChange={this.onChangeParamValue}
                      className="form-control"
                      value={gateway['params'][param]} />
                  </div>
                </div>
              ))}
            </form>
          </Panel>
        </Tab>

        <Tab title="File Based Export" eventKey={2}>
          <Panel style={{ borderTop: 'none' }}>
            <form className="form-horizontal">
              {settings.get('export').keySeq().map((param, paramKey) => (
                <div className="form-group" key={paramKey}>
                  <label className="col-lg-3 control-label">{param}</label>
                  <div className="col-lg-4">
                    <input type="text"
                      id={param}
                      onChange={this.onChangeExportValue}
                      className="form-control"
                      value={exportValue[param]} />
                  </div>
                </div>
              ))}
            </form>
          </Panel>
        </Tab>

        <Tab title="Transactions Receiver" eventKey={3}>
          <Panel style={{ borderTop: 'none' }}>
            <form className="form-horizontal">
              {settings.get('receiver').keySeq().map((param, paramKey) => (
                <div className="form-group" key={paramKey}>
                  <label className="col-lg-3 control-label">{param}</label>
                  <div className="col-lg-4">
                    <input type="text"
                      id={param}
                      onChange={this.onChangeTransactionsReceiverValue}
                      className="form-control"
                      value={transactionsConnection[param]} />
                  </div>
                </div>
              ))}
            </form>
          </Panel>
        </Tab>

        <Tab title="Denials receiver" eventKey={4}>
          <Panel style={{ borderTop: 'none' }}>
            <form className="form-horizontal">
              {settings.get('receiver').keySeq().map((param, paramKey) => (
                <div className="form-group" key={paramKey}>
                  <label className="col-lg-3 control-label">{param}</label>
                  <div className="col-lg-4">
                    <input type="text"
                      id={param}
                      onChange={this.onChangeDenialsReceiverValue}
                      className="form-control"
                      value={denialsConnection[param]} />
                  </div>
                </div>
              ))}
            </form>
          </Panel>
        </Tab>

      </Tabs>
    );
  }

  renderSingularBody = () => {
    const { settings } = this.props;
    const { gateway } = this.state;
    const secretFields = settings.get('secret_fields') !== undefined ? settings.get('secret_fields') : [];
    return (
      <form className="form-horizontal">
        {settings.get('params').keySeq().map((param, paramKey) => (
          <div className="form-group" key={paramKey}> {/* eslint-disable-line react/no-array-index-key */}
            <label className="col-lg-3 control-label">{param}</label>
            <div className="col-lg-4">
              <input type={secretFields.includes(param) ? 'password': 'text'}
                id={param}
                onChange={this.onChangeParamValue}
                autoComplete="new-password"
                className="form-control"
                value={gateway['params'][param]} />
            </div>
          </div>
        ))}
      </form>
    );
  }

  renderModalBody = () => {
    const { settings } = this.props;
    const hasTabs = !settings.get('receiver').isEmpty() && !settings.get('export').isEmpty();

    if (hasTabs) {
      return (
        this.renderTabsBody()
      );
    }
    return (
      this.renderSingularBody()
    );
  }

  render() {
    const { settings, show = false } = this.props;

    return (

      <Modal show={show} onHide={this.onClose} bsSize="large">
        <Modal.Header closeButton>
          <Modal.Title>{settings.get('title')} parameters</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          { this.renderModalBody() }
        </Modal.Body>
        <Modal.Footer>
          <button type="button" className="btn btn-default" onClick={this.onClose}>Cancel</button>
          <button type="button" className="btn btn-primary" onClick={this.onSave}>Save</button>
        </Modal.Footer>
      </Modal>
    );
  }
}
