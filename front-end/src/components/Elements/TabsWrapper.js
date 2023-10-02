import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { withRouter } from 'react-router';
import { connect } from 'react-redux';
import { Tabs } from 'react-bootstrap';
import uuid from 'uuid';
import { tabSelector } from '@/selectors/entitySelector';


class TabsWrapper extends Component {

  static propTypes = {
    id: PropTypes.string,
    children: PropTypes.node,
    activeTab: PropTypes.number,
    location: PropTypes.shape({
      pathname: PropTypes.string,
      query: PropTypes.object,
    }).isRequired,
    router: PropTypes.object.isRequired,
  };

  static defaultProps = {
    id: uuid.v4(),
    children: null,
    activeTab: 1,
  };

  handleSelectTab = (tab) => {
    const { pathname, query } = this.props.location;
    this.props.router.push({
      pathname,
      query: Object.assign({}, query, { tab }),
    });
  }

  render() {
    const { id, children, activeTab } = this.props;

    return (
      <div>
        <Tabs
          defaultActiveKey={activeTab}
          animation={false}
          id={id}
          onSelect={this.handleSelectTab}
        >
          {children}
        </Tabs>
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  activeTab: tabSelector(state, props),
});

export default withRouter(connect(mapStateToProps)(TabsWrapper));
