import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import { getFieldName } from '@/common/Util';

const RoundingRules = ({
    item,
    editable,
    roundingTypeOptions,
    roundingDecimalsOptions,
    roundingStateOptions,
    onChangeFieldValue,
}) => {

    const roundingState = item.getIn(['rounding_rules', 'rounding_stage'], 'None');
    const roundingType = item.getIn(['rounding_rules', 'rounding_type'], '');
    const roundingDecimals = item.getIn(['rounding_rules', 'rounding_decimals'], '');

    const onChangeRoundingState = (value) => {
        if (value === ""){
            onChangeFieldValue(['rounding_rules'], Immutable.Map({'rounding_stage': 'None'}));
        } else {
            onChangeFieldValue(['rounding_rules', 'rounding_stage'], value);
        }
    }

    const onChangeRoundingType = (value) => {
        if(value === ""){
            onChangeFieldValue(['rounding_rules'], Immutable.Map({
                "rounding_stage": roundingState,
            }));
        } else {
            onChangeFieldValue(['rounding_rules', 'rounding_type'], value);
            const defaultRoundingDecimals = (roundingDecimals === "") ? 2 : roundingDecimals;
            onChangeFieldValue(['rounding_rules', 'rounding_decimals'], defaultRoundingDecimals);
        }
    }

    const onChangeRoundingDecimals = (value) => {
        if (value === ""){
            onChangeFieldValue(['rounding_rules'], Immutable.Map({
                "rounding_stage": roundingState,
                "rounding_type": roundingType,
            }));
        } else {
            onChangeFieldValue(['rounding_rules', 'rounding_decimals'], value);
        }
    }

    return (
        <Panel header={<h3>{getFieldName('rounding_rules')}</h3>} collapsible className="collapsible" defaultExpanded={roundingType && roundingType !== 'None'}>
            <FormGroup>
                <Col componentClass={ControlLabel} sm={3} lg={2}>
                    { getFieldName('rounding_state')}
                </Col>
                <Col sm={4}>
                <Field
                    fieldType="select"
                    options={roundingStateOptions}
                    onChange={onChangeRoundingState}
                    value={roundingState}
                    editable={editable}
                />
                </Col>
            </FormGroup>
            { roundingState !== 'None' && (
                <FormGroup>
                    <Col componentClass={ControlLabel} sm={3} lg={2}>
                        { getFieldName('rounding_type')}
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
            )}
            { roundingType !== 'None' && roundingType !== '' && ( 
                <FormGroup>
                    <Col componentClass={ControlLabel} sm={3} lg={2}>
                        { getFieldName('rounding_decimals')}
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
    roundingStateOptions: PropTypes.array,
    roundingTypeOptions: PropTypes.array,
    roundingDecimalsOptions: PropTypes.array,
    onChangeFieldValue: PropTypes.func.isRequired,
};

RoundingRules.defaultProps = {
    editable: true,
    roundingStateOptions: [
        { value: 'None', label: 'None' },
        { value: 'after_tax', label: 'Final charge' },
        { value: 'before_tax', label: 'Charge before taxes' },
    ],
    roundingTypeOptions: [
        { value: 'down', label: 'Down' },
        { value: 'up', label: 'Up' },
        { value: 'nearest', label: 'Nearest' },
    ],
    roundingDecimalsOptions: [...Array(11)].map((_, i) => ({value: i , label: `${i}` })),
};

export default RoundingRules;
