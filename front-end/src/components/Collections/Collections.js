import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Col, Form, Panel } from 'react-bootstrap';
import CollectionStep from './CollectionStep';
import Collection from './Collection';
import { ModalWrapper, ActionButtons, SortableFieldsContainer } from '@/components/Elements';
import { getSettings } from '@/actions/settingsActions';
import {
  getCollections,
  saveCollections,
  updateCollections,
  updateCollectionStep,
  removeCollectionStep,
} from '@/actions/collectionsActions';
import {
  showConfirmModal,
  setPageFlag,
  setPageError,
} from '@/actions/guiStateActions/pageActions.js';
import { collectionSelector } from '@/selectors/settingsSelector';
import { pageFlagSelector, getPageErrors } from '@/selectors/guiSelectors';
import { getConfig } from '@/common/Util';


class Collections extends Component {

  static propTypes = {
    processes: PropTypes.instanceOf(Immutable.List),
    pageErrors: PropTypes.instanceOf(Immutable.Map),
    isDirty: PropTypes.bool,
    dirtySets:PropTypes.instanceOf(Immutable.List),
    location: PropTypes.object.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    processes: Immutable.List(),
    dirtySets: Immutable.List(),
    pageErrors: Immutable.Map(),
    isDirty: false,
  };

  static defaultProcess = Immutable.Map({
    name: "",
    label: "",
    conditions: Immutable.List([
      Immutable.Map({
        "account": Immutable.Map({
          "fields": Immutable.List()
        }),
      })
    ]),
    settings: {
      "min_debt" : 0,
      "change_state_url" : "",
      "change_state_method" : "post"
    },
    steps: Immutable.List(),
  });

  state = {
    reordering: false,
    editedItem: null,
    editedItemName: '',
    editedIndex: null,
    errors: Immutable.Map(),
  };

  componentWillMount() {
    this.props.dispatch(getSettings([
      'template_token',
      'subscribers.account.fields'
    ]));
    this.props.dispatch(getCollections());
  }

  componentWillUnmount() {
    this.props.dispatch(setPageFlag('collection'));
    this.props.dispatch(setPageError('collection'));
  }

  onReorderStart = () => {
    this.setState(() => ({ reordering: true }));
  }

  onReorderSave = () => {
    this.setState(() => ({ reordering: false }));
    this.onSave()
  }

  onReorderCancel = () => {
    this.setState(() => ({ reordering: false }))
    this.props.dispatch(getCollections());
  }

  onReorderEnd = ({ oldIndex, newIndex }) => {
    const { processes } = this.props;
    const movingProcess = processes.get(oldIndex);
    const newOrderProcesses = processes.delete(oldIndex).insert(newIndex, movingProcess); 
    this.props.dispatch(updateCollections([], newOrderProcesses));
  };

  onChange = (path, value) => {
    this.props.dispatch(setPageError('collection', path.join('.')));
    this.props.dispatch(updateCollections(path, value));
  }

  onChangeStep = (index, step) => {
    this.props.dispatch(updateCollectionStep(index, step));
  }

  onRemoveStep = (index, step) => {
    this.props.dispatch(removeCollectionStep(index, step));
  }

  onCloseEditStep = () => {
    this.setState(() => ({
      editedItem: null,
      editedItemName: '',
      editedIndex: null,
      errors: Immutable.Map(),
    }));
  }

  onChangeEditStep = (path, value) => {
    this.setState(prevState => ({
      editedItem: prevState.editedItem.setIn(path, value),
      errors: prevState.errors.deleteIn(path),
    }));
  }

  onClickAdd = (index, type) => () => {
    const active = getConfig(['collections', 'default_new_step_status'], false);
    this.setState(() => ({
      editedIndex: index,
      editedItem: Immutable.Map({ type, active }),
    }));
  }

  onSaveEditStep = () => {
    const { editedItem, editedIndex } = this.state;
    if (!this.validateStep(editedItem)) {
      this.props.dispatch(updateCollectionStep(editedIndex, editedItem));
      this.onCloseEditStep();
    }
  }

  onClickEdit = (index, item) => {
    this.setState(() => ({
      editedItem: item,
      editedIndex: index,
      editedItemName: item.get('name', ''),
      errors: Immutable.Map(),
    }));
  }

  onClickClone = (index, item) => {
    this.setState(() => ({
      editedItem: item.delete('id'),
      editedItemName: '',
      editedIndex: index,
      errors: Immutable.Map(),
    }));
  }

  onRemoveProcess = (index) => {
    const { processes } = this.props;
    const process = processes.get(index, Immutable.Map());
    const confirm = {
      message: `Are you sure you want to delete "${process.get('name')}" ?`,
      onOk: () => this.props.dispatch(updateCollections([], processes.delete(index))),
      type: 'delete',
      labelOk: 'Delete',
      labelCancel: 'No',
    };
    this.props.dispatch(showConfirmModal(confirm));

  }

  onAddProcess = () => {
    const { processes } = this.props;
    if (!this.validateProcesses(processes)) {
      this.props.dispatch(updateCollections([], processes.push(Collections.defaultProcess)));
    }
  }

  onCancel = () => {
    const confirm = {
      message: "You have unsaved changes. If you discard them, all modifications will be lost.",
      onOk: () => this.props.dispatch(getCollections()),
      type: 'delete',
      labelOk: 'Discard Changes',
      labelCancel: 'Keep Editing',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onSave = () => {
    this.props.dispatch(saveCollections());
  }

  validateProcesses = (processes) => {
    let hasError = false;
    processes.forEach((process, index) => {
      if (['', null].includes(process.get('name', ''))) {
        this.props.dispatch(setPageError('collection', [index, 'name'].join('.'), 'Key field is required'));
        hasError = true;
      }
      if (['', null].includes(process.get('label', ''))) {
        this.props.dispatch(setPageError('collection', [index, 'label'].join('.'), 'Title field is required'));
        hasError = true;
      }
    });
    return hasError;
  }

  validateStep = (item) => {
    let hasError = false;
    if (['', null].includes(item.get('name', ''))) {
      this.setState(prevState => ({ errors: prevState.errors.setIn(['name'], 'Title field is required') }));
      hasError = true;
    }
    if (['', null].includes(item.get('do_after_days', ''))) {
      this.setState(prevState => ({ errors: prevState.errors.setIn(['do_after_days'], 'Trigger after field is required') }));
      hasError = true;
    }
    if (item.get('type', '') === 'http' && ['','null'].includes(item.getIn(['content', 'url'], ''))) {
      this.setState(prevState => ({ errors: prevState.errors.setIn(['content', 'url'], 'URL field is required') }));
      hasError = true;
    }
    return hasError;
  }

  renderEventForm = () => {
    const { editedItem, editedItemName, errors } = this.state;
    if (editedItem === null) {
      return null;
    }
    const title = editedItemName !== ''
      ? `Edit "${editedItemName}" step`
      : (<span>Create {getConfig(['collections', 'step_types', editedItem.get('type', ''), 'label'], '')} Step</span>);
    return (
      <ModalWrapper
        title={title}
        show={true}
        onOk={this.onSaveEditStep}
        onCancel={this.onCloseEditStep}
        labelOk="OK"
        modalSize="large"
      >
          <CollectionStep
            item={editedItem}
            errors={errors}
            onChange={this.onChangeEditStep}
          />
      </ModalWrapper>
    );
  }

  getCollectionsRows = () => {
    const { reordering } = this.state;
    const { location, processes, pageErrors, dirtySets } = this.props;
    return processes.map((process, idx) => (
      <Collection
        index={idx}
        key={`dunning_${idx}`}
        process={process}
        location={location}
        errors={pageErrors}
        reordering={reordering}
        isDirty={dirtySets.includes(idx)}
        onChange={this.onChange}
        onChangeStep={this.onChangeStep}
        onRemoveStep={this.onRemoveStep}
        onRemove={this.onRemoveProcess}
        onClickAdd={this.onClickAdd}
        onClickEdit={this.onClickEdit}
        onClickClone={this.onClickClone}
      />
    )).toArray();
  }

  render() {
    const { isDirty, dirtySets } = this.props;
    const { reordering } = this.state;
    
    return (
      <Panel bsStyle={dirtySets.includes(-1) ? "warning" : "default"}>
        {isDirty && (<Col sm={12} className="pr0 pl0"><p className="alert-warning mb0 pl10 pr10 pt5 pb5">You have unsaved changes!</p></Col>)}
        <Form horizontal>
          <Col sm={12}>
            <SortableFieldsContainer
              lockAxis="y"
              helperClass="draggable-row"
              useDragHandle={true}
              items={this.getCollectionsRows()}
              onSortEnd={this.onReorderEnd}
              />
          </Col>
        </Form>

        { !reordering && (
          <div className="form-actions-controllers">
            <div className="pull-left">
              <ActionButtons
                onClickSave={this.onSave}
                disableSave={!isDirty}
                onClickCancel={this.onCancel}
                disableCancel={!isDirty}
              />
            </div>
            <div className="pull-right">
              <ActionButtons
                saveLabel="Add process"
                onClickSave={this.onAddProcess}
                cancelLabel="Reorder"
                onClickCancel={this.onReorderStart}
                disableCancel={isDirty}
                cancelTitle={isDirty ? 'Save changes to reorder': ''}
                />
            </div>
          </div>
        )}
        { reordering && (
          <div className="form-actions-controllers">
            <div className="pull-left"></div>
            <div className="pull-right">
              <ActionButtons
                saveLabel="Save New Order"
                onClickSave={this.onReorderSave}
                disableSave={!isDirty}
                cancelLabel="Cancel New Order"
                onClickCancel={this.onReorderCancel}
              />
            </div>
          </div>
        )}
        {this.renderEventForm()}
      </Panel>
    );
  }
}

const mapStateToProps = (state, props) => ({
  processes: collectionSelector(state, props),
  isDirty: pageFlagSelector(state, props, 'collection', 'isFormDirty'),
  dirtySets: pageFlagSelector(state, props, 'collection', 'dirtySets'),
  pageErrors: getPageErrors(state, props, 'collection'),
});

export default connect(mapStateToProps)(Collections);
