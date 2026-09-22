import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { titleCase } from 'change-case';
import { Panel } from '@/common/BootstrapCompat';
import Exporter from './Exporter';
import { exportItemTypeSelector } from '../../selectors/entitySelector';
import { getConfig } from '@/common/Util';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
 import {
   exportEntities,
 } from '@/actions/entityActions';


class ExporterSetup extends Component {

  static defaultProps = {
    exportEntity: undefined,
  };

  static propTypes = {
    exportEntity: PropTypes.string,
    dispatch: PropTypes.func.isRequired,
  };

  
  
  componentDidMount() {
    const { exportEntity } = this.props;
    this.updatePageTitle(exportEntity);
  }

  
  updatePageTitle = (exportEntity = null) => {
    if (exportEntity === null) {
      this.props.dispatch(setPageTitle('Export'));
    } else {
      const entityName = getConfig(['systemItems', exportEntity, 'itemsName'], exportEntity);
      this.props.dispatch(setPageTitle(titleCase(`Export ${entityName}`)));
    }
  }

  handleExport = (entity, exportParams) => this.props.dispatch(
    exportEntities(entity, exportParams)
  );

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { exportEntity } = this.props;
    if (prevProps.exportEntity !== exportEntity) {
      this.updatePageTitle(exportEntity);
    }
  }

  componentWillUnmount() {
    this.props.dispatch(setPageTitle(''));
  }

  render() {
    const { exportEntity } = this.props;
    return (
      <Panel>
        <Exporter
          entityKey={exportEntity}
          onExport={this.handleExport}
        />
      </Panel>
    );
  }
}

const mapStateToProps = (state, props) => ({
  exportEntity: exportItemTypeSelector(state, props) || undefined,
});

export default connect(mapStateToProps)(ExporterSetup);
