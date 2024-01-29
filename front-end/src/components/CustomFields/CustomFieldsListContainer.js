import { connect } from 'react-redux';
import { Map } from 'immutable';
import CustomFieldsList from './CustomFieldsList';
import {
  pageFlagSelector,
} from '../../selectors/guiSelectors';
import {
  setPageFlag,
} from '../../actions/guiStateActions/pageActions';


const mapStateToProps = (state, props) => ({
  reordering: pageFlagSelector(state, props, 'customFields', `reorder.${props.entity}`),
  fieldsConfig: props.fieldsSettings.get(props.entity, Map()).delete('fields'),
  fields: props.fieldsSettings.getIn([props.entity, 'fields']),
});

const mapDispatchToProps = (dispatch, {
  entity, onNew, onReorder, onReorederSave, onReorederCancel, orderChanged,
}) => ({
  onNew: () => onNew(entity),
  onReorder: e => onReorder(entity, e),
  onReorederSave: () => onReorederSave(entity, orderChanged === true),
  onReorederCancel: () => onReorederCancel(entity, orderChanged === true),
  onReorederStart: () => dispatch(setPageFlag('customFields', `reorder.${entity}`, true)),
});

export default connect(mapStateToProps, mapDispatchToProps)(CustomFieldsList);
