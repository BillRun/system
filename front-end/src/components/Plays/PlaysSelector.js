import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col } from 'react-bootstrap';
import Field from '@/components/Field';
import { getSettings } from '@/actions/settingsActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import {
  availablePlaysSettingsSelector,
} from '@/selectors/settingsSelector';
import {
  shouldUsePlays,
  getPlayOptions,
} from '@/common/Util';


class PlaysSelector extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    availablePlays: PropTypes.instanceOf(Immutable.List),
    entity: PropTypes.instanceOf(Immutable.Map).isRequired,
    editable: PropTypes.bool,
    labelStyle: PropTypes.object,
    fieldStyle: PropTypes.object,
    multi: PropTypes.bool,
    mandatory: PropTypes.bool,
    onChange: PropTypes.func,
  }

  static defaultProps = {
    availablePlays: Immutable.List(),
    editable: true,
    labelStyle: { sm: 3, lg: 2 },
    fieldStyle: { sm: 8, lg: 9 },
    multi: false,
    mandatory: false,
    onChange: () => {},
  }

  componentWillMount() {
    this.props.dispatch(getSettings(['plays']));
  }

  componentWillReceiveProps(nextProps) {
    const { availablePlays, mandatory, editable, onChange, entity } = this.props;
    // Set default play in edit mode if it required and not set.
    const playsLoadedComplete = !nextProps.availablePlays.isEmpty()
      && Immutable.is(availablePlays, nextProps.availablePlays);
    const playNotSet = entity.get('play', null) === null;
    if (playNotSet && mandatory && editable && playsLoadedComplete) {
      const defaultPlay = nextProps.availablePlays.find(
        availablePlay => availablePlay.get('enabled', true) && availablePlay.get('default', false),
        null, nextProps.availablePlays.first(),
      ).get('name', '');
      onChange(defaultPlay);
    }
  }

  playsValueToList = (plays) => {
    if (plays === '') {
      return Immutable.List();
    }
    if (Immutable.List.isList(plays)) {
      return plays;
    }
    if (Array.isArray(plays)) {
      return Immutable.List([...plays]);
    }
    return Immutable.List(plays.split(','));
  }

  onChange = (plays) => {
    const { entity } = this.props;
    const oldPlays = this.playsValueToList(entity.get('play', ''));
    const newPlays = this.playsValueToList(plays);
    // its ok to add play, only on remove play we need to remove all related data
    const isNewPlayAdded = oldPlays.every(oldPlay => newPlays.includes(oldPlay));
    if (isNewPlayAdded) {
      return this.props.onChange(newPlays.join(','));
    }
    const onCancel = () => {
      this.forceUpdate();
    };
    const onOk = () => {
      this.props.onChange(plays, true);
    };
    const confirm = {
      children: 'By removing play value all play related data will be removed',
      message: 'Are you sure you want to change play?',
      onOk,
      onCancel,
      type: 'delete',
      labelOk: 'Yes',
    };
    return this.props.dispatch(showConfirmModal(confirm));
  }

  render() {
    const { availablePlays, entity, editable, multi, mandatory, labelStyle, fieldStyle } = this.props;
    if (!shouldUsePlays(availablePlays)) {
      return null;
    }
    const label = multi ? 'Play/s' : 'Play';
    const playValue = this.playsValueToList(entity.get('play', '')).join(',');
    return (
      <FormGroup key="play">
        <Col componentClass={ControlLabel} sm={labelStyle.sm} lg={labelStyle.lg}>
          {label}
          { mandatory && (<span className="danger-red"> *</span>)}
        </Col>
        <Col sm={fieldStyle.sm} lg={fieldStyle.lg}>
          <Field
            fieldType="select"
            options={getPlayOptions(availablePlays)}
            value={playValue}
            onChange={this.onChange}
            multi={multi}
            placeholder=""
            clearable={!mandatory}
            editable={editable}
          />
        </Col>
      </FormGroup>
    );
  }

}

const mapStateToProps = (state, props) => ({
  availablePlays: availablePlaysSettingsSelector(state, props),
});

export default connect(mapStateToProps)(PlaysSelector);
