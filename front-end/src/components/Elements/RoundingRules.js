import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import {
    getFieldName,
  } from '@/common/Util';

const RoundingRules = ({
    item,
    editable,
    roundingTypeOptions,
    roundingDecimalsOptions,
    onChangeFieldValue,
}) => {

    const roundingType = item.getIn(['rounding_rules', 'rounding_type'], 'None');
    const roundingDecimals = item.getIn(['rounding_rules', 'rounding_decimals'], '');

    const onChangeRoundingType = (value) => {
        if(value === ""){
            onChangeFieldValue(['rounding_rules'], Immutable.Map({"rounding_type": 'None'}));
        } else {
            onChangeFieldValue(['rounding_rules', 'rounding_type'], value);
            const defaultRoundingDecimals = (roundingDecimals === "") ? 2 : roundingDecimals;
            onChangeFieldValue(['rounding_rules', 'rounding_decimals'], defaultRoundingDecimals);
        }
    }

    const onChangeRoundingDecimals = (value) => {
        if (value === ""){
            onChangeFieldValue(['rounding_rules'], Immutable.Map({"rounding_type": roundingType}));
        } else {
            onChangeFieldValue(['rounding_rules', 'rounding_decimals'], value);
        }
    }
    return (
        <Panel header={<h3>Rounding Rules</h3>} collapsible className="collapsible" defaultExpanded={roundingType && roundingType !== 'None'}>
            <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('rounding_type', '', 'Final charge rounding type')}
                </Col>
                <Col sm={4}>
                <Field
                    fieldType="select"
                    options={roundingTypeOptions}
                    onChange={onChangeRoundingType}
                    value={roundingType}
                    editable={editable}
                />
                </Col>
            </FormGroup>
            {roundingType && roundingType !== 'None' && ( 
            <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('rounding_decimals', '', 'Final charge rounding Decimals')}
                </Col>
                <Col sm={4}>
                <Field
                    fieldType="select"
                    options={roundingDecimalsOptions}
                    onChange={onChangeRoundingDecimals}
                    value={roundingDecimals}
                    editable={editable}
                />
                </Col>
            </FormGroup>
            )}
        </Panel>
    )
};

RoundingRules.propTypes = {
    item: PropTypes.instanceOf(Immutable.Map).isRequired,
    editable: PropTypes.bool,
    roundingTypeOptions: PropTypes.array,
    roundingDecimalsOptions: PropTypes.array,
    onChangeFieldValue: PropTypes.func.isRequired,
};

RoundingRules.defaultProps = {
    editable: true,
    roundingTypeOptions: [
        { value: 'None', label: 'None' },
        { value: 'down', label: 'Down' },
        { value: 'up', label: 'Up' },
        { value: 'nearest', label: 'Nearest' },
    ],
    roundingDecimalsOptions: [...Array(11)].map((_, i) => ({value: i , label: `${i}` })),
};

export default RoundingRules;
