import { connect } from 'react-redux';
import ExampleInvoice from './ExampleInvoice';
import {
  setOnBoardingStep,
  pendingOnBoarding,
  pauseOnBoarding,
  showConfirmModal,
} from '@/actions/guiStateActions/pageActions';

const mapDispatchToProps = dispatch => ({
  onPause: () => {
    dispatch(pauseOnBoarding());
  },
  onStop: () => {
    const onStop = () => {
      dispatch(setOnBoardingStep(0));
      dispatch(pendingOnBoarding());
    };
    const confirm = {
      message: 'Are you sure you want to end the tour ?',
      onOk: onStop,
      labelOk: 'End tour',
      labelCancel: 'Continue tour',
      type: 'delete',
    };
    dispatch(showConfirmModal(confirm));
  },
});

export default connect(null, mapDispatchToProps)(ExampleInvoice);
