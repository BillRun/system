import { connect } from 'react-redux';
import OnBoardingNavigation from './OnBoardingNavigation';
import {
  runOnBoarding,
  startOnBoarding,
} from '@/actions/guiStateActions/pageActions';
import {
  onBoardingIsRunnigSelector,
  onBoardingIsReadySelector,
  onBoardingIsPausedSelector,
} from '@/selectors/guiSelectors';

const mapStateToProps = state => ({
  isRunnig: onBoardingIsRunnigSelector(state),
  isReady: onBoardingIsReadySelector(state),
  isPaused: onBoardingIsPausedSelector(state),
});

const mapDispatchToProps = dispatch => ({
  onRun: () => {
    dispatch(runOnBoarding());
  },
  onStart: () => {
    dispatch(startOnBoarding());
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(OnBoardingNavigation);
