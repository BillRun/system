import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import Field from '@/components/Field';
import {
  plansOptionsSelector,
} from '@/selectors/listSelectors';
import { formatSelectOptions } from '@/common/Util';
import { getList } from '@/actions/listActions';
import { getPlansByTypeQuery } from '../../common/ApiQueries';


class PlanSearch extends Component {

  static propTypes = {
    options: PropTypes.instanceOf(Immutable.List),
    selectedOptions: PropTypes.array,
    onSelectPlan: PropTypes.func,
    planType: PropTypes.oneOf(['prepaid', 'postpaid', '']),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    options: Immutable.List(),
    selectedOptions: [],
    onSelectPlan: () => {},
    planType: '',
  };

  state = { val: null }

  componentDidMount() {
    const { planType } = this.props;
    this.props.dispatch(getList('available_plans', getPlansByTypeQuery(planType)));
  }

  onSelectPlan = (planKey) => {
    if (planKey) {
      this.props.onSelectPlan(planKey);
    }
    this.setState({ val: null });
  }

  render() {
    const { options, selectedOptions } = this.props;

    const formatedOptions = options
      .filter(option => !selectedOptions.includes(option.get('value', '')))
      .map(formatSelectOptions)
      .toArray();

    return (
      <div className="PlanSearch">
        <Field
          fieldType="select"
          value={this.state.val}
          onChange={this.onSelectPlan}
          options={formatedOptions}
          placeholder="Search by plan name..."
          noResultsText="No plans found, please try another name"
        />
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  options: plansOptionsSelector(state, props),
});
export default connect(mapStateToProps)(PlanSearch);
