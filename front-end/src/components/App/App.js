import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Outlet } from 'react-router-dom';
import withRouter from '@/common/withRouter';
import Immutable from 'immutable';
import { Col, Row } from 'react-bootstrap';
import { PageHeader } from '@/common/BootstrapCompat';
import { ProgressIndicator } from '@/components/Elements';
import ReduxConfirmModal from '../ReduxConfirmModal';
import ReduxFormModal from '../ReduxFormModal';
import Navigator from '../Navigator';
import Alerts from '../Alerts';
import { Tour, ExampleInvoice } from '../OnBoarding';
import { Footer } from '../StaticPages';
import { userCheckLogin } from '@/actions/userActions';
import { systemRequirementsLoadingComplete, setPageTitle } from '@/actions/guiStateActions/pageActions';
import { initMainMenu } from '@/actions/guiStateActions/menuActions';
import { getSettings, fetchFile } from '@/actions/settingsActions';
import { onBoardingIsRunnigSelector } from '@/selectors/guiSelectors';
import { taxationTypeSelector } from '@/selectors/settingsSelector';
import { showDanger } from '@/actions/alertsActions';
import { getWorkersStatus } from '@/actions/guiStateActions/appActions';

class App extends Component {

  static displayName = 'App';

  static propTypes = {
    auth: PropTypes.bool,
    systemRequirementsLoad: PropTypes.bool,
    isTourRunnig: PropTypes.bool,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
      isActive: PropTypes.func.isRequired,
    }),
    children: PropTypes.element,
    title: PropTypes.string,
    logo: PropTypes.string,
    taxType: PropTypes.string,
    mainMenuOverrides: PropTypes.oneOfType([
      PropTypes.instanceOf(Immutable.Iterable),
    ]),
    mainMenu: PropTypes.instanceOf(Immutable.Iterable),
    logoName: PropTypes.oneOfType([
      PropTypes.string,
    ]),
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    mainMenuOverrides: null,
    mainMenu: Immutable.List(),
    auth: null,
    title: '',
    logoName: '',
    taxType: '',
    systemRequirementsLoad: false,
    isTourRunnig: false,
  };

  
  componentDidMount() {
    this.props.dispatch(userCheckLogin());
    // In react-router v6 there is no this.props.routes.
    // Page title is set per-route via componentDidUpdate when the title prop changes.
  }

  
  // eslint-disable-next-line react/sort-comp
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
          <div className="col-md-4 col-md-offset-4">
            <div style={{ marginTop: '33%', textAlign: 'center' }}>
              <img alt="logo" src={logo} style={{ height: 50 }} />
              <br />
              <br />
              <br />
              <p>Loading...</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  renderWithoutLayout = () => (
    <div>
      <ProgressIndicator />
      <Alerts />
      <div className="container">
        <Outlet />
      </div>
    </div>
  );

  renderWithLayout = () => {
    const { title, isTourRunnig, taxType, router } = this.props;
    const hiddenMenuItems = (taxType === 'CSI') ? ['tax'] : [];
    return (
      <div id="wrapper">
        <ProgressIndicator />
        <ReduxConfirmModal />
        <ReduxFormModal />
        <Alerts />
        <Tour />
        <Navigator hiddenItems={hiddenMenuItems} router={router} />
        <div id="page-wrapper" className="page-wrapper">
          { isTourRunnig && <ExampleInvoice />}
          <Row>
            <Col lg={12}>{title && <PageHeader>{title}</PageHeader> }</Col>
          </Row>
          {/* v6: nested routes render via <Outlet /> instead of this.props.children */}
          <div><Outlet /></div>
          <div id="footer-push" />
        </div>
        <Footer />
      </div>
    );
  }

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { auth, mainMenuOverrides } = prevProps;

    // Update main menu with tenant overrides
    if (mainMenuOverrides === null && this.props.mainMenuOverrides !== null) {
      this.props.dispatch(initMainMenu(this.props.mainMenuOverrides));
    }
    const prevPathname = prevProps.router?.location?.pathname || '';
    const pathname = this.props.router?.location?.pathname || '';
    if (this.props.auth === true && pathname !== prevPathname) {
      this.syncMenuPageTitle();
    }
    // In react-router v6 there is no this.props.routes — page title is set
    // by individual route components dispatching setPageTitle.
    if (auth !== true && this.props.auth === true) { // user did success login
      // get global system settings
      this.props.dispatch(getSettings(['pricing', 'tenant', 'menu', 'billrun', 'usage_types', 'property_types', 'plays', 'taxation']))
        .then(response => {
          if (response) {
            return response;
          }
          this.props.dispatch(showDanger('Error, can not load required settings'));
          throw new Error();
        })
        .then(response => response ? prevProps.logoName : '')
        .then((logoFileName) => {
          if (logoFileName && logoFileName.length > 0) {
            return this.props.dispatch(fetchFile({ filename: logoFileName }, 'logo'));
          }
          return true;
        })
        .then(() => {
          this.props.dispatch(systemRequirementsLoadingComplete());
        })
        .then(() => {
          this.props.dispatch(getWorkersStatus());
        })
        .then(() => {
          this.syncMenuPageTitle();
        })
        .catch(() => {});
    }
  }

  getMenuTitleByPathname = (pathname) => {
    const { mainMenu } = this.props;
    const route = (pathname || '').replace(/^\//, '');
    if (!route || !Immutable.Iterable.isIterable(mainMenu)) {
      return '';
    }

    const stack = mainMenu.toArray();
    while (stack.length > 0) {
      const menuItem = stack.pop();
      const menuRoute = menuItem.get('route', '');
      if (menuRoute === route) {
        return menuItem.get('title', '');
      }
      const subMenus = menuItem.get('subMenus', Immutable.List());
      if (subMenus.size > 0) {
        stack.push(...subMenus.toArray());
      }
    }
    return '';
  }

  syncMenuPageTitle = () => {
    const pathname = this.props.router?.location?.pathname || '';
    const menuTitle = this.getMenuTitleByPathname(pathname);
    if (menuTitle) {
      this.props.dispatch(setPageTitle(menuTitle));
    }
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
  mainMenu: state.guiState.menu.get('main', Immutable.List()),
  logo: state.settings.getIn(['files', 'logo']),
  logoName: state.settings.getIn(['tenant', 'logo']),
  isTourRunnig: onBoardingIsRunnigSelector(state),
  taxType: taxationTypeSelector(state),
});

export default withRouter(connect(mapStateToProps)(App));
