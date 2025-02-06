import React, { Component } from 'react';
import GatewayParamsModal from './GatewayParamsModal';
import ToggleButton from './ToggleButton';
import NotSupportedModal from './NotSupportedModal';
import { getConfig } from '@/common/Util';


export default class PaymentGateway extends Component {

  state = {
    showParamsModal: false,
    showUnsupported: false,
  };

  onShowParams = () => {
    if (this.props.settings.get('supported', false)) {
      this.setState({showParamsModal: true});
      return;
    }
    this.setState({showUnsupported: true});
  };

  onCloseParams = () => {
    this.setState({showParamsModal: false, showUnsupported: false});
  };

  onSaveParams = (gateway) => {
    const { onSaveParams, enabled } = this.props;
    onSaveParams(gateway, enabled !== undefined);
    this.onCloseParams();
  };

  onClickEnable = () => {
    this.onShowParams();
  };

  onClickDisable = () => {
    const { onDisable, settings } = this.props;
    var r = window.confirm("Are you sure you want to disable this payment gateway?");
    if (r)
      onDisable(settings.get('name'));
  };

  render() {
    const { settings, enabled } = this.props;
    const { showParamsModal, showUnsupported } = this.state;
    const style = {};
    if (!enabled) {
      style['WebkitFilter'] = style.filter = "grayscale(100%)";
    }

    return (
      <div className="PaymentGateway">
        { settings.get('supported', false) ? (
          <GatewayParamsModal
            settings={settings}
            show={showParamsModal}
            gateway={enabled}
            onSave={this.onSaveParams}
            onClose={this.onCloseParams}
          />
        ) : (
          <NotSupportedModal
            show={ showUnsupported }
            onClose={ this.onCloseParams }
            gateway={ settings.get('name') }
          />
        )}
        <div className="form-group">
          <div className="col-lg-8 col-md-8">
            { !settings.get('image_url')
              ? settings.get('name')
              : (<img src={`${getConfig(['env','serverApiUrl'], '')}/${settings.get('image_url')}`} alt="Logo" style={style} width="200" height="51" />)
            }
          </div>
          <div className="col-lg-4 col-md-4">
            <div className="pull-right">
              <button
                onClick={this.onShowParams}
                type="button"
                className="btn btn-default btn-lg"
                disabled={!enabled}
              >
                <i className="fa fa-gear"></i>
              </button>
            </div>
          </div>
        </div>
        <div className="form-group">
          <div className="col-lg-12 col-md-12">
            {settings.get('title')}
            <div className="pull-right">
              <ToggleButton enabled={enabled} onClickEnable={this.onClickEnable} onClickDisable={this.onClickDisable} />
            </div>
          </div>
        </div>
        <div className="separator"></div>
      </div>
    );
  }
}
