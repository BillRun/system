import React, { Component } from 'react';
import { ErrorInternal500 } from '../StaticPages';
import { ModalWrapper } from '@/components/Elements';


class ErrorBoundary extends Component {

  static getDerivedStateFromError(error) {
    // Update state so the next render will show the fallback UI.
    return { hasError: true };
  }

  state = {
    hasError: false,
  };

  componentDidCatch(error, info) {
    // You can also log the error to an error reporting service
    if (process.env.NODE_ENV === "development") {
      console.log("App Error: ", error);
      console.log("App Info: ", info);
    }
  }

  onClickAskCloseImport = () => {
    this.setState(() => ({ hasError: false }));
  }

  render() {
    if (this.state.hasError && process.env.NODE_ENV === "development") {
      return (
        <div>
          {this.props.children}
          <ModalWrapper show={true} title="Error" onHide={this.onClickAskCloseImport} modalSize="large">
            <div className="clearfix">
              <ErrorInternal500 onIgnore={this.onClickAskCloseImport}/>
            </div>
          </ModalWrapper>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
