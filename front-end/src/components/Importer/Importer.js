import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { titleCase, upperCaseFirst } from 'change-case';
import { Form } from 'react-bootstrap';
import StepUpload from './StepUpload';
import StepMapper from './StepMapper';
import StepValidate from './StepValidate';
import StepResult from './StepResult';
import { ActionButtons, Stepper } from '@/components/Elements';
import {
  initImporter,
  deleteImporter,
  updateImporterValue,
  deleteImporterValue,
  sendImport,
  addNewImporterMap,
  updateImporterMap,
  deleteImporterMap,
} from '@/actions/importerActions';
import {
  getSettings,
} from '@/actions/settingsActions';
import { showDanger } from '@/actions/alertsActions';
import {
  importFieldsOptionsSelector,
  importMapperSelector,
  importTypesOptionsSelector,
} from '@/selectors/importSelectors';
import { itemSelector } from '@/selectors/entitySelector';
import {
  isPlaysEnabledSelector,
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';
import {
  getConfig,
  getProductConvertedRates,
} from '@/common/Util';


class Importer extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    importFields: PropTypes.array,
    ignoredHeaders: PropTypes.array,
    predefinedValues: PropTypes.instanceOf(Immutable.Map),
    defaultValues: PropTypes.instanceOf(Immutable.Map),
    defaultValuesOperation: PropTypes.instanceOf(Immutable.Map),
    predefinedValuesOperation: PropTypes.instanceOf(Immutable.Map),
    hiddenActionFields: PropTypes.instanceOf(Immutable.Map),
    savedMappers: PropTypes.instanceOf(Immutable.List),
    entityOptions: PropTypes.instanceOf(Immutable.List),
    typeSelectOptions: PropTypes.array,
    showPlay: PropTypes.bool,
    restartString: PropTypes.string,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    onFinish: PropTypes.func,
    onClearItems: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    entityOptions: getConfig(['import', 'allowed_entities'], Immutable.List()),
    item: Immutable.Map(),
    importFields: [],
    predefinedValues: Immutable.Map(),
    // predefinedValues={{ // example
    //   customer: [{
    //     key: 'type',
    //     value: 'account',
    //   }]
    // }}
    defaultValues: Immutable.Map(),
    // defaultValues={{ // example
    //   customer: [{
    //     key: 'company_name',
    //     value: 'Def Com name',
    //   }]
    // }}
    defaultValuesOperation: getConfig(['import', 'default_values_allowed_actions'], Immutable.Map()),
    predefinedValuesOperation: getConfig(['import', 'predefined_values_allowed_actions'], Immutable.Map()),
    hiddenActionFields: getConfig(['import', 'hidden_actions_fields'], Immutable.Map()),
    ignoredHeaders: [
      'import_error_message',
      'import_error_row',
    ], // csv comuns that will not shown as option
    savedMappers: Immutable.List(),
    restartString: '',
    typeSelectOptions: [],
    showPlay: false,
    onFinish: null,
    onClearItems: null,
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
  };

  state = {
    status: 'create',
    stepIndex: 0,
    mapperPrefix: '__csvindex__',
  }

  componentDidMount() {
    const { entityOptions } = this.props;
    this.props.dispatch(initImporter());
    this.initImportEntity(entityOptions);
    this.props.dispatch(getSettings('import.mapping'));
  }

  componentWillReceiveProps(nextProps) {
    const { restartString, item, entityOptions } = nextProps;
    if (restartString !== this.props.restartString) {
      this.props.dispatch(initImporter());
      this.setState({
        status: 'create',
        stepIndex: 0,
      });
    }
    if (!Immutable.is(this.props.entityOptions, entityOptions)) {
      this.initImportEntity(entityOptions);
    }
    // import entity changes to new entity
    const newEntity = item.get('entity', '');
    const oldEntity = this.props.item.get('entity', '');
    if (newEntity !== '' && oldEntity !== newEntity) {
      const collection = getConfig(['systemItems', newEntity, 'collection'], '');
      const importName = `import${upperCaseFirst(collection)}`;
      this.props.dispatch(getSettings('plugin_actions', { actions: [importName] }));
    }
  }

  componentWillUnmount() {
    this.props.dispatch(deleteImporter());
  }

  initImportEntity = (entityOptions) => {
    const isSingleEntity = (entityOptions && entityOptions.size === 1);
    if (isSingleEntity) {
      this.onChange('entity', entityOptions.first());
    }
  }

  getImporterSteps = (index = null) => {
    const { item } = this.props;
    const importType = item.get('importType', '');
    const steps = [];
    if (importType === 'manual_mapping') {
      steps.push({ id: 'upload', stepDate:{ title: 'Upload File'}});
      steps.push({ id: 'mapping', stepDate:{ title: 'Field Map'}});
      steps.push({ id: 'validate', stepDate:{ title: 'Validate'}, okLabel: 'Confirm and Import', okAction: this.onImport });
    } else {
      steps.push({ id: 'upload', stepDate:{ title: 'Upload File'}, okLabel: 'Import', okAction: this.onImport});
    }
    steps.push({ id: 'finish', stepDate:{ title: 'Finish'}, okLabel: 'Close', okAction: this.onFinish});
    if (index === null) {
      return steps;
    }
    return steps[index] || {};
  }

  onChange = (path, value) => {
    const { item } = this.props;
    const operation = item.get('operation', 'create');
    this.props.dispatch(updateImporterValue(path, value));
    if (path === 'operation' && operation !== value) {
      // reset selected mapper if action was changed to filter field by action configuration
      this.onSelectMapping();
    }
  }

  onDelete = (path) => {
    this.props.dispatch(deleteImporterValue(path));
  }

  onFinish = () => {
    if (this.props.onFinish) {
      this.props.onFinish();
    }
  }

  onClearItems = (itemName) => {
    if (this.props.onClearItems) {
      this.props.onClearItems(itemName);
    }
  }

  onImport = () => {
    const { item } = this.props;
    const entity = item.get('entity', '');
    const operation = item.get('operation', 'create');
    const rows = (item.get('importType', '') === 'manual_mapping')
      ? this.alterData(this.getFormattedRows())
      : item;
    if (rows.size > 0 && entity !== '') {
      this.setState({ status: 'progress' });
      const collection = getConfig(['systemItems', entity, 'collection'], '');
      this.props.dispatch(sendImport(collection, rows, operation)).then(this.afterImport);
    } else {
      this.props.dispatch(showDanger('No Import data found'));
    }
  }

  onNextStep = () => {
    const { stepIndex } = this.state;
    this.setState({ stepIndex: stepIndex + 1 });
  }

  onPrevStep = () => {
    const { stepIndex } = this.state;
    this.setState({ stepIndex: stepIndex - 1 });
  }

  combineRateLines = (combinedRate, rateLine, index) => {
    const { importFields, item, propertyTypes, usageTypesData } = this.props;
    const multiValueFields = importFields
      .filter(importField => importField.multiple)
      .map(importField => importField.value);
    const operation = item.get('operation', 'create');
    const specicalField = [
      '__MULTI_FIELD_ACTION__',
      '__UPDATER__',
      '__CSVROW__',
      '__ERRORS__',
      'price_plan',
      'price_from',
      'price_to',
      'price_interval',
      'price_value',
      'usage_type_value',
      'usage_type_unit',
    ];
    const taxFields = ['tax__type', 'tax__taxation','tax__custom_logic','tax__custom_tax'];
    const roundingRulesFields = ['rounding_type', 'rounding_decimals'];
    return combinedRate.withMutations((combinedRateWithMutations) => {
      // Check price field, if we have pricing or percentage in import but missing pricing data -> return error
      if (rateLine.get('rates.percentage', '') !== '' && rateLine.get('price_plan', 'BASE') !== 'BASE') {
        const usageType = rateLine.get('usage_type_value', '_KEEP_SOURCE_USAGE_TYPE_');
        const planName = rateLine.get('price_plan', 'BASE');
        const ratePath = ['rates', usageType, planName, 'percentage'];
        const pricePercentage = parseFloat(rateLine.get('rates.percentage', 1));
        combinedRateWithMutations.setIn(ratePath, pricePercentage);
      } else if (rateLine.get('price_value', '') !== '') {
        if (rateLine.has('price_from')
          && rateLine.has('price_to')
          && rateLine.has('price_interval')
          && rateLine.has('price_value')
          && ((rateLine.has('usage_type_unit') && rateLine.has('usage_type_value') && operation === 'create')
              || operation !== 'create'
          )
        ) {
          const usageType = rateLine.get('usage_type_value', '_KEEP_SOURCE_USAGE_TYPE_');
          const planName = rateLine.get('price_plan', 'BASE');
          const ratePath = ['rates', usageType, planName, 'rate'];
          const priceRate = Immutable.Map({
            from: rateLine.get('price_from', 0),
            to: rateLine.get('price_to', 'UNLIMITED'),
            interval: rateLine.get('price_interval', 0),
            price: rateLine.get('price_value', 0),
            uom_display: Immutable.Map({
              range: rateLine.get('usage_type_unit', '_KEEP_SOURCE_USAGE_TYPE_UNIT_'),
              interval: rateLine.get('usage_type_unit', '_KEEP_SOURCE_USAGE_TYPE_UNIT_'),
            }),
          });
          combinedRateWithMutations.updateIn(ratePath, Immutable.List(), rates => rates.push(priceRate));
        } else {
          const mandatoryPriceFields = ['price_from', 'price_to', 'price_interval', 'price_value'];
          if (item.get('operation', 'create') === 'create') {
            mandatoryPriceFields.push('usage_type_value');
            mandatoryPriceFields.push('usage_type_unit');
          }
          mandatoryPriceFields.forEach((priceField) => {
            if (!rateLine.has(priceField)) {
              combinedRateWithMutations.update('__ERRORS__', Immutable.Map(), erros =>
                erros.update(rateLine.get('__CSVROW__', 'unknown'), Immutable.List(), messages =>
                  messages.push(`missing ${priceField} data`),
                ),
              );
            }
          });
        }
      }
      // Check all other fields field with same value
      rateLine.forEach((value, fieldName) => {
        if (index !== 0 && !specicalField.includes(fieldName) && !taxFields.includes(fieldName) && !multiValueFields.includes(fieldName) && !roundingRulesFields.includes(fieldName) && value !== combinedRateWithMutations.get(fieldName, '')) {
          combinedRateWithMutations.update('__ERRORS__', Immutable.Map(), erros =>
            erros.update(rateLine.get('__CSVROW__', 'unknown'), Immutable.List(), messages =>
              messages.push(`different values for ${fieldName} field`),
            ),
          );
        }
        // build multivalues field value
        if (multiValueFields.includes(fieldName)) {
          const prev = combinedRateWithMutations.get(fieldName, Immutable.List());
          if (!Immutable.List.isList(prev)) {
            const prevs = prev.split(',').map(v => v.trim());
            combinedRateWithMutations.set(fieldName, Immutable.List(prevs));
          }
          let existing = Immutable.Set(combinedRateWithMutations.get(fieldName, Immutable.List()));
          existing = existing.concat(value.split(',').map(v => v.trim()));
          combinedRateWithMutations.set(fieldName, existing.toList());
        }

        // build tax object
        if (taxFields.includes(fieldName) && rateLine.has(fieldName)) {
          const taxFieldNameArray = fieldName.split("__");
          const taxFieldPath = [taxFieldNameArray[0], 0, taxFieldNameArray[1]];
          if (index !== 0 && value !== combinedRateWithMutations.getIn(taxFieldPath, '')) {
            combinedRateWithMutations.update('__ERRORS__', Immutable.Map(), erros =>
              erros.update(rateLine.get('__CSVROW__', 'unknown'), Immutable.List(), messages =>
                messages.push(`different values for ${titleCase(taxFieldNameArray[0])} ${titleCase(taxFieldNameArray[1])} field`),
              ),
            );
          } else {
            combinedRateWithMutations.update(
              taxFieldNameArray[0],
              Immutable.List(),
              taxes => taxes.update(
                0,
                Immutable.Map(),
                tax => tax.set(taxFieldNameArray[1], rateLine.get(fieldName, ''))
              )
            );
          }
        }
        // build rounding rules object
        if (roundingRulesFields.includes(fieldName)) {
          combinedRateWithMutations.update('rounding_rules', Immutable.Map(),rounding_rules => rounding_rules.set(fieldName, rateLine.get(fieldName, '')));
        }
      });
      // convert Price and Interval by unit
      const convertedRates = getProductConvertedRates(propertyTypes, usageTypesData, combinedRateWithMutations, true);
      if (!convertedRates.isEmpty()) {
        combinedRateWithMutations.set('rates', convertedRates);
      }
      // push all rows number that build combined revision
      let rowNumber = combinedRateWithMutations.get('__CSVROW__', Immutable.List());
      if (!Immutable.List.isList(rowNumber)) {
        rowNumber = Immutable.List([rowNumber]);
      }
      if (index !== 0) {
        rowNumber = rowNumber.push(rateLine.get('__CSVROW__', 'unknown'));
      }
      combinedRateWithMutations.set('__CSVROW__', rowNumber);
      // Delete all help fileds that was added by UI.
      combinedRateWithMutations
        .delete('rounding_type')
        .delete('rounding_decimals')
        .delete('tax__custom_logic')
        .delete('tax__custom_tax')
        .delete('tax__taxation')
        .delete('rates.percentage')
        .delete('price_plan')
        .delete('price_from')
        .delete('price_to')
        .delete('price_interval')
        .delete('price_value')
        .delete('usage_type_value')
        .delete('usage_type_unit');
    });
  }

  alterProductsData = data => Immutable.List().withMutations((fieldsWithMutations) => {
    const { item, showPlay } = this.props;
    const operation = item.get('operation', 'create');
    const revisionDateField = (operation === 'create') ? 'from' : 'effective_date';
    const productsGrouped = Immutable.fromJS(data)
      .sortBy(v => v.get(revisionDateField, ''))
      .reverse()
      .groupBy(v => v.getIn(['__UPDATER__', 'value'], v.get('key', 'key')))
      .map(dateGrouped => dateGrouped
        .sortBy(v => v.get('price_from', ''))
        .groupBy(v => v.get(revisionDateField, ''))
        .map(dateUpdaterGroup =>
          dateUpdaterGroup.reduce(this.combineRateLines, dateUpdaterGroup.first()),
        ),
      );
    let hasError = false;
    productsGrouped.forEach((productKey) => {
      productKey.forEach((productKeyRevision) => {
        productKeyRevision.withMutations((productKeyRevisionWithMutations) => {
          if (showPlay) {
            productKeyRevisionWithMutations.set('play', item.get('play', ''));
          }
          // Date field (from/effective_date) is reqired
          if (!productKeyRevision.has(revisionDateField)) {
            productKeyRevisionWithMutations.update('__ERRORS__', Immutable.Map(), erros =>
              erros.withMutations((errosWithMutations) => {
                productKeyRevision.get('__CSVROW__', Immutable.List()).forEach((rowIdx) => {
                  errosWithMutations.update(rowIdx, Immutable.List(), messages =>
                    messages.push(`missing ${revisionDateField} field`),
                  );
                });
              }),
            );
          }
          if (operation === 'permanentchange' && !productKeyRevision.has('__UPDATER__')) {
            productKeyRevisionWithMutations.update('__ERRORS__', Immutable.Map(), erros =>
              erros.withMutations((errosWithMutations) => {
                productKeyRevision.get('__CSVROW__', Immutable.List()).forEach((rowIdx) => {
                  errosWithMutations.update(rowIdx, Immutable.List(), messages =>
                    messages.push('missing updater field'),
                  );
                });
              }),
            );
          }
          // check for errors
          if (productKeyRevisionWithMutations.has('__ERRORS__')) {
            hasError = true;
          }
          fieldsWithMutations.push(productKeyRevisionWithMutations);
        });
      });
    });
    this.props.dispatch(updateImporterValue('error', hasError));
  });

  alterData = (data) => {
    const { item } = this.props;
    const entity = item.get('entity', '');
    switch (entity) {
      case 'product':
        return this.alterProductsData(data);
      default:
        return data;
    }
  }

  removeMapper = () => {
    const { item } = this.props;
    const mapperName = item.get('mapperName', '');
    this.props.dispatch(deleteImporterMap(mapperName));
    this.props.dispatch(updateImporterValue('mapperName', ''));
  }

  onSaveMapping = (label) => {
    const { item, savedMappers } = this.props;
    const selectedMapperName = item.get('mapperName', '');
    const index = savedMappers.findIndex(savedMapper => savedMapper.get('label', '') === label);
    const map = item.get('map', Immutable.List());
    const linker = item.get('linker', Immutable.Map());
    const updater = item.get('updater', Immutable.Map());
    const multiFieldAction = item.get('multiFieldAction', Immutable.Map());
    const newMap = Immutable.Map({ label, map, updater, linker, multiFieldAction });
    if (selectedMapperName === '' || index === -1) { // save new or update in case label not found
      if(index !== -1) {
        return this.props.dispatch(showDanger(`Mapper with name "${label}" already exists`));
      }
      this.props.dispatch(addNewImporterMap(newMap));
    } else { // update existing
      this.props.dispatch(updateImporterMap(newMap, index));
    }
    this.props.dispatch(updateImporterValue('mapperName', label));
  }

  onSelectMapping = (name = '') => {
    const { savedMappers } = this.props;
    const selectedMapper = savedMappers.find(mapper => mapper.get('label', '') === name, null, Immutable.Map());
    // fix PHP empty object to array
    const map = selectedMapper.get('map', Immutable.Map()) === Immutable.List() ? Immutable.Map() : selectedMapper.get('map', Immutable.Map());
    this.props.dispatch(updateImporterValue('map', map.filter((value, fieldName) => this.customFilterMapperFields({ value: fieldName }))));
    const linker = selectedMapper.get('linker', Immutable.Map()) === Immutable.List() ? Immutable.Map() : selectedMapper.get('linker', Immutable.Map());
    this.props.dispatch(updateImporterValue('linker', linker));
    const updater = selectedMapper.get('updater', Immutable.Map()) === Immutable.List() ? Immutable.Map() : selectedMapper.get('updater', Immutable.Map())
    this.props.dispatch(updateImporterValue('updater', updater));
    const multiFieldAction = selectedMapper.get('multiFieldAction', Immutable.Map()) === Immutable.List() ? Immutable.Map() : selectedMapper.get('multiFieldAction', Immutable.Map())
    this.props.dispatch(updateImporterValue('multiFieldAction', multiFieldAction));
    this.props.dispatch(updateImporterValue('mapperName', name));
  }

  getOkButtonLable = () => {
    const { stepIndex } = this.state;
    const importerStep = this.getImporterSteps(stepIndex);
    return importerStep.okLabel || 'Next';
  }

  getOkButtonAction = () => {
    const { stepIndex } = this.state;
    const importerStep = this.getImporterSteps(stepIndex);
    return importerStep.okAction || this.onNextStep;
  }

  getFormattedRows = (limit = -1) => {
    const {
      item,
      predefinedValues,
      defaultValues,
      importFields,
      predefinedValuesOperation,
      defaultValuesOperation,
    } = this.props;
    const { mapperPrefix } = this.state;
    const lines = item.get('fileContent', []);
    const linker = item.get('linker', null);
    const updater = item.get('updater', null);
    const entity = item.get('entity', []);
    const operation = item.get('operation', 'create');
    const map = item.get('map', Immutable.List());
    const multiFieldAction = item.get('multiFieldAction', null);

    return Immutable.List().withMutations((rowsWithMutations) => {
      if (Array.isArray(lines)) {
        const linesToParse = (limit === -1) ? lines.length : Math.min(limit + 1, lines.length);
        // Ignore first (headers) line
        for (let idx = 1; idx < linesToParse; idx++) {
          const row = Immutable.Map().withMutations((mapWithMutations) => {
            map.forEach((mapperValue, fieldName) => {
              let columnValue = mapperValue;
              if (mapperValue.startsWith(mapperPrefix)) {
                const csvIndex = mapperValue.substring(mapperPrefix.length);
                columnValue = lines[idx][csvIndex];
              }
              const curField = importFields.find(field => field.value === fieldName);
              const fieldType = curField ? curField.type : 'string';
              if (fieldType === 'ranges') {
                columnValue = Immutable.List().withMutations((rangewithMutations) => {
                  const ranges = columnValue.split(',');
                  ranges.forEach((range) => {
                    const rangeValue = range.split('-');
                    rangewithMutations.push(Immutable.Map({
                      from: rangeValue[0],
                      to: rangeValue[1],
                    }));
                  });
                });
              }
              mapWithMutations.set(fieldName, columnValue);
            });
            // Set predefined values
            if (['create'].includes(operation) && predefinedValuesOperation.get(operation, Immutable.List()).includes(entity) && predefinedValues.has(entity)) {
              predefinedValues.get(entity, Immutable.Map()).forEach((predefinedValue, fieldkey) => {
                mapWithMutations.set(fieldkey, predefinedValue);
              });
            }
            // Set predefined values
            if (['create'].includes(operation) && defaultValuesOperation.get(operation, Immutable.List()).includes(entity) && defaultValues.has(entity)) {
              defaultValues.get(entity, Immutable.Map()).forEach((defaultValue, fieldkey) => {
                if (mapWithMutations.get(fieldkey, '') === '') {
                  mapWithMutations.set(fieldkey, defaultValue);
                }
              });
            }
            // Set linker for entities with parent<->child relationship
            if (linker !== null && linker.get('field', '') !== '' && linker.get('value', '') !== '') {
              const csvIndex = linker.get('value', '').substring(mapperPrefix.length);
              mapWithMutations.set('__LINKER__', Immutable.Map({
                field: linker.get('field', ''),
                value: lines[idx][csvIndex],
              }));
            }
            // Set updater for import Update action
            if (updater !== null && updater.get('field', '') !== '' && updater.get('value', '') !== '') {
              const csvIndex = updater.get('value', '').substring(mapperPrefix.length);
              mapWithMutations.set('__UPDATER__', Immutable.Map({
                field: updater.get('field', ''),
                value: lines[idx][csvIndex],
              }));
            }
            // Set updater action for multifields
            if (multiFieldAction !== null && !multiFieldAction.isEmpty()) {
              mapWithMutations.set('__MULTI_FIELD_ACTION__', multiFieldAction);
            }
            // Set CSV row
            mapWithMutations.set('__CSVROW__', (idx + 1));
          });
          rowsWithMutations.push(row);
        }
      }
    });
  }

  afterImport = (response) => {
    const { item } = this.props;
    if ([1, 2].includes(response.status)) {
      const responseData = Immutable.fromJS(response.data) || Immutable.Map();
      const result = responseData.get('imported_entities', responseData.toList());
      if (responseData.has('created', false)) {
        this.setState({ status: 'finish' });
      } else {
        const allSuccess = result.every(status => status === true);
        const allFails = result.every(status => status !== true);
        if (allSuccess || (result.size > 0 && !allFails)) {
          this.setState({ status: 'finish' });
        } else {
          this.setState({ status: 'create' });
        }
      }
      this.onChange('result', responseData);
      this.onNextStep();
      this.onClearItems(item.get('entity', ''));
    } else {
      this.onDelete('result');
      this.setState({ status: 'create' });
    }
  }

  customFilterMapperFields = (field) => {
    const { item, hiddenActionFields } = this.props;
    const entity = item.get('entity', '');
    const fieldPlays = field.plays || Immutable.List();
    const itemPlay = item.get('play', '');
    if (!fieldPlays.isEmpty() && itemPlay !== '') {
      if (!fieldPlays.includes(itemPlay)) {
        return false;
      }
    }
    const operation = item.get('operation', 'create');
    if (hiddenActionFields.getIn([operation, entity], Immutable.List()).includes(field.value)) {
      return false;
    }
    return true;
  }

  renderStepper = () => {
    const { stepIndex } = this.state;
    const importerSteps = this.getImporterSteps();
    return (
      <Stepper activeIndex={stepIndex} steps={importerSteps.map(step => step.stepDate)} />
    );
  }

  renderStepContent = () => {
    const {
      item,
      importFields,
      entityOptions,
      ignoredHeaders,
      defaultValues,
      savedMappers,
      showPlay,
      typeSelectOptions,
    } = this.props;
    const { stepIndex, mapperPrefix } = this.state;
    const entity = item.get('entity', '');
    const mapperName = item.get('mapperName', '');
    const id = this.getImporterSteps(stepIndex).id;

    switch (id) {
      case 'upload': return (
        <StepUpload
          item={item}
          entityOptions={entityOptions.toJS()}
          onChange={this.onChange}
          onDelete={this.onDelete}
          onSelectMapping={this.onSelectMapping}
          mapperName={mapperName}
          mapperOptions={savedMappers}
          typeSelectOptions={typeSelectOptions}
          showPlay={showPlay}
        />
      );
      case 'mapping': return (
        <StepMapper
          item={item}
          customFilterFields={this.customFilterMapperFields}
          onChange={this.onChange}
          onDelete={this.onDelete}
          fields={importFields}
          defaultFieldsValues={defaultValues.get(entity, Immutable.Map())}
          ignoredHeaders={ignoredHeaders}
          mapperPrefix={mapperPrefix}
        />
      );
      case 'validate': return (
        <StepValidate
          entity={entity}
          fields={importFields}
          rows={this.alterData(this.getFormattedRows())}
          selectedMapper={mapperName === '' ? null : mapperName }
          defaultMappedName={item.get('fileName', '') === '' ? undefined : item.get('fileName')}
          saveMapper={this.onSaveMapping}
          removeMapper={this.removeMapper}
        />
      );
      case 'finish': return (
        <StepResult item={item} />
      );
      default: return (
        <p>Not valid Step</p>
      );
    }
  }

  renderActionButtons = () => {
    const { stepIndex, status } = this.state;
    const { item } = this.props;
    const inProgress = status === 'progress';
    const inFinish = status === 'finish';
    const validateHasError = stepIndex === 2 && item.get('error', false);

    return (
      <ActionButtons
        cancelLabel={this.getOkButtonLable()}
        onClickCancel={this.getOkButtonAction()}
        disableCancel={inProgress || validateHasError || item.get('importType', '') === ''}
        saveLabel="Back"
        hideCancel={stepIndex === 3 && this.props.onFinish === null}
        disableSave={stepIndex === 0 || inProgress || inFinish}
        onClickSave={this.onPrevStep}
        reversed={true}
      />
    );
  }

  render() {
    return (
      <div className="Importer">
        {this.renderStepper()}
        <hr style={{ margin: '20px -15px 0 -15px' }} />
        <Form horizontal className="mb0">
          {this.renderStepContent()}
        </Form>
        <div className="clearfix" />
        <hr className="mt0" />
        {this.renderActionButtons()}
      </div>
    );
  }
}


const mapStateToProps = (state, props) => {
  const item = itemSelector(state, props, 'importer');
  const entity = item ? item.get('entity', '') : '';
  const allowedEntitiesForPlays = getConfig(['import', 'allowed_plays'], Immutable.List());
  const isPlaysEnabled = isPlaysEnabledSelector(state, props);
  return ({
    item,
    importFields: importFieldsOptionsSelector(state, props, 'importer'),
    savedMappers: importMapperSelector(state, props, 'importer'),
    typeSelectOptions: importTypesOptionsSelector(state, props, 'importer'),
    showPlay: isPlaysEnabled && allowedEntitiesForPlays.includes(entity),
    usageTypesData: usageTypesDataSelector(state, props),
    propertyTypes: propertyTypeSelector(state, props),
  });
};

export default connect(mapStateToProps)(Importer);
