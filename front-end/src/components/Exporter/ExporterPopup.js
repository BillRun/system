import React, { useCallback } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { titleCase } from 'change-case';
import Exporter from './Exporter';
import { ModalWrapper } from '@/components/Elements';
 import {
   exportEntities,
 } from '@/actions/entityActions';
 import {
   getConfig,
 } from '@/common/Util';


const ExporterPopup = ({ entityKey, show, onClose, dispatch }) => {

  const handleExport = useCallback((entity, exportParams) => {
    const result = dispatch(exportEntities(entity, exportParams));
    onClose(result);
  }, [dispatch, onClose]);

  const onHide = useCallback(() => {
    onClose();
  }, [onClose]);

  return (
    <ModalWrapper
      show={show}
      title={`Export ${titleCase(getConfig(['systemItems', entityKey, 'itemsName'], entityKey))}`}
      onHide={onHide}
      modalSize="large"
    >
      <Exporter
        entityKey={entityKey}
        onExport={handleExport}
      />
    </ModalWrapper>
  );
}

ExporterPopup.defaultProps = {
  show: false,
  onClose: () => {}
};

ExporterPopup.propTypes = {
  entityKey: PropTypes.string.isRequired,
  show: PropTypes.bool,
  onClose: PropTypes.func,
  dispatch: PropTypes.func.isRequired,
};


export default connect()(ExporterPopup);
