import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Tab, Panel } from 'react-bootstrap';
import { TabsWrapper } from '@/components/Elements';
import CollectionSettings from './CollectionSettings';
import CollectionsList from './CollectionsList';
import CollectionStep from './CollectionStep';
import { ModalWrapper } from '@/components/Elements';
import { getSettings } from '@/actions/settingsActions';
import { saveCollectionStep } from '@/actions/collectionsActions';
import { getConfig } from '@/common/Util';


class Collections extends Component {

  static propTypes = {
    location: PropTypes.object.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
  };

  state = {
    editedItem: null,
    editedItemName: '',
    errors: Immutable.Map(),
  };

  componentWillMount() {
    this.props.dispatch(getSettings('template_token'));
  }

  onCloseEditStep = () => {
    this.setState(() => ({ editedItem: null, editedItemName: '', errors: Immutable.Map() }));
  }

  onChangeEditStep = (path, value) => {
    this.setState(prevState => ({
      editedItem: prevState.editedItem.setIn(path, value),
      errors: prevState.errors.deleteIn(path),
    }));
  }

  onAddStep = type => () => {
    const active = getConfig(['collections', 'default_new_step_status'], false);
    this.setState(() => ({
      editedItem: Immutable.Map({ type, active }),
    }));
  }

  onSaveEditStep = () => {
    const { editedItem } = this.state;
    if (!this.validateStep(editedItem)) {
      this.props.dispatch(saveCollectionStep(editedItem));
      this.onCloseEditStep();
    }
  }

  onClickEdit = (item) => {
    this.setState(() => ({
      editedItem: item,
      editedItemName: item.get('name', ''),
      errors: Immutable.Map(),
    }));
  }

  onClickClone = (item) => {
    this.onClickEdit(item.delete('id'));
    this.setState(() => ({
      editedItemName: '',
    }));
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
        labelOk="Save"
        modalSize="large"
      >
        <CollectionStep item={editedItem} onChange={this.onChangeEditStep} errors={errors} />
      </ModalWrapper>
    );
  }

  render() {
    const { location } = this.props;
    return (
      <div>
        <TabsWrapper id="CollectionsTab" location={location}>
          <Tab title="Steps" eventKey={1}>
            <Panel style={{ borderTop: 'none' }}>
              <CollectionsList
                onAddStep={this.onAddStep}
                onClickEdit={this.onClickEdit}
                onClickClone={this.onClickClone}
              />
            </Panel>
          </Tab>
          <Tab title="Settings" eventKey={2}>
            <Panel style={{ borderTop: 'none' }}>
              <CollectionSettings />
            </Panel>
          </Tab>
        </TabsWrapper>
        {this.renderEventForm()}
      </div>
    );
  }
}

export default connect(null)(Collections);
