import React, { useState } from "react";
import PropTypes from "prop-types";
import Immutable from "immutable";
import { SortableElement } from "react-sortable-hoc";
import { Col, Panel, Tab, FormGroup } from "react-bootstrap";
import classNames from "classnames";
import { TabsWrapper, DragHandle, Actions } from "@/components/Elements";
import CollectionStepsList from "./CollectionStepsList";
import CollectionSettings from "./CollectionSettings";
import CollectionConditions from "./CollectionConditions";


const Collection = ({
  process,
  index,
  reordering,
  location,
  errors,
  isDirty,
  onChange,
  onChangeStep,
  onRemoveStep,
  onRemove,
  onClickAdd,
  onClickEdit,
  onClickClone,
 }) => {

  const [isOpen, toggleOpen] = useState(process.getIn(["name"], "") === '');

  const onChangeSettings = (path, value) => {
    return onChange([index, ...path], value);
  };
  const onChangeConditions = (path, value) => {
    return onChange([index, "conditions", ...path], value);
  };
  const onRemoveConditionStep = (step) => {
    return onRemoveStep(index, step);
  };
  const onClickAddStep = (type) => {
    return onClickAdd(index, type);
  };
  const onClickCloneStep = (step) => {
    return onClickClone(index, step);
  };
  const onClickEditStep = (step) => {
    return onClickEdit(index, step);
  };
  const onChangeConditionStep = (step) => {
    return onChangeStep(index, step);
  };
  const onRemoveProcess = () => {
    return onRemove(index);
  };

  const actions = [
    { type: 'remove', showIcon: true, onClick: onRemoveProcess },
  ];

  const getPanelHeader = () => (
    <div onClick={() => toggleOpen(!isOpen)}>
      <div className="inline">
        <h4 className="mt0 mb0">Process #{index + 1} | {process.get('label', '')} <small>{process.get('name', '')}</small></h4>
      </div>
      <div className="pull-right">
        <Actions actions={actions} />
      </div>
    </div>
  );

  const listClass = classNames("form-inner-edit-row", {
    withHover: reordering,
  });

  return (
    <FormGroup className={listClass}>
      {reordering && (
        <Col sm={1} className='text-center mt10 mb0'>
          <DragHandle />
        </Col>
      )}
      <Col sm={reordering ? 10 : 12} className='pr0 pl0'>
        <Panel
          expanded={isOpen && !reordering}
          header={getPanelHeader()}
          collapsible={true}
          className='collapsible mt10 mb10'
          // defaultExpanded={defaultExpanded}
          bsStyle={isDirty ? "warning" : "default"}
        >
          <TabsWrapper id='CollectionsTab' location={location}>
            <Tab title='Settings' eventKey={1}>
              <Panel style={{ borderTop: "none" }}>
                <CollectionSettings
                  process={process}
                  index={index}
                  errors={errors}
                  onChange={onChangeSettings}
                />
              </Panel>
            </Tab>
            <Tab title='Conditions' eventKey={2}>
              <Panel style={{ borderTop: "none" }}>
                <CollectionConditions
                  conditions={process.getIn(["conditions"], Immutable.List())}
                  fields={Immutable.List()}
                  onChange={onChangeConditions}
                />
              </Panel>
            </Tab>
            <Tab title='Steps' eventKey={3}>
              <Panel style={{ borderTop: "none" }}>
                <CollectionStepsList
                  steps={process.getIn(["steps"], Immutable.List())}
                  onChange={onChangeConditionStep}
                  onRemove={onRemoveConditionStep}
                  onClickAdd={onClickAddStep}
                  onClickEdit={onClickEditStep}
                  onClickClone={onClickCloneStep}
                />
              </Panel>
            </Tab>
          </TabsWrapper>
        </Panel>
      </Col>

      {reordering && (
        <Col sm={1} className='text-center mt10 mb0'></Col>
      )}
    </FormGroup>
  );
};


Collection.defaultProps = {
  process: Immutable.Map(),
  errors: Immutable.Map(),
  index: 0,
  reordering: false,
  isDirty: false,
};

Collection.propTypes = {
  process: PropTypes.instanceOf(Immutable.Map),
  index: PropTypes.number,
  reordering: PropTypes.bool,
  isDirty: PropTypes.bool,
  location: PropTypes.object.isRequired,
  errors: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func.isRequired,
  onChangeStep: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  onClickEdit: PropTypes.func.isRequired,
  onClickClone: PropTypes.func.isRequired,
};

export default SortableElement(Collection);
