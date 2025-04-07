import React from "react";
import PropTypes from "prop-types";
import Immutable from "immutable";
import { SortableElement } from "react-sortable-hoc";
import { Col, Row, Panel, Tab } from "react-bootstrap";
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
  onChange,
  onChangeStep,
  onRemoveStep,
  onRemove,
  onClickAdd,
  onClickEdit,
  onClickClone,
 }) => {
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
    <div>
      <div className="inline">
        <h4 className="mt0 mb0">Set #{index + 1} | {label} <small>{name}</small></h4>
      </div>
      <div className="pull-right">
        <Actions actions={actions} />
      </div>
    </div>
  );

  const name = process.getIn(["name"], "");
  const label = process.getIn(["label"], "");
  const listClass = classNames("table-row", {
    withHover: reordering,
  });
  const defaultExpanded = name === "";
  return (
    <Row className={listClass}>
      {reordering && (
        <Col sm={1} className='text-center mt10 mb0'>
          <DragHandle />
        </Col>
      )}
      <Col sm={reordering ? 11 : 12} className='pr0 pl0'>
        <Panel header={getPanelHeader()} collapsible={true} className='collapsible mt10 mb10' defaultExpanded={defaultExpanded}>
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
    </Row>
  );
};


Collection.defaultProps = {
  process: Immutable.Map(),
  errors: Immutable.Map(),
  index: 0,
  reordering: false,
};

Collection.propTypes = {
  process: PropTypes.instanceOf(Immutable.Map),
  index: PropTypes.number,
  reordering: PropTypes.bool,
  location: PropTypes.object.isRequired,
  errors: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func.isRequired,
  onChangeStep: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  onClickEdit: PropTypes.func.isRequired,
  onClickClone: PropTypes.func.isRequired,
};

export default SortableElement(Collection);
