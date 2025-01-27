import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { MenuItem, DropdownButton, InputGroup, Panel } from 'react-bootstrap';
import classNames from 'classnames';
import { titleCase } from 'change-case';
import { EntityField } from './index';
import { getSettings } from '@/actions/settingsActions';
import {
  entityFieldSelector,
  isPlaysEnabledSelector,
} from '@/selectors/settingsSelector';


class EntityFields extends Component {

  static propTypes = {
    entity: PropTypes.instanceOf(Immutable.Map),
    entityName: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.arrayOf(PropTypes.string),
    ]).isRequired,
    fields: PropTypes.instanceOf(Immutable.List),
    errors: PropTypes.instanceOf(Immutable.Map),
    highlightParams: PropTypes.instanceOf(Immutable.List),
    fieldsFilter: PropTypes.func,
    editable: PropTypes.bool,
    isPlaysEnabled: PropTypes.bool,
    onChangeField: PropTypes.func,
    onRemoveField: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    entity: Immutable.Map(),
    fields: Immutable.List(),
    errors: Immutable.Map(),
    highlightParams: null,
    fieldsFilter: null,
    editable: true,
    isPlaysEnabled: false,
    onChangeField: () => {},
    onRemoveField: () => { console.error('Please implement onRemoveField function for EntityFields'); },
  }

  componentDidMount() {
    const { entityName, fields, entity } = this.props;
    if (fields.isEmpty()) {
      this.props.dispatch(getSettings(entityName));
    }
    // fix problem when empty params object converted to array
    //if (entity.has('params') && Immutable.is(entity.get('params', Immutable.List()), Immutable.List())) {
      // this.props.onChangeField(['params'], Immutable.Map());
    //}
    // fix problem when empty params object converted to array
    let updated_levels = [];
    fields.forEach(field => {
      const levels = field.get('field_name', '').split('.');
      levels.pop(); // remove last element from the path
      if (levels.length) {
        let levelsArray = [];
        levels.forEach((level) => {
          levelsArray.push(level);
          const laterString = levelsArray.join('.');
          const isAlreadyUpdated = !updated_levels.includes(laterString);
          const isPresentInEntity = entity.hasIn(levelsArray);
          const isWrongType = Immutable.is(entity.getIn(levelsArray, Immutable.List()), Immutable.List());
          if (isAlreadyUpdated && isPresentInEntity && isWrongType) {
            updated_levels.push(laterString);
            this.props.onChangeField(levelsArray, Immutable.Map());
    }
        });
      }
    })
  }

  componentDidUpdate(prevProps, prevState) { // eslint-disable-line no-unused-vars
    const { fields, entity } = this.props;
    const { entity: oldEntity } = prevProps;

    const isMultiple = fields.find(field => field.get('field_name', '') === 'play',
      null, Immutable.Map(),
    ).get('multiple', false);
    const shouldResetFields = isMultiple ?
      !Immutable.is(entity.get('play', Immutable.List()), oldEntity.get('play', Immutable.List()))
      : entity.get('play', '') !== oldEntity.get('play', '');
    if (shouldResetFields) {
      fields.forEach((field) => {
        const shouldPlaysBeDisplayed = this.filterPlayFields(field);
        if (!shouldPlaysBeDisplayed) {
          this.props.onRemoveField(field.get('field_name', '').split('.'));
        }
      });
    }
  }

  getParamsOptions = () => {
    const { fields, fieldsFilter, highlightParams } = this.props;
    const fieldFilterFunction = fieldsFilter !== null ? fieldsFilter : this.filterPrintableFields;
    return fields
      .filter(fieldFilterFunction)
      .filter(field => !this.filterParamsFields(field))
      .map(field => ({
        label: titleCase(field.get('title', '')),
        value: field.get('field_name', ''),
      }))
      .sortBy(field => field.label)
      .sortBy(field =>
        (highlightParams !== null && highlightParams.includes(`params.${field.value}`) ? 0 : 1)
      )
  }

  onAddParam = (key) => {
    const path = Array.isArray(key) ? key : key.split('.');
    this.props.onChangeField(path, undefined);
  }

  filterPrintableFields = field => (
    field.get('display', false) !== false
    // && field.get('editable', false) !== false
    && field.get('field_name', '') !== 'tariff_category'
    && field.get('field_name', '') !== 'play'
  );

  filterParamsFields = (field) => {
    const { entity } = this.props;
    const fieldPath = field.get('field_name', '').split('.');
    const isParam = fieldPath[0] === 'params' || field.get('nullable', false);
    return (!(isParam && !entity.hasIn(fieldPath))) || field.get('mandatory', false);
  }

  filterPlayFields = (field) => {
    const { entity, isPlaysEnabled } = this.props;
    if (!isPlaysEnabled) {
      return true;
    }
    const play = entity.get('play', '');
    const plays = Immutable.List(typeof play.split === 'function' ? play.split(',') : play);
    const fieldPlays = field.get('plays', 'all');
    const isFieldOfPlay = fieldPlays === 'all' || plays.some(p => fieldPlays.indexOf(p) > -1);
    return isFieldOfPlay;
  }

  renderField = (fields, category) => {
    const { entity, editable, onChangeField, onRemoveField, errors } = this.props;

    const rows = fields.map((field, key) => (
      <EntityField
        key={`key_${field.get('field_name', key)}`}
        field={field}
        entity={entity}
        editable={editable && field.get('editable', false)}
        onChange={onChangeField}
        onRemove={onRemoveField}
        error={errors.get(field.get('field_name', ''), false)}
      />
    ))

    return category === 'uncategorized' ? rows : (
      <Panel header={category} key={`panel-${category}`} collapsible className="collapsible">
        {rows}
      </Panel>
    );
  };

  renderFields = () => {
    const { fields, fieldsFilter } = this.props;
    const fieldFilterFunction = fieldsFilter !== null ? fieldsFilter : this.filterPrintableFields;
    const updatedFields = fields
      .filter(this.filterPlayFields)
      .filter(fieldFilterFunction)
      .filter(this.filterParamsFields)

    // Group fields by category
    const fieldsByCategory = updatedFields.reduce((acc, field) => {
      const category = field.get('category', '') || 'uncategorized';
      return acc.update(category, Immutable.List(), cat => cat.push(field));
    }, Immutable.Map());

    return fieldsByCategory.map(this.renderField).toList();
  }

  renderAddParamButton = (options) => {
    const { highlightParams } = this.props;
    const highlightAll = highlightParams === null;
    const menuItems = options.map((option) => {
      const highlight = highlightAll || highlightParams.includes(`params.${option.value}`);
      const menuItemClass = classNames({
        'disable-label': !highlight,
      });
      const onSelect = () => { this.onAddParam(option.value); };
      return (
        <MenuItem key={option.value} eventKey={option.value} onSelect={onSelect}>
          <span className={menuItemClass}>{option.label}</span>
        </MenuItem>
      );
    });
    return (
      <DropdownButton id="add-param-input" componentClass={InputGroup.Button} className="btn-primary btn btn-xs btn-default" title="Add parameter" >
        { menuItems }
      </DropdownButton>
    );
  }

  render() {
    const { editable } = this.props;
    const entityFields = this.renderFields();
    const paramsOptions = this.getParamsOptions();
    if (!entityFields.isEmpty() || !paramsOptions.isEmpty()) {
      return (
        <div className="EntityFields">
          { entityFields }
          { (!paramsOptions.isEmpty() && editable) && this.renderAddParamButton(paramsOptions) }
        </div>
      );
    }
    return null;
  }
}

const mapStateToProps = (state, props) => ({
  fields: props.fields || entityFieldSelector(state, props),
  isPlaysEnabled: isPlaysEnabledSelector(state, props),
});

export default connect(mapStateToProps)(EntityFields);
