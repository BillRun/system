import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, Panel } from 'react-bootstrap';
import { ActionButtons, Stepper } from '@/components/Elements';
import Segmentation from './Steps/Segmentation';
import Filtration from './Steps/Filtration';
import Mapping from './Steps/Mapping';
import SenderFtp from './Steps/SenderFtp';
import {
  getExportGenerator,
  clearExportGenerator,
  updateExportGeneratorValue,
  removeExportGeneratorValue,
  saveExportGenerator,
} from '@/actions/exportGeneratorActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { getSettings } from '@/actions/settingsActions';


class ExportGeneratorSetup extends Component {

  static propTypes = {
    exportGenerator: PropTypes.instanceOf(Immutable.Map),
    exportGeneratorName: PropTypes.string,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    exportGenerator: Immutable.Map(),
    exportGeneratorName: '',
  };

  state = {
    stepIndex: 0,
  }

  componentDidMount() {
    const { exportGeneratorName } = this.props;
    this.props.dispatch(getSettings([
      'file_types',
      'lines.fields',
      'rates.fields',
    ]));
    this.props.dispatch(clearExportGenerator());
    if (exportGeneratorName) {
      this.props.dispatch(getExportGenerator(exportGeneratorName));
    }
  }

  componentWillUnmount(prevProps, prevState) { // eslint-disable-line no-unused-vars
    this.props.dispatch(clearExportGenerator());
  }

  onChangeValue = (path, value) => {
    this.props.dispatch(updateExportGeneratorValue(path, value));
  }

  onRemoveValue = (path) => {
    this.props.dispatch(removeExportGeneratorValue(path));
  }

  onCancel = () => {
    const confirm = {
      message: `Are you sure you want to stop editing Export Generator ?`,
      onOk: () => this.onCancelOk(),
      type: 'delete',
      labelOk: 'Yes',
      labelCancel: 'No',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onCancelOk = () => {
    this.props.router.push('export_generators');
  }

  onFinish = () => {
    const { exportGenerator } = this.props;
    this.props.dispatch(saveExportGenerator(exportGenerator)).then(
      () => this.props.router.push('export_generators')
    );
  }

  onNextStep = () => {
    const { stepIndex } = this.state;
    const steps = this.getSteps();
    if (stepIndex < steps.length -1 ) {
      this.setState({ stepIndex: stepIndex + 1 });
    }
  }

  onPrevStep = () => {
    const { stepIndex } = this.state;
    if (stepIndex > 0) {
      this.setState({ stepIndex: stepIndex - 1 });
    }
  }

  getSteps = (index = null) => {
    const steps = [];
    steps.push({id: 'segmentation', stepData:{ title: 'Segmentation'}});
    steps.push({id: 'filtration', stepData:{ title: 'Filtration'}});
    steps.push({id: 'mapping', stepData:{ title: 'Field Mapping'}});
    steps.push({id: 'senders', stepData:{ title: 'Senders'}, okLabel: 'Save', okAction: this.onFinish});
    if (index === null) {
      return steps;
    }
    return steps[index] || {};
  }

  renderStepContent = () => {
    const { exportGenerator, exportGeneratorName } = this.props;
    const { stepIndex } = this.state;
    const step = this.getSteps(stepIndex);
    const mode = exportGeneratorName.length > 0 ? 'edit': 'create';

    switch (step.id) {
      case 'segmentation':
        return (<Segmentation data={exportGenerator} onChange={this.onChangeValue} onRemove={this.onRemoveValue} mode={mode} />);
      case 'filtration':
        return (<Filtration data={exportGenerator} onChange={this.onChangeValue} onRemove={this.onRemoveValue} />);
      case 'mapping':
        return (<Mapping data={exportGenerator} onChange={this.onChangeValue} onRemove={this.onRemoveValue} />);
      case 'senders':
        return (<SenderFtp data={exportGenerator} onChange={this.onChangeValue} onRemove={this.onRemoveValue} />);
      default:
        return (<p>Not valid Step</p>);
    }
  }

  renderStepper = () => {
    const { stepIndex } = this.state;
    const steps = this.getSteps();
    return (
      <Stepper activeIndex={stepIndex} steps={steps.map(step => step.stepData)} />
    );
  }

  getOkButtonLabel = () => {
    const { stepIndex } = this.state;
    const step = this.getSteps(stepIndex);
    return step.okLabel || 'Next';
  }

  getOkButtonAction = () => {
    const { stepIndex } = this.state;
    const step = this.getSteps(stepIndex);
    return step.okAction || this.onNextStep;
  }

  renderActionButtons = () => {
    return (
      <div className="form-actions-controllers">
        <div className="pull-left">
          <ActionButtons
            hideSave={true}
            onClickCancel={this.onCancel}
          />
        </div>
        <div className="pull-right">
          <ActionButtons
            cancelLabel={this.getOkButtonLabel()}
            onClickCancel={this.getOkButtonAction()}
            saveLabel="Back"
            onClickSave={this.onPrevStep}
            reversed={true}
          />
        </div>
      </div>
    );
  }

  render() {
    return (
      <div className="Importer">
        <Panel header={this.renderStepper()} className="mb0">
          <Form horizontal className="mb0">
            {this.renderStepContent()}
          </Form>
        </Panel>
        {this.renderActionButtons()}
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  exportGenerator: state.exportGenerator,
  exportGeneratorName: props.params.name,
});

export default connect(mapStateToProps)(ExportGeneratorSetup);
