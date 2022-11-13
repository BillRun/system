import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import { Col, Row, PageHeader } from 'react-bootstrap';
import { ProgressIndicator } from '@/components/Elements';
import ReduxConfirmModal from '../ReduxConfirmModal';
import ReduxFormModal from '../ReduxFormModal';
import Navigator from '../Navigator';
import Alerts from '../Alerts';
import { Tour, ExampleInvoice } from '../OnBoarding';
import { Footer } from '../StaticPages';
import { userCheckLogin } from '@/actions/userActions';
import { setPageTitle, systemRequirementsLoadingComplete } from '@/actions/guiStateActions/pageActions';
import { initMainMenu } from '@/actions/guiStateActions/menuActions';
import { getSettings, fetchFile } from '@/actions/settingsActions';
import { onBoardingIsRunnigSelector } from '@/selectors/guiSelectors';
import { taxationTypeSelector } from '@/selectors/settingsSelector';

class App extends Component {

  static displayName = 'App';

  static propTypes = {
    auth: PropTypes.bool,
    systemRequirementsLoad: PropTypes.bool,
    isTourRunnig: PropTypes.bool,
    routes: PropTypes.array,
    children: PropTypes.element,
    title: PropTypes.string,
    logo: PropTypes.string,
    taxType: PropTypes.string,
    mainMenuOverrides: PropTypes.oneOfType([
      PropTypes.instanceOf(Immutable.Iterable),
    ]),
    logoName: PropTypes.oneOfType([
      PropTypes.string,
    ]),
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    mainMenuOverrides: null,
    auth: null,
    title: '',
    logoName: '',
    taxType: '',
    systemRequirementsLoad: false,
    isTourRunnig: false,
  };

  componentWillMount() {
    this.props.dispatch(userCheckLogin());
  }

  componentDidMount() {
    const { routes, title } = this.props;
    const newTitle = routes[routes.length - 1].title || title;
    if (newTitle.length) {
      this.props.dispatch(setPageTitle(newTitle));
    }
  }

  componentWillReceiveProps(nextProps) {
    const { title, auth, mainMenuOverrides } = this.props;

    // Update main menu with tenant overrides
    if (mainMenuOverrides === null && nextProps.mainMenuOverrides !== null) {
      this.props.dispatch(initMainMenu(nextProps.mainMenuOverrides));
    }
    const nextTitle = nextProps.routes[nextProps.routes.length - 1].title;
    if (typeof nextTitle !== 'undefined' && nextTitle !== title) {
      this.props.dispatch(setPageTitle(nextTitle));
    }
    if (auth !== true && nextProps.auth === true) { // user did success login
      // get global system settings
      this.props.dispatch(getSettings(['pricing', 'tenant', 'menu', 'billrun', 'usage_types', 'property_types', 'plays', 'taxation']))
        .then(responce => ((responce) ? this.props.logoName : ''))
        .then((logoFileName) => {
          if (logoFileName && logoFileName.length > 0) {
            return this.props.dispatch(fetchFile({ filename: logoFileName }, 'logo'));
          }
          return true;
        })
        .then(() => {
          this.props.dispatch(systemRequirementsLoadingComplete());
        });
    }
  }

  getView = () => {
    const { auth, systemRequirementsLoad } = this.props;
    let appState = 'waiting';
    if (auth === false) {
      appState = 'noLogin';
    } else if (systemRequirementsLoad && auth === true) {
      appState = 'ready';
    }

    switch (appState) {
      case 'ready':
        return this.renderWithLayout();
      case 'noLogin':
        return this.renderWithoutLayout();
      default: // 'waiting'
        return this.renderAppLoading();
    }
  }

  renderAppLoading = () => {
    const { logo } = this.props;
    return (
      <div>
        <ProgressIndicator />
        <Alerts />
        <div className="container">
          <Col md={4} mdOffset={4}>
            <div style={{ marginTop: '33%', textAlign: 'center' }}>
              <img alt="logo" src={logo} style={{ height: 50 }} />
              <br />
              <br />
              <br />
              <p>Loading...</p>
            </div>
          </Col>
        </div>
      </div>
    );
  }

  renderWithoutLayout = () => (
    <div>
      <ProgressIndicator />
      <Alerts />
      <div className="container">
        { this.props.children }
      </div>
    </div>
  );

  renderWithLayout = () => {
    const { title, children, routes, isTourRunnig, taxType } = this.props;
    const hiddenMenuItems = (taxType === 'CSI') ? ['tax'] : [];
    return (
      <div id="wrapper">
        <ProgressIndicator />
        <ReduxConfirmModal />
        <ReduxFormModal />
        <Alerts />
        <Tour />
        <Navigator routes={routes} hiddenItems={hiddenMenuItems}/>
        <div id="page-wrapper" className="page-wrapper">
          { isTourRunnig && <ExampleInvoice />}
          <Row>
            <Col lg={12}>{title && <PageHeader>{title}</PageHeader> }</Col>
          </Row>
          <div>{children}</div>
          <div id="footer-push" />
        </div>
        <Footer />
      </div>
    );
  }

  render() {
    return (
      <div>
        { this.getView() }
      </div>
    );
  }
}


const mapStateToProps = state => ({
  auth: state.user.get('auth'),
  title: state.guiState.page.get('title'),
  systemRequirementsLoad: state.guiState.page.get('systemRequirementsLoad'),
  mainMenuOverrides: state.settings.getIn(['menu', 'main']),
  logo: state.settings.getIn(['files', 'logo']),
  logoName: state.settings.getIn(['tenant', 'logo']),
  isTourRunnig: onBoardingIsRunnigSelector(state),
  taxType: taxationTypeSelector(state),
});

export default withRouter(connect(mapStateToProps)(App));
