import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Col, Row, Panel } from 'react-bootstrap';
import moment from 'moment';
import { Actions } from '@/components/Elements';
import List from '@/components/List';
import { pageFlagSelector } from '@/selectors/guiSelectors';
import { setPageFlag } from "@/actions/guiStateActions/pageActions";
import {
    scheduleChargeParser,
    cancelledChargeParser,
    statusIconChargeParser,
} from '@/common/Parsers';
import {
    getFieldName,
    getChargeStatus,
} from '@/common/Util';


const ChargingList = ({
    items,
    scheduleItems,
    size,
    listType,
    listSizeOptions,
    listTypeOptions,
    onChangeSize,
    onChangeType,
    listFields,
    listActions,
    rowActions,
}) => {
    
    const panelHeader = (
        <div>
            <small>Show jobs of type:
                <select value={listType} className="form-control charging-list-type inline mr5 ml5 pr5 pl5" onChange={onChangeType}>
                    {listTypeOptions.map(option => <option key={option.key} value={option.key}>{option.title}</option>)}
                </select>
            </small>
            { listType === 'all' && (
                <small> last
                <select value={size} className="form-control charging-list-size inline mr5 ml5 pr5 pl5" onChange={onChangeSize}>
                    {listSizeOptions.map(val => <option key={val} value={val}>{val}</option>)}
                </select>
            </small>
            )}
            <div className="pull-right">
                <Actions actions={listActions} />
            </div>
        </div>
    );

    return (
        <Row id='charging-wrapper'>
            <Col lg={12} >
                <Panel header={panelHeader}>
                    <List
                        items={listType === 'all' ? items : scheduleItems}
                        fields={listFields}
                        actions={rowActions}
                    />
                </Panel>
            </Col>
        </Row>
    );
}


ChargingList.defaultProps = {
    items: Immutable.List(),
    allowCreate: false,
    size: 20,
    listType: 'all',
};

ChargingList.propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    listType: PropTypes.string,
    allowCreate: PropTypes.bool,
    size: PropTypes.number,
    onFetch: PropTypes.func,
    onUpdateSize: PropTypes.func,
    onShowDetails: PropTypes.func,
    onCreate: PropTypes.func,
    onCancel: PropTypes.func,
};

const mapStateToProps = (state, props) => ({
    listType: pageFlagSelector(state, props, 'charging', 'listType'),
    listSizeOptions: [20, 50, 100, 500, 1000],
    listTypeOptions: [{key: 'all', title: 'All'}, {key: 'schedule', title: 'Scheduled'}],
})

const mapDispatchToProps = (dispatch, props) => ({
    onChangeSize: (e) => props.onUpdateSize(parseInt(e.target.value)),
    onChangeType: (e )=> dispatch(setPageFlag('charging', 'listType', e.target.value)),
    isItemCancelable: (item) => getChargeStatus(item) === 'future',
});

const mergeProps = (stateProps, dispatchProps, ownProps) => {

    const listFields = [
        { id: 'state', parser: statusIconChargeParser, cssClass: 'state'},
        { id: 'created', title: getFieldName('created', 'charging_process'), type: 'datetime', cssClass: 'text-center'},
        { id: 'schedule', title: getFieldName('schedule', 'charging_process'), type: 'datetime', parser: scheduleChargeParser, cssClass: 'text-center'},
        { id: 'start_time', title: getFieldName('start_time', 'charging_process'), type: 'datetime', cssClass: 'text-center'},
        { id: 'cancelled', title: getFieldName('cancelled', 'charging_process'), parser: cancelledChargeParser, cssClass: 'text-center'},
    ];

    const listActions = [{
        type: 'add',
        actionStyle: 'primary',
        actionSize: 'xsmall',
        label: 'Start New Charging',
        show: ownProps.allowCreate,
        onClick: ownProps.onCreate,
    },{
        type: 'refresh',
        actionStyle: 'primary',
        actionSize: 'xsmall',
        label: 'Refresh',
        onClick: ownProps.onFetch,
    }];

    const rowActions = [{
        type: 'view',
        showIcon: true,
        helpText: 'View',
        onClick: ownProps.onShowDetails,
    }, {
        type: 'remove',
        showIcon: true,
        helpText: 'Cancel',
        enable: dispatchProps.isItemCancelable,
        onClick: ownProps.onCancel,
    }];

    return ({
        ...stateProps,
        ...dispatchProps,
        ...ownProps,
        listActions,
        listFields,
        rowActions,
    });
};

export default connect(mapStateToProps, mapDispatchToProps, mergeProps)(ChargingList);