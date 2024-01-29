import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import flattenDeep from 'lodash.flattendeep';
import Papa from 'papaparse';
import Templates from '@/config/Templates';
import { Stepper } from '@/components/Elements';
import SampleCSV from './SampleCSV';
import FieldsMapping from './FieldsMapping';
import CustomerMappings from './CustomerMapping/CustomerMappings';
import RateMappings from './RateMapping/RateMappings';
import PricingMappings from './PricingMapping/PricingMappings';
import Receiver from './Receiver/Receiver';
import RealtimeMapping from './RealtimeMapping';
import {
  setProcessorType,
  setParserSetting,
  setInputProcessorTemplate,
  clearInputProcessor,
  getProcessorSettings,
  setName, setDelimiterType,
  setDelimiter,
  updateInputProcessorField,
  setFields,
  setFieldMapping,
  setFieldWidth,
  addCSVField,
  setCustomerMapping,
  setPricingMapping,
  setReceiverField,
  saveInputProcessorSettings,
  removeCSVField,
  removeAllCSVFields,
  checkAllFields,
  mapUsaget,
  removeUsagetMapping,
  setUsagetType,
  setStaticUsaget,
  moveCSVFieldUp,
  moveCSVFieldDown,
  changeCSVField,
  unsetField,
  setRealtimeField,
  setRealtimeDefaultField,
  cancelKeyAuth,
  setCheckedField,
 } from '@/actions/inputProcessorActions';
import { getSettings } from '@/actions/settingsActions';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import { getList, clearList } from '@/actions/listActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import {
  usageTypeSelector,
  usageTypesDataSelector,
  propertyTypeSelector,
  productFieldsSelector,
  subscriberFieldsWithPlaySelector,
} from '@/selectors/settingsSelector';
import { getConfig } from '@/common/Util';

class InputProcessor extends Component {

  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.Map),
    usageTypes: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    subscriberFields: PropTypes.instanceOf(Immutable.List),
    customRatingFields: PropTypes.instanceOf(Immutable.List),
    inputProcessorsExitNames: PropTypes.instanceOf(Immutable.List),
    dispatch: PropTypes.func.isRequired,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    fileType: PropTypes.string,
    action: PropTypes.string,
    template: PropTypes.string,
    type: PropTypes.string,
    format: PropTypes.string,
  };

  static defaultProps = {
    settings: Immutable.Map(),
    subscriberFields: Immutable.List(),
    customRatingFields: Immutable.List(),
    inputProcessorsExitNames: Immutable.List(),
    usageTypes: Immutable.List(),
    propertyTypes: Immutable.List(),
    usageTypesData: Immutable.List(),
    fileType: '',
    action: 'new',
    template: null,
    type: null,
    format: null,
  };

  constructor(props) {
    super(props);

    const errors = Immutable.Map({
      sampleCSV: Immutable.Map(),
      fieldsMapping: Immutable.Map(),
      calculatorMapping: Immutable.Map(),
      realtimeMapping: Immutable.Map(),
      receiver: Immutable.Map(),
    });

    let steps = Immutable.Map({
      parser: {
        idx: 0,
        label: 'CDR Fields',
        parts: ['file_type', 'parser', 'filters'],
      },
      processor: {
        idx: 1,
        label: 'Field Mapping',
        parts: ['file_type', 'parser', 'processor', 'filters'],
      },
      customer_identification_fields: {
        idx: 2,
        label: 'Customer Mapping',
        parts: ['file_type', 'parser', 'processor', 'customer_identification_fields', 'filters'],
      },
      rate_calculators: {
        idx: 3,
        label: 'Rate Mapping',
        parts: ['file_type', 'parser', 'processor', 'customer_identification_fields', 'rate_calculators', 'filters'],
      },
      pricing: {
        idx: 4,
        label: 'Pricing',
        parts: ['file_type', 'parser', 'processor', 'customer_identification_fields', 'rate_calculators', 'pricing', 'filters'],
      },
    });
    if (props.type === 'api') {
      steps = steps.set('realtimeMapping', {
        idx: 5,
        label: 'Realtime Mapping',
        parts: ['file_type', 'parser', 'processor', 'customer_identification_fields', 'rate_calculators', 'pricing', 'realtime', 'response', 'unify', 'filters'],
      });
    } else {
      steps = steps.set('receiver', {
        idx: 5,
        label: 'Receiver',
        parts: ['file_type', 'parser', 'processor', 'customer_identification_fields', 'rate_calculators', 'pricing', 'receiver', 'unify', 'filters'],
      });
    }

    this.state = {
      stepIndex: 0,
      errors,
      steps,
      uploadingFile: false,
    };
  }

  componentDidMount() {
    const { fileType, action, template, type, format } = this.props;
    let pageTitle = '';
    if (action === 'new') {
      if (template) {
        this.props.dispatch(setInputProcessorTemplate(Templates[template]));
        this.props.dispatch(setName(''));
        pageTitle = `Create New Input Processor - ${template}`;
      } else {
        pageTitle = 'Create New Input Processor';
      }
      if (type === 'api' && format === 'json') {
        this.props.dispatch(setParserSetting('type', 'realtime'));
        this.props.dispatch(setDelimiterType('json'));
        this.props.dispatch(setProcessorType('realtime'));
      }
      this.props.dispatch(getList('all_input_processors', this.buildGetAllQuery()));
    } else {
      this.props.dispatch(getProcessorSettings(fileType));
      pageTitle = 'Edit Input Processor';
    }
    this.props.dispatch(getSettings(['usage_types', 'property_types', 'subscribers.subscriber.fields', 'rates.fields']));
    this.props.dispatch(setPageTitle(pageTitle));
  }

  componentWillReceiveProps(nextProps) {
    const { settings, action } = this.props;
    const name = settings.get('file_type', '');
    const newName = nextProps.settings.get('file_type', '');
    if (action !== 'new' && newName !== name) {
      this.props.dispatch(setPageTitle(`Edit Input Processor - ${newName}`));
    }
  }

  componentWillUpdate(nextProps, nextState) {
    const { settings, action, template } = this.props;
    const { stepIndex } = this.state;
    const name = settings.get('file_type', '');
    const newName = nextProps.settings.get('file_type', '');
    // show name only from second step.
    if (action === 'new' && ((newName !== name && stepIndex >= 1) || (nextState.stepIndex >= 1 && stepIndex === 0))) {
      const templateName = template ? ` - ${template}` : '';
      const inputProcessorName = newName.length > 0 ? ` - ${newName}` : '';
      this.props.dispatch(setPageTitle(`Create New Input Processor${templateName}${inputProcessorName}`));
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearInputProcessor());
    this.props.dispatch(clearList('all_input_processors'));
  }

  buildGetAllQuery = () => ({
    api: 'settings',
    params: [
      { category: 'file_types' },
      { data: JSON.stringify({}) },
    ],
  })

  onChangeName = (e) => {
    const { inputProcessorsExitNames, action } = this.props;
    const { errors } = this.state;
    const { value } = e.target;
    if ( action === 'new' && !getConfig('keyRegex', '').test(value)) {
      this.setState({ errors: errors.setIn(['sampleCSV', 'name'], 'Name contains illegal characters, name should contain only alphabets, numbers and underscores (A-Z, a-z, 0-9, _)') });
    }
    else if (inputProcessorsExitNames.includes(value)) {
      this.setState({ errors: errors.setIn(['sampleCSV', 'name'], `Name ${value} already exists`) });
    } else {
      this.setState({ errors: errors.deleteIn(['sampleCSV', 'name']) });
    }
    this.props.dispatch(setName(e.target.value));
  }

  onSetDelimiterType = (e) => {
    this.props.dispatch(setDelimiterType(e.target.value));
  }

  onChangeInputProcessorField = (fieldPath, value) => {
    this.props.dispatch(updateInputProcessorField(fieldPath, value));
  }

  onChangeDelimiter = (e) => {
    this.props.dispatch(setDelimiter(e.target.value));
  }

  onSelectJSON = (e) => {
    const file = e.target.files[0];
    if (typeof file === 'undefined') {
      return;
    }
    const reader = new FileReader();
    reader.onloadend = ((evt) => {
      if (evt.target.readyState === FileReader.DONE) {
        try {
          const json = JSON.parse(evt.target.result);
          const fields = Object.keys(json).map(key => this.buildJSONFields(key, json));
          this.props.dispatch(setFields(flattenDeep(fields)));
        } catch (err) {
          this.props.dispatch(showDanger('Not a valid JSON'));
        }
      }
    });
    const blob = file.slice(0, file.size - 1);
    reader.readAsText(blob);
  };

  onSelectSampleCSV = (e) => {
    const { settings } = this.props;
    const { files } = e.target;
    const delimiter = settings.get('delimiter', ',');
    Papa.parse(files[0], {
      dynamicTyping: false,
      skipEmptyLines: true,
      delimiter,
      header: true,
      complete: this.onParseScvComplete,
      error: this.onParseScvError,
    });
  }

  onParseScvError = (error, file) => { // eslint-disable-line no-unused-vars
    this.props.dispatch(showDanger('Error in CSV file'));
  }

  onParseScvComplete = (results) => {
    if (results.meta && results.meta.fields && results.meta.fields.length > 0) {
      this.props.dispatch(setFields([])); // empty existing fields
      const whiteListCharacters = new RegExp('[^A-Za-z0-9_]', 'g');
      const cleanFields = results.meta.fields.map(field => field.replace(whiteListCharacters, '_'))
                                              .map(field => ({ name: field, checked: true }));
      this.props.dispatch(setFields(cleanFields));
    } else {
      this.props.dispatch(showDanger('Error in CSV file, no headers found.'));
    }
  }

  onAddField = (val, e) => { // eslint-disable-line no-unused-vars
    this.props.dispatch(addCSVField(''));
  }

  onRemoveField = (index, e) => { // eslint-disable-line no-unused-vars
    this.props.dispatch(removeCSVField(index));
  }

  onRemoveAllFields = () => {
    this.props.dispatch(removeAllCSVFields());
  }

  onCheckAllFields = (checked) => {
    this.props.dispatch(checkAllFields(checked));
  }

  onSetFieldMapping = (e) => {
    const { value: mapping, id: field } = e.target;
    this.props.dispatch(setFieldMapping(field, mapping));
  }

  onSetFieldWidth = (e) => {
    const { value, dataset: { field } } = e.target;
    this.props.dispatch(setFieldWidth(field, value));
  }

  onAddUsagetMapping = (val) => {
    this.props.dispatch(mapUsaget(val));
  }

  onSetStaticUsaget = (val) => {
    this.props.dispatch(setStaticUsaget(val));
  }

  onRemoveUsagetMapping = (index, e) => { // eslint-disable-line no-unused-vars
    this.props.dispatch(removeUsagetMapping(index));
  }

  onSetCustomerMapping = (field, mapping, usaget, index) => {
    this.props.dispatch(setCustomerMapping(field, mapping, usaget, index));
  }

  onSetPricingMapping = (field, mapping, usaget) => {
    this.props.dispatch(setPricingMapping(field, mapping, usaget));
  }

  onSetReceiverField = (id, value, index) => {
    this.props.dispatch(setReceiverField(id, value, index));
  }

  onCancelKeyAuth = (index) => {
    this.props.dispatch(cancelKeyAuth('key', index));
  }

  onSetReceiverCheckboxField = (id, checked, index) => {
    this.props.dispatch(setReceiverField(id, checked, index));
  }

  onMoveFieldUp = (index) => {
    this.props.dispatch(moveCSVFieldUp(index));
  };

  onMoveFieldDown = (index) => {
    this.props.dispatch(moveCSVFieldDown(index));
  };

  onChangeCSVField = (index, value) => {
    this.props.dispatch(changeCSVField(index, value));
  };

  onChangeRealtimeField = (e) => {
    const { id, value } = e.target;
    this.props.dispatch(setRealtimeField(id, value));
  };

  onChangeRealtimeDefaultField = (e) => {
    const { id, value } = e.target;
    this.props.dispatch(setRealtimeDefaultField(id, value));
  };

  onError = (message) => {
    this.props.dispatch(showDanger(message));
  }

  onCheckedField = (index, checked, field) => {
    this.props.dispatch(setCheckedField(index, checked, field));
  }

  setUsagetType = (val) => {
    this.props.dispatch(setUsagetType(val));
  }

  unsetField = (fieldPath) => {
    this.props.dispatch(unsetField(fieldPath));
  }

  buildJSONFields = (field, obj) => {
    if (typeof obj[field] === 'object' && !Array.isArray(obj[field])) {
      const nested = Object.keys(obj[field]).map(key => this.buildJSONFields(key, obj[field]));
      return nested.map(n => `${field}.${n}`);
    }
    return field;
  };

  goBack = () => {
    this.props.router.push('/input_processors');
  }

  handleNext = () => {
    const { stepIndex, steps } = this.state;
    const isLastStep = (stepIndex === steps.size - 1);
    const parts = isLastStep ? [] : steps.get(steps.findKey(step => step.idx === stepIndex), {}).parts;
    this.props.dispatch(saveInputProcessorSettings(this.props.settings, parts)
      ).then((response) => {
        if (response !== false && !isLastStep) {
          this.setState({ stepIndex: stepIndex + 1 });
          return false;
        } else if (response !== false && isLastStep) {
          return response;
        }
        return false;
      }).then((saveStatus) => {
        if (saveStatus !== false) {
          this.props.dispatch(showSuccess('Input processor saved successfully!'));
          this.goBack();
        }
      });
  }

  handlePrev = () => {
    const { stepIndex } = this.state;
    if (stepIndex > 0) {
      this.setState({ stepIndex: stepIndex - 1 });
    } else {
      this.handleCancel();
    }
  }

  handleCancel = () => {
    const r = window.confirm('Are you sure you want to stop editing input processor ?');
    if (r) {
      this.goBack();
    }
  }

  isValidForm = () => {
    const { errors } = this.state;
    const hasEroor = errors.reduce((errorExist, section) =>
      errorExist || !section.isEmpty()
    , false);
    return !hasEroor;
  }

  changeUploadingFile = () => {
    const { uploadingFile } = this.state;
    this.setState({ uploadingFile: !uploadingFile });
  }

  getStepContent = () => {
    const {
      settings,
      usageTypes,
      usageTypesData,
      propertyTypes,
      subscriberFields,
      customRatingFields,
      action,
      type,
      format,
      fileType,
    } = this.props;
    const { stepIndex, errors, steps } = this.state;

    switch (stepIndex) {
      case steps.get('parser', {}).idx: return (
        <SampleCSV
          settings={settings}
          type={type}
          action={action}
          format={format}
          errors={errors.get('sampleCSV', Immutable.Map())}
          onAddField={this.onAddField}
          onSelectJSON={this.onSelectJSON}
          onChangeName={this.onChangeName}
          onRemoveField={this.onRemoveField}
          onMoveFieldUp={this.onMoveFieldUp}
          onSetFieldWidth={this.onSetFieldWidth}
          onMoveFieldDown={this.onMoveFieldDown}
          onChangeCSVField={this.onChangeCSVField}
          onChangeDelimiter={this.onChangeDelimiter}
          onSelectSampleCSV={this.onSelectSampleCSV}
          onRemoveAllFields={this.onRemoveAllFields}
          onSetDelimiterType={this.onSetDelimiterType}
          onChangeInputProcessorField={this.onChangeInputProcessorField}
          onCheckedField={this.onCheckedField}
          checkAllFields={this.onCheckAllFields}
        />
      );

      case steps.get('processor', {}).idx: return (
        <FieldsMapping
          settings={settings}
          usageTypes={usageTypes}
          usageTypesData={usageTypesData}
          propertyTypes={propertyTypes}
          onError={this.onError}
          unsetField={this.unsetField}
          setUsagetType={this.setUsagetType}
          onSetStaticUsaget={this.onSetStaticUsaget}
          onSetFieldMapping={this.onSetFieldMapping}
          onAddUsagetMapping={this.onAddUsagetMapping}
          onRemoveUsagetMapping={this.onRemoveUsagetMapping}
        />
      );

      case steps.get('customer_identification_fields', {}).idx: return (
        <CustomerMappings
          settings={settings}
          subscriberFields={subscriberFields}
          onSetCustomerMapping={this.onSetCustomerMapping}
        />
      );

      case steps.get('rate_calculators', {}).idx: return (
        <RateMappings
          settings={settings}
          customRatingFields={customRatingFields}
        />
      );

      case steps.get('pricing', {}).idx: return (
        <PricingMappings
          settings={settings}
          onSetPricingMapping={this.onSetPricingMapping}
        />
      );

      case steps.get('realtimeMapping', {}).idx: return (
        <RealtimeMapping
          settings={settings}
          onChange={this.onChangeRealtimeField}
          onChangeDefault={this.onChangeRealtimeDefaultField}
        />
      );

      case steps.get('receiver', {}).idx: return (
        <Receiver
          settings={settings}
          onSetReceiverField={this.onSetReceiverField}
          onSetReceiverCheckboxField={this.onSetReceiverCheckboxField}
          onCancelKeyAuth={this.onCancelKeyAuth}
          fileType={fileType}
          OnChangeUploadingFile={this.changeUploadingFile}
        />
      );

      default: return null;
    }
  }

  renderStepper = () => {
    const { stepIndex, steps } = this.state;
    const ipSteps = steps
      .sortBy((step => step.idx))
      .map(step => ({title: step.label}))
      .toList()
      .toArray();
    return (
      <Stepper activeIndex={stepIndex} steps={ipSteps} />
    );
  }

  render() {
    const { stepIndex, steps, uploadingFile } = this.state;
    const isValidForm = this.isValidForm();
    return (
      <div>
        <div className="row">
          <div className="col-lg-12">
            <div className="panel panel-default">
              <div className="panel-heading">
                { this.renderStepper() }
              </div>
              <div className="panel-body">
                <div className="contents bordered-container">
                  { this.getStepContent() }
                </div>
              </div>
              <div style={{ marginTop: 12, float: 'right' }}>
                <button className="btn btn-default" onClick={this.handleCancel} style={{ marginRight: 12 }} > Cancel </button>
                { (stepIndex > 0) && <button className="btn btn-default" onClick={this.handlePrev} style={{ marginRight: 12 }} > Back </button>}
                <button disabled={!isValidForm || uploadingFile} className="btn btn-primary" onClick={this.handleNext} > { stepIndex === (steps.size - 1) ? 'Finish' : 'Next' }</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }
}


const mapStateToProps = (state, props) => {
  const { file_type: fileType, action, template, type, format } = props.location.query;
  return {
    inputProcessorsExitNames: state.list.get('all_input_processors', Immutable.List()).map(ip => ip.get('file_type', '')),
    settings: state.inputProcessor,
    usageTypes: usageTypeSelector(state, props),
    propertyTypes: propertyTypeSelector(state, props),
    usageTypesData: usageTypesDataSelector(state, props),
    subscriberFields: subscriberFieldsWithPlaySelector(state, props),
    customRatingFields: productFieldsSelector(state, props),
    fileType,
    action,
    template,
    type,
    format,
  };
};

export default connect(mapStateToProps)(withRouter(InputProcessor));
