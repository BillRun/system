import React, { useEffect } from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel, Col, Form, FormGroup, ControlLabel } from 'react-bootstrap';
import { Actions } from '@/components/Elements';
import ChargingList from './ChargingList';
import ChargingDetails from './ChargingDetails';
import ChargingForm from './ChargingForm';
import Field from '@/components/Field';
import { showFormModal, showConfirmModal, setPageFlag } from "@/actions/guiStateActions/pageActions";
import { pageFlagSelector } from '@/selectors/guiSelectors';
import {
    getCharges,
    clearCharges,
    getChargesSchedule,
    getChargeDetails,
    setActiveCharge,
    clearCharge,
    cancelCharge,
    startCharge,
} from '@/actions/chargingActions';
import {
    chargeSelector,
    chargingListSelector,
    chargingScheduleListSelector,
} from '@/selectors/chargingSelectors';
import { isWorkersSelector } from '@/selectors/appSelectors';
import {
    getConfig,
    getFieldName,
    getChargeStatus,
} from '@/common/Util';


const Charging = ({ 
    items,
    scheduleItems,
    listSize,
    onChangeListSize,
    activeItem,
    isWorkers,
    loadCharges,
    removeCharges,
    loadActiveCharge,
    onClearCharge,
    onViewCharge,
    onReloadItems,
    onCancelCharge,
    total,
    done,
    progress,
    dateTimeFormat,
    activePanelActions,
    onCreateChargeInPopup,
}) => {

    useEffect(() => {
        // component will mount in functional component.
        loadCharges(listSize);
        // component will unmount in functional component.
        return () => {
            removeCharges();
            onClearCharge();
        }
    }, []);

    useEffect(() => {
        loadCharges(listSize);
    }, [listSize, loadCharges]);
    
    useEffect(() => {
        const suspicionActiveItem = items
            .filter(item => item.get('start_time', '') !== '')
            .filter(item => !['cancelled', 'future'].includes(getChargeStatus(item)))
            .reduce((maxItem, currentItem) => {
                if (maxItem.isEmpty()) {
                    return currentItem;
                }
                const currStartTime = moment(currentItem.get('start_time', ''));
                const validCurrStartTime = moment.isMoment(currStartTime) && currStartTime.isValid();
                if (!validCurrStartTime) {
                    return maxItem;
                }
                const maxStartTime = moment(maxItem.get('start_time', ''));
                const validMaxStartTime = moment.isMoment(maxStartTime) && maxStartTime.isValid();
                if (!validMaxStartTime) {
                    return currentItem;
                }
                return currStartTime.isAfter(validMaxStartTime) ? currentItem : maxItem;
            }, Immutable.Map())
        if (!suspicionActiveItem.isEmpty() && !suspicionActiveItem.get('active', false)) {
            loadActiveCharge(suspicionActiveItem);
        }
    }, [items, loadActiveCharge]);

    if (!isWorkers) {
        return <p>This feature is available for <strong>BillRun Premium</strong> customers</p>
    }

    const idleItems = items.filter(item => getChargeStatus(item) === 'idle');
    const allowCreate = activeItem.get('md5', '') === '' && idleItems.isEmpty();
    const futureItems = scheduleItems.map((scheduleCharge) => moment(scheduleCharge.get('schedule', null)))
        .filter(value => moment.isMoment(value) && value.isValid() && value.isAfter(moment()))
        .sort((dateA, dateB) => dateA.isBefore(dateB) ? -1 : 1)
        .map((date) => date.format(dateTimeFormat));

    const activeChargeHeader = (<>
        Active Job Progress <strong>{progress.toFixed(2)}%</strong>
        <div className="pull-right">
            <Actions actions={activePanelActions} data={activeItem}/>
        </div>
    </>);

    const scheduleChargesHeader = (<>
        Schedulers: <strong>{futureItems.size} jobs</strong>
        <div className="pull-right">
        </div>
    </>);

    return (
        <div>
            {!activeItem.isEmpty() && (
                <Panel header={activeChargeHeader}>
                    Done <i>{done}</i> of <i>{total}</i>
                </Panel>
            )}
            {!futureItems.isEmpty() && (
                <Panel header={scheduleChargesHeader}>
                    { futureItems.join(", ") }
                </Panel>
            )}
            <ChargingList
                items={items}
                scheduleItems={scheduleItems}
                size={listSize}
                allowCreate={allowCreate}
                onFetch={onReloadItems}
                onUpdateSize={onChangeListSize}
                onShowDetails={onViewCharge}
                onCancel={onCancelCharge}
                onCreate={onCreateChargeInPopup}
            />
        </div>
    );
}

Charging.defaultProps = {
    items: Immutable.List(),
    activeItem: Immutable.Map(),
    scheduleItems: Immutable.List(),
    isWorkers: false,
    listSize: 20,
};

Charging.propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    activeItem: PropTypes.instanceOf(Immutable.Map),
    scheduleItems: PropTypes.instanceOf(Immutable.List),
    isWorkers: PropTypes.bool,
    listSize: PropTypes.number,
};

const mapStateToProps = (state, props) => {
    const activeItem = chargeSelector(state, props);
    const total = Immutable.Map.isMap(activeItem) ? parseFloat(activeItem.getIn(['details', 'total'], 0)) : 0;
    const done = Immutable.Map.isMap(activeItem) ? parseFloat(activeItem.getIn(['details', 'done'], 0)): 0;
    const progress = parseFloat(done === 0 ? 0 : (done / total ) * 100);
    const dateTimeFormat = getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm');
    return ({
        total,
        done,
        progress,
        dateTimeFormat,
        activeItem,
        items: chargingListSelector(state, props),
        isWorkers: isWorkersSelector(state, props),
        scheduleItems: chargingScheduleListSelector(state, props),
        listSize: pageFlagSelector(state, props, 'charging', 'listSize'),
    });
}

const mapDispatchToProps = (dispatch, props) => ({
    loadCharges: (size) => {
        dispatch(getCharges(size));
        dispatch(getChargesSchedule());
    },
    removeCharges: () => dispatch(clearCharges()),
    loadActiveCharge: (charge) => dispatch(setActiveCharge(charge)),
    loadChargeProgressDetails: (charge) => dispatch(getChargeDetails(charge)),
    onChangeListSize: (size) => dispatch(setPageFlag('charging', 'listSize', size)),
    onClearCharge: () => dispatch(clearCharge()),
    onCancelCharge: (charge) => dispatch(cancelCharge(charge)).then(() => dispatch(getChargesSchedule())),
    onSaveCharge: (charge) => dispatch(startCharge(charge)),
    showConfirmPopup: (confirm) => dispatch(showConfirmModal(confirm)),
    showItemDetailsPopup: (data, config) => dispatch(showFormModal(data, ChargingDetails, config)),
    showItemCreatePopup: (data, config) => dispatch(showFormModal(data, ChargingForm, config)),
});

const mergeProps = (stateProps, dispatchProps, ownProps) => {

    const onReloadItems = () => {
        dispatchProps.loadCharges(stateProps.listSize);
        if (Immutable.Map.isMap(stateProps.activeItem) && stateProps.activeItem.get('md5', '') !== '') {
            dispatchProps.loadActiveCharge(stateProps.activeItem);
        }
    };

    const onConfirmStartCharge = (item) => {
        let confirm = {
            type: 'confirm',
            labelOk: 'Yes',
            onOk: () => dispatchProps.onSaveCharge(item).then((res) => {
                if (res === true) {
                    dispatchProps.loadCharges(stateProps.listSize);
                }
            }),
        };
        
        const scheduleDate = moment(item.get('schedule', null));
        const isSchedule = moment.isMoment(scheduleDate) && scheduleDate.isValid();
        confirm.message = isSchedule
            ? 'Are you sure you what to create a charging schedule?'
            : 'Are you sure you what to start charging?';
        confirm.children = <Form horizontal id='charging-wrapper'>
            { isSchedule && (
                <FormGroup>
                    <Col sm={5} componentClass={ControlLabel}>
                        {getFieldName('schedule', 'charging_process', 'Scheduler')}:
                    </Col>
                    <Col sm={5}>
                        <Field editable={false} value={scheduleDate.format(stateProps.dateTimeFormat)} />
                    </Col>
                </FormGroup>
            )}
            { !isSchedule && (
                <FormGroup>
                    <Col sm={5} componentClass={ControlLabel}>
                        {getFieldName('start_time', 'charging_process')}:
                    </Col>
                    <Col sm={5}>
                        <Field editable={false} value={moment().format(stateProps.dateTimeFormat)} />
                    </Col>
                </FormGroup>
            )}
            { item.get('run_on', '') === 'include' && (
                <FormGroup>
                    <Col sm={5} componentClass={ControlLabel}>
                        {getFieldName('include_aids', 'charging_process')}:
                    </Col>
                    <Col sm={5}>
                        <Field fieldType="json" className="included-excluded-items" value={item.get('include', null)} editable={false} />
                    </Col>
                </FormGroup>
            )}
            { item.get('run_on', '') === 'exclude' && (
                <FormGroup>
                    <Col sm={5} componentClass={ControlLabel}>
                        {getFieldName('exclude_aids', 'charging_process')}:
                    </Col>
                    <Col sm={5}>
                        <Field fieldType="json" className="included-excluded-items" value={item.get('exclude', null)} editable={false} />
                    </Col>
                </FormGroup>
            )}
        </Form>;
        dispatchProps.showConfirmPopup(confirm);
    }

    const onCreateChargeInPopup = () => dispatchProps.showItemCreatePopup(Immutable.Map({
        run_on: 'all',
        pay_mode: 'one_payment',
        mode: 'charge',
    }), {
        title: 'Start new charge batch',
        skipConfirmOnClose: false,
        onOk: onConfirmStartCharge,
        labelOk: 'Start',
    });

    const onCancelCharge = (charge) => dispatchProps.showConfirmPopup({
        message: `Are you sure you want to cancel charge ID "${charge.get('md5')}" ?`,
        onOk: () => dispatchProps.onCancelCharge(charge),
        type: 'delete',
        labelOk: 'Delete',
    });

    const onViewCharge = (charge) => dispatchProps.loadChargeProgressDetails(charge)
        .then((data) => dispatchProps.showItemDetailsPopup(data, {
            title: `Details of charge ID: "${data.get('md5', '')}"`,
            skipConfirmOnClose: true,
            showOnOk: false,
            labelCancel: "Close",
        }));

    const activePanelActions = [{
        type: 'view',
        actionStyle: 'primary',
        actionSize: 'xsmall',
        label: 'View Details',
        onClick: onViewCharge,
    }, {
        type: 'refresh',
        actionStyle: 'primary',
        actionSize: 'xsmall',
        label: 'Update Progress',
        onClick: dispatchProps.loadActiveCharge,
    }];

    return ({
        ...stateProps,
        ...dispatchProps,
        ...ownProps,
        activePanelActions,
        onReloadItems,
        onViewCharge,
        onCancelCharge,
        onCreateChargeInPopup,
    });
};


export default connect(mapStateToProps, mapDispatchToProps, mergeProps)(Charging);