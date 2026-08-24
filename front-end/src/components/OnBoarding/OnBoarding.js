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

/**
 * BillRun onboarding tour — react-joyride v2 on React 19.
 *
 * Do NOT pass `stepIndex`: it enables controlled mode where repeated Next clicks stall
 * (Joyride's store listener only fires when internal JSON state changes; see
 * node_modules/react-joyride/dist/index.js → hasUpdatedState / getNextState).
 *
 * Joyride owns step progression (non-controlled). Redux guiState.onBoarding.step mirrors
 * the active position for Resume Tour, synced from callback events:
 *   · EVENTS.TOOLTIP — tooltip visible (Next, Back, or beacon reopen)
 *   · EVENTS.BEACON   — × dismissed the tooltip; Joyride advanced in beacon mode
 * `run` gates Joyride start/stop; `autoStart` delays the first RUNNING entry so
 * ExampleInvoice targets exist before step 1 positions (see componentDidUpdate).
 * getHelpers().go(step) restores the mirrored step after TOUR_START when resuming.
 *
 * Styling: JOYRIDE_STYLES (react-floater / beacon / spotlight API) +
 * react-joyride.scss (tooltip DOM). primaryColor here must match $joyride-color in SCSS.
 * Animation: v1 pop-in on step 0 only; step transitions via JOYRIDE_FLOATER_PROPS (v2
 * remounts floater per step unlike v1's single repositioned tooltip).
 */

// Bootstrap 3 smPush={1} + sm={10} centring for welcome/finished modals (customer_portal parity).
const MODAL_BODY_COL = { sm: 10, className: 'col-sm-offset-1' };

// Tooltip DOM matches v1 (.joyride-tooltip__*). Visual rules live in react-joyride.scss;
// only floater-owned values (z-index, arrow, beacon colour, spotlight) belong here.
const JOYRIDE_STYLES = {
  options: {
    zIndex: 10000,
    arrowColor: '#fff',
    primaryColor: '#008cba', // controls beacon colour (outer ring + inner fill)
  },
  // Match the v1 highlight (joyride-hole): a transparent cut-out with a 9999px box-shadow that
  // dims everything outside it (+ a soft 15px edge) and 4px rounded corners. v2's modern mode
  // uses a gray spotlight + mix-blend-mode overlay (a different look), so we force the legacy
  // box-shadow style and make the overlay transparent — dimming then comes solely from the
  // spotlight's box-shadow, exactly like v1.
  spotlight: {
    backgroundColor: 'transparent',
    borderRadius: 4,
    boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.5), 0 0 15px rgba(0, 0, 0, 0.5)',
  },
  overlay: {
    backgroundColor: 'transparent',
    mixBlendMode: 'normal',
  },
};

// v2 remounts react-floater on every step change. Step-to-step motion is this transition;
// the v1 pop-in scale runs once on step 0 only (see JoyrideTooltipV1 + SCSS --animate).
const JOYRIDE_FLOATER_PROPS = {
  styles: {
    floater: {
      transition: 'opacity 0.3s ease-out, transform 0.3s ease-out',
    },
    floaterOpening: {
      transition: 'opacity 0.3s ease-out, transform 0.3s ease-out',
    },
    floaterWithAnimation: {
      transition: 'opacity 0.3s ease-out, transform 0.3s ease-out',
    },
  },
};

// v1 kept one tooltip DOM node across steps, so joyride-tooltip--animate fired once.
// v2 mounts fresh floater/tooltip per step — pop-in only on index 0; later steps cross-fade.
const JoyrideTooltipV1 = ({ backProps, closeProps, index, isLastStep, primaryProps, step, tooltipProps }) => (
  <div
    className={`joyride-tooltip${index === 0 ? ' joyride-tooltip--animate' : ''}`}
    {...tooltipProps}
  >
    {/* The × is a CSS background icon (react-joyride.scss). closeProps injects children="Close"
        and title="Close" — we suppress both ({null} child + title undefined) so only the icon shows. */}
    <button className="joyride-tooltip__close joyride-tooltip__close--header" {...closeProps} title={undefined}>{null}</button>
    <div className="joyride-tooltip__header">{step.title}</div>
    <div className="joyride-tooltip__main">{step.content}</div>
    <div className="joyride-tooltip__footer">
      {/* Back uses Joyride's backProps (wired to prev()); shown from the 2nd step on, as in v1. */}
      {index > 0 && (
        <button className="joyride-tooltip__button joyride-tooltip__button--secondary" {...backProps}>
          Back
        </button>
      )}
      <button className="joyride-tooltip__button joyride-tooltip__button--primary" {...primaryProps}>
        {isLastStep ? 'Last' : 'Next'}
      </button>
    </div>
  </div>
);


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
    autoStart: true, // false only after a mid-tour remount that must not wait 500ms
    run: false,      // flipped true in componentDidUpdate once Redux enters RUNNING
  }

  // Assigned by Joyride via getHelpers — go() for resume, next()/prev() for TARGET_NOT_FOUND.
  joyrideHelpers = null;

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

  // v2 step shape: `target` (was `selector`), `content` (was `text`).
  getSteps = () => {
    const steps = [{
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
    }];

    // disableBeacon only on the first step: the tour opens directly into step 1's tooltip.
    // Steps 2+ keep beacons enabled — they never show during the forward Next flow (Joyride's
    // skipBeacon hides them for action=NEXT), but after × (action=CLOSE) Joyride advances one
    // step and shows a beacon there, exactly like v1. Clicking it reopens the tooltip; the tour
    // stays RUNNING the whole time (ExampleInvoice stays mounted).
    // placement: v1 defaulted to 'top'; v2 defaults to 'bottom' — keep portal behaviour.
    return steps.map((step, i) => ({
      ...step,
      placement: 'top',
      disableBeacon: i === 0,
      // Like v1: a click on the dark overlay must NOT dismiss the tour (only × / Links do).
      disableOverlayClose: true,
    }));
  };

  joyrideEventHandler = ({ action, index, status, type }) => {
    if (type === EVENTS.TOUR_END && status === STATUS.FINISHED) {
      this.onFinish();
    } else if (status === STATUS.SKIPPED) {
      this.askCancel();
    } else if (type === EVENTS.TOOLTIP || type === EVENTS.BEACON) {
      // Mirror Joyride's index into Redux. TOOLTIP covers Next/Back/beacon-reopen.
      // BEACON covers × (tooltip dismissed, tour still RUNNING on the next step).
      // Prefer TOOLTIP/BEACON over STEP_AFTER: Joyride drops duplicate same-action STEP_AFTER.
      this.onStepChanged(index);
    } else if (type === EVENTS.TARGET_NOT_FOUND) {
      // v2 non-controlled does not auto-skip missing DOM targets (v1 did via startIndex).
      setTimeout(() => {
        if (!this.joyrideHelpers) return;
        if (action === ACTIONS.NEXT) this.joyrideHelpers.next();
        else if (action === ACTIONS.PREV) this.joyrideHelpers.prev();
      }, 0);
    } else if (type === EVENTS.TOUR_START && this.props.step !== 0) {
      // Fresh mount after Pause — restore mirrored step once status === RUNNING.
      const { step } = this.props;
      setTimeout(() => {
        if (this.joyrideHelpers) {
          this.joyrideHelpers.go(step);
        }
      }, 0);
    }
  }

  renderIsReadyContent = () => (
    <Row>
      <Col {...MODAL_BODY_COL}>
        <p>In this short tutorial we will walk you through the main features of BillRun
           by examining a sample BillRun invoice and guiding you how to
           do it yourself with just a few clicks.
        </p>
      </Col>
      <Col {...MODAL_BODY_COL}>
        <Button onClick={this.onStart} variant="success">
          Let&apos;s start the tour!
        </Button>
      </Col>
    </Row>
  );

  renderIsFinishedContent = () => (
    <Row>
      <Col {...MODAL_BODY_COL}>
        <p>We&apos;re done!</p>
        <p>Thank you for taking the tour!</p>
      </Col>
      <Col {...MODAL_BODY_COL}>
        <Button onClick={this.onPending} variant="success">
          Start Using BillRun
        </Button>
      </Col>
    </Row>
  )

  
  componentDidUpdate(prevProps) {
    const { isPaused, isRunnig } = prevProps;
    const { autoStart } = this.state;
    // Entered RUNNING (fresh start or resume from pause): flip `run` on. Delay the very
    // first start so the ExampleInvoice DOM mounts before the first step positions itself.
    if ((isPaused && !this.props.isPaused) || (!isRunnig && this.props.isRunnig)) {
      if (autoStart) {
        setTimeout(this.switchToRun, 500);
      } else {
        this.switchToRun();
      }
    }
    // Left RUNNING (paused, finished or cancelled): reset `run` so the next mount starts
    // from run=false and the start-trigger above can flip it on again.
    if (isRunnig && !this.props.isRunnig && this.state.run) {
      this.setState({ run: false });
    }
  }

  render() {
    const { isRunnig, isFinished, isStarting, mobalTitle } = this.props;
    const { run } = this.state;
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
          spotlightPadding={5}
          showSkipButton={false}
          tooltipComponent={JoyrideTooltipV1}
          steps={this.getSteps()}
          run={run}
          callback={this.joyrideEventHandler}
          getHelpers={(helpers) => { this.joyrideHelpers = helpers; }} // see joyrideEventHandler
          floaterProps={JOYRIDE_FLOATER_PROPS}
          styles={JOYRIDE_STYLES}
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
