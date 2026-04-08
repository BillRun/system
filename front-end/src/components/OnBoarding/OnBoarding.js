import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Link } from 'react-router-dom';
import { Col, Row, Button } from 'react-bootstrap';
import Joyride, { ACTIONS, EVENTS, STATUS } from 'react-joyride';
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
    startIndex: 0,
    autoStart: true,
    run: false,
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

  // react-joyride v2: steps use `target` (was `selector`) and `content` (was `text`)
  getSteps = () => ([{
    title: '1. Customer details',
    content: (
      <span>
        Customer name, id and address. Invoices are generated per customer.
        <br />
        <Link to="/customers/customer" onClick={this.onPause}>Click here to create a customer</Link>
      </span>
    ),
    target: '.table-info',
  }, {
    title: '2. Plans',
    content: (
      <span>
        A customer can have multiple subscriptions, each one tied to exactly one
        plan with recurring charges.
        <br />
        <Link to="/plans/plan" onClick={this.onPause}>Click here to create a plan</Link>
      </span>
    ),
    target: '.step-plan',
  }, {
    title: '3. Services',
    content: (
      <span>
        Every subscription can register to extra services that are charged periodically.
        <br />
        <Link to="/services/service" onClick={this.onPause}>Click here to create a service</Link>
      </span>
    ),
    target: '.step-service',
  }, {
    title: '4. Discounts',
    content: (
      <span>
        Build automatic discounts based on various combinations on subscription plans / services.
        <br />
        <Link to="/discounts/discount" onClick={this.onPause}>Click here to create a discount</Link>
      </span>
    ),
    target: '.step-discount',
  }, {
    title: '5. Subscription details',
    content: (
      <span>
        This section appears for every subscription of the customer and shows aggregated
        amounts on the subscription level.
      </span>
    ),
    target: '.step-subscription-details',
  }, {
    title: '6. Usage details',
    content: (
      <span>
        If billing also by usage, the usage details can be included in the invoice (optional).<br />
        BillRun&apos;s input processors can receive events either in online (HTTP request)
        or offline (files) mode.
        <br />
        <Link to={{ pathname: '/select_input_processor_template', query: { action: 'new' } }} onClick={this.onPause}>Click here to set up an input processor</Link>
      </span>
    ),
    target: '.table-usage',
  }, {
    title: '7. Products',
    content: (
      <span>
        You can create different products which define pricing rules based on various usage events.
        <br />
        <Link to="/products/product" onClick={this.onPause}>Click here to create a product</Link>
      </span>
    ),
    target: '.step-products',
  }, {
    title: '8. Company details',
    content: (
      <span>
        Your company logo, name, address, etc. appear at the invoice header & footer.
        <br />
        <Link to={{ pathname: '/settings', query: { tab: 1 } }} onClick={this.onPause}>Set up your company details here</Link>
      </span>
    ),
    target: '.step-company-details-header',
  }, {
    title: '9. Billing cycle management',
    content: (
      <span>
        You are in full control of the billing cycle run. See the billing cycle run progress
         as it runs and watch invoices as soon as they&apos;re created. Confirm or reset the
         cycle after reviewing it and charge your customers, all functionalities available
         from one screen!
        <br />
        <Link to="/run_cycle" onClick={this.onPause}>Go to billing cycle management screen</Link>
      </span>
    ),
    target: '.step-period',
  }]);

  // react-joyride v2 callback — event shape: { action, index, status, type, lifecycle }
  joyrideEventHandler = ({ action, index, status, type }) => {
    const { step } = this.props;

    if (status === STATUS.FINISHED) {
      this.onFinish();
    } else if (type === EVENTS.TARGET_NOT_FOUND) {
      const skippedIndex = action === ACTIONS.NEXT ? index + 1 : index - 1;
      this.setState({ startIndex: skippedIndex });
      this.onStepChanged(skippedIndex);
    } else if (action === ACTIONS.CLOSE && status === STATUS.PAUSED) {
      const lastStep = Math.max(this.getSteps().length - 2, 0);
      this.setState({ startIndex: lastStep, run: true, autoStart: false });
    } else if (action === ACTIONS.START) {
      if (step !== 0) {
        this.setState({ startIndex: step });
      }
    } else if (type === EVENTS.STEP_BEFORE && action === ACTIONS.NEXT) {
      this.onStepChanged(index);
    } else if (type === EVENTS.STEP_AFTER && action === ACTIONS.PREV) {
      this.onStepChanged(index - 1);
    }
  }

  renderIsReadyContent = () => (
    <Row>
      <Col sm={10}>
        <p>In this short tutorial we will walk you through the main features of BillRun
           by examining a sample BillRun invoice and guiding you how to
           do it yourself with just a few clicks.
        </p>
      </Col>
      <Col sm={10}>
        <Button onClick={this.onStart} variant="success">
          Let&apos;s start the tour!
        </Button>
      </Col>
    </Row>
  );

  renderIsFinishedContent = () => (
    <Row>
      <Col sm={10}>
        <p>We&apos;re done!</p>
        <p>Thank you for taking the tour!</p>
      </Col>
      <Col sm={10}>
        <Button onClick={this.onPending} variant="success">
          Start Using BillRun
        </Button>
      </Col>
    </Row>
  )

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { isPaused, isRunnig } = prevProps;
    const { autoStart } = this.state;
    if ((isPaused && !this.props.isPaused) || (!isRunnig && this.props.isRunnig)) {
      if (autoStart) {
        setTimeout(this.switchToRun, 500); // let DOM settle before first step
      } else {
        this.switchToRun();
      }
    }
    // Prevent infinite update loop: reset local state only on transition
    // from running -> not running, not on every render while not running.
    if (isRunnig && !this.props.isRunnig) {
      const nextState = {};
      if (this.state.startIndex !== 0) {
        nextState.startIndex = 0;
      }
      if (this.state.run) {
        nextState.run = false;
      }
      if (Object.keys(nextState).length > 0) {
        this.setState(nextState);
      }
    }
  }

  render() {
    const { isRunnig, isFinished, isStarting, mobalTitle } = this.props;
    const { startIndex, run } = this.state;
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
          continuous={true}
          scrollToFirstStep={true}
          disableOverlay={false}
          showSkipButton={true}
          stepIndex={startIndex}
          steps={this.getSteps()}
          run={run}
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
