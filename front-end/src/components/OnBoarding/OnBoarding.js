import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Link } from 'react-router';
import { Col, Row, Button } from 'react-bootstrap';
import Joyride from 'react-joyride';
import { ModalWrapper } from '@/components/Elements'
import {
  setOnBoardingStep,
  cancelOnBoarding,
  pendingOnBoarding,
  pauseOnBoarding,
  finishOnBoarding,
  runOnBoarding,
  showConfirmModal,
} from '@/actions/guiStateActions/pageActions';
import {
  onBoardingStepSelector,
  onBoardingIsRunnigSelector,
  onBoardingIsFinishedSelector,
  onBoardingIsStartingSelector,
  onBoardingIsPausedSelector,
} from '@/selectors/guiSelectors';


class OnBoarding extends Component {

  static propTypes = {
    isRunnig: PropTypes.bool,
    isFinished: PropTypes.bool,
    isStarting: PropTypes.bool,
    isPaused: PropTypes.bool,
    step: PropTypes.number,
    mobalTitle: PropTypes.element,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    step: 0,
    isRunnig: false,
    isFinished: false,
    isStarting: false,
    isPaused: false,
    mobalTitle: (
      <span>
        Welcome to BillRun
        <i className="fa fa-registered" style={{ fontSize: 12, verticalAlign: 'text-top' }} />
        &nbsp;Cloud!
      </span>
    ),
  };

  state = {
    // Ugly workaround https://github.com/gilbarbara/react-joyride/issues/223
    // Joyride set stepIndex only in componentWillReceiveProps if it was changed!
    // startIndex var need to trigget change in value from 0 to real step.
    startIndex: 0,
    autoStart: true,
    run: false,
  }

  componentWillReceiveProps(nextProps, nextState) {
    const { isPaused, isRunnig } = this.props;
    const { autoStart } = this.state;
    if ((isPaused && !nextProps.isPaused) || (!isRunnig && nextProps.isRunnig)) {
      if (autoStart || nextState.autoStart) {
        setTimeout(this.switchToRun, 500); // fix first step position - let invoce HTML to load before first step start
      } else {
        this.switchToRun();
      }
    }
    if (!nextProps.isRunnig) {
      this.setState({ startIndex: 0 });
    }
  }

  onCancel = () => {
    this.props.dispatch(cancelOnBoarding());
  }

  onPause = () => {
    this.props.dispatch(pauseOnBoarding());
  }

  onFinish = () => {
    this.onStepChanged(0);
    this.props.dispatch(finishOnBoarding());
  }

  onPending = () => {
    this.onStepChanged(0);
    this.props.dispatch(pendingOnBoarding());
    this.setState({ autoStart: true });
  }

  onStart = () => {
    this.props.dispatch(runOnBoarding());
  }

  onRestart = () => {
    this.onStepChanged(0);
    this.onStart();
    this.setState({ autoStart: true });
  }

  onStepChanged = (newStep) => {
    this.props.dispatch(setOnBoardingStep(newStep));
  }

  switchToRun = () => {
    this.setState({ run: true });
  }

  askCancel = () => {
    const confirm = {
      message: 'Are you sure you want to skip the tour ?',
      onOk: this.onCancel,
      labelOk: 'Skip tour',
      type: 'delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  };

  getSteps = () => ([{
    title: '1. Customer details',
    text: (
      <span>
        Customer name, id and address. Invoices are generated per customer.
        <br />
        <Link to="customers/customer" onClick={this.onPause}>Click here to create a customer</Link>
      </span>
    ),
    style: { beacon: { offsetY: -75 } },
    selector: '.table-info',
    type: 'click',
  }, {
    title: '2. Plans',
    text: (
      <span>
        A customer can have multiple subscriptions, each one tied to exactly one
        plan with recurring charges.
        <br />
        <Link to="plans/plan" onClick={this.onPause}>Click here to create a plan</Link>
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.step-plan',
    type: 'click',
  }, {
    title: '3. Services',
    text: (
      <span>
        Every subscription can register to extra services that are charged periodically.
        <br />
        <Link to="services/service" onClick={this.onPause}>Click here to create a service</Link>
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.step-service',
    type: 'click',
  }, {
    title: '4. Discounts',
    text: (
      <span>
        Build automatic discounts based on various combinations on subscription plans / services.
        <br />
        <Link to="discounts/discount" onClick={this.onPause}>Click here to create a discount</Link>
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.step-discount',
    type: 'click',
  }, {
    title: '5. Subscription details',
    text: (
      <span>
        This section appears for every subscription of the customer and shows aggregated
        amounts on the subscription level.
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.step-subscription-details',
    type: 'click',
  }, {
    title: '6. Usage details',
    text: (
      <span>
        If billing also by usage, the usage details can be included in the invoice (optional).<br />
        BillRun&apos;s input processors can receive events either in online (HTTP request)
        or offline (files) mode.
        <br />
        <Link to={{ pathname: '/select_input_processor_template', query: { action: 'new' } }} onClick={this.onPause}>Click here to set up an input processor</Link>
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.table-usage',
    type: 'click',
  }, {
    title: '7. Products',
    text: (
      <span>
        You can create different products which define pricing rules based on various usage events.
        <br />
        <Link to="/products/product" onClick={this.onPause}>Click here to create a product</Link>
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.step-products',
    type: 'click',
  }, {
    title: '8. Company details',
    text: (
      <span>
        Your company logo, name, address, etc. appear at the invoice header & footer.
        <br />
        <Link to={{ pathname: '/settings', query: { tab: 1 } }} onClick={this.onPause}>Set up your company details here</Link>
      </span>
    ),
    style: { beacon: { offsetY: -25 } },
    selector: '.step-company-details-header',
    type: 'click',
  }, {
    title: '9. Billing cycle management',
    text: (
      <span>
        You are in full control of the billing cycle run. See the billing cycle run progress
         as it runs and watch invoices as soon as they&apos;re created. Confirm or reset the
         cycle after reviewing it and charge your customers, all functionalities available
         from one screen!
        <br />
        <Link to="run_cycle" onClick={this.onPause}>Go to billing cycle management screen</Link>
      </span>
    ),
    style: { beacon: { offsetY: -65 } },
    selector: '.step-period',
    type: 'click',
  }]);

  joyrideEventHandler = (e) => {
    const { step } = this.props;
    if (!['close'].includes(e.action) && e.type === 'finished') {
      this.onFinish();
    } else if (e.type === 'error:target_not_found') {
      const skiptedIndex = (e.action === 'next') ? e.index + 1 : e.index - 1;
      this.setState({ startIndex: skiptedIndex });
      this.onStepChanged(skiptedIndex);
    } else if (e.action === 'close' && e.type === 'finished') {
      const lastStep = Math.max(e.steps.length - 2, 0);
      this.setState({ startIndex: lastStep, run: true, autoStart: false });
    } else if (e.action === 'start') {
      if (step !== 0) {
        this.setState({ startIndex: step });
      }
    } else if (e.action === 'next' && e.type === 'step:before') {
      this.onStepChanged(e.index);
    } else if (e.action === 'back' && e.type === 'step:after') {
      this.onStepChanged(e.index - 1);
    }
  }

  renderIsReadyContent = () => (
    <Row>
      <Col smPush={1} sm={10}>
        <p>In this short tutorial we will walk you through the main features of BillRun
           by examining a sample BillRun invoice and guiding you how to
           do it yourself with just a few clicks.
        </p>
      </Col>
      <Col smPush={1} sm={10}>
        <Button onClick={this.onStart} bsStyle="success">
          Let&apos;s start the tour!
        </Button>
      </Col>
    </Row>
  );

  renderIsFinishedContent = () => (
    <Row>
      <Col smPush={1} sm={10}>
        <p>We&apos;re done!</p>
        <p>Thank you for taking the tour!</p>
      </Col>
      <Col smPush={1} sm={10}>
        <Button onClick={this.onPending} bsStyle="success">
          Start Using BillRun
        </Button>
      </Col>
    </Row>
  )

  render() {
    const { isRunnig, isFinished, isStarting, mobalTitle } = this.props;
    const { startIndex, autoStart, run } = this.state;
    if (isStarting) {
      return (
        <ModalWrapper
          show={true}
          title={mobalTitle}
          labelCancel="Maybe later"
          onCancel={this.onPending}
          onHide={this.onPending}
        >
          <div className="text-center">
            { this.renderIsReadyContent() }
          </div>
        </ModalWrapper>
      );
    }

    if (isRunnig) {
      return (
        <Joyride
          type="continuous"
          showStepsProgress={false}
          scrollToFirstStep={true}
          scrollToSteps={true}
          disableOverlay={true}
          showOverlay={true}
          debug={false}
          stepIndex={startIndex}
          steps={this.getSteps()}
          run={run}
          autoStart={autoStart}
          callback={this.joyrideEventHandler}
        />
      );
    }

    if (isFinished) {
      return (
        <ModalWrapper
          show={true}
          title={mobalTitle}
          labelCancel="Start Tour Again"
          onCancel={this.onRestart}
          onHide={this.onPending}
        >
          <div className="text-center">
            { this.renderIsFinishedContent()}
          </div>
        </ModalWrapper>
      );
    }

    return (null);
  }

}

const mapStateToProps = state => ({
  isRunnig: onBoardingIsRunnigSelector(state),
  isFinished: onBoardingIsFinishedSelector(state),
  isStarting: onBoardingIsStartingSelector(state),
  isPaused: onBoardingIsPausedSelector(state),
  step: onBoardingStepSelector(state),
});

export default connect(mapStateToProps)(OnBoarding);
