import Immutable from 'immutable';
import { actions } from '@/actions/settingsActions';
import { ADD_USAGET_MAPPING } from '@/actions/inputProcessorActions';
import { LOGOUT } from '@/actions/userActions';
import { convertImporterMapperFromDb } from '@/actions/importerActions';
import { parseGotPlugins } from '@/actions/pluginActions';
import { getConfig } from '@/common/Util';



const LogoImg = `${process.env.PUBLIC_URL}/assets/img/${getConfig('defaultLogo', 'billRun-cloud-logo.png')}`;
const defaultState = Immutable.fromJS({
  subscribers: {
    account: {
      fields: [],
    },
    subscriber: {
      fields: [],
    },
  },
  files: {
    logo: LogoImg,
  },
  payment_gateways: [],
});

export default function (state = defaultState, action) {
  const { name, value, category, settings, gateway } = action;

  switch (action.type) {
    case LOGOUT:
      return defaultState;

    case actions.UPDATE_SETTING: {
      if (Array.isArray(name)) {
        return state.setIn([category, ...name], value);
      }
      return state.setIn([category, name], value);
    }

    case actions.PUSH_TO_SETTING: {
      let path;
      if (!action.path) {
        path = [category];
      } else if (action.path && Array.isArray(action.path)) {
        path = [category, ...action.path];
      } else {
        path = [category, action.path];
      }
      return state.updateIn(path, Immutable.List(), list => list.push(action.value));
    }

    case ADD_USAGET_MAPPING:
      return state.update('usage_types', list => list.push(action.usaget));

    case actions.GOT_SETTINGS:
      return state.withMutations((stateWithMutations) => {
        settings.forEach((setting) => {
          let data = Immutable.fromJS(setting.data.details);
          if (setting.name === 'taxation' && data.get('vat', '') !== '') {
            data = data.set('vat', data.get('vat', '') * 100);
          }
          if (setting.name === 'import.mapping' && Immutable.List.isList(data)) {
            data = data.map(convertImporterMapperFromDb);
          }
          if (setting.name === 'taxation.vat' && data !== '') {
            data *= 100;
          }
          if (setting.name === 'plugins' && Immutable.List.isList(data)) {
            data = parseGotPlugins(data);
          }
          stateWithMutations.setIn(setting.name.split('.'), data);
        });
      });

    case actions.ADD_PAYMENT_GATEWAY: {
      const added = state.get('payment_gateways').filterNot(pg => pg.get('name') === gateway.name).push(Immutable.fromJS(gateway));
      return state.set('payment_gateways', added);
    }

    case actions.REMOVE_PAYMENT_GATEWAY: {
      const removed = state.get('payment_gateways').filterNot(pg => pg.get('name') === gateway);
      return state.set('payment_gateways', removed);
    }

    case actions.UPDATE_PAYMENT_GATEWAY: {
      const paymentgateway = state.get('payment_gateways').find(pg => pg.get('name') === gateway.name).set('params', gateway.params);
      const paymentgateways = state.get('payment_gateways').filterNot(pg => pg.get('name') === gateway.name).push(paymentgateway);
      return state.set('payment_gateways', paymentgateways);
    }

    case actions.REMOVE_SETTING_FIELD: {
      if (Array.isArray(name)) {
        return state.deleteIn([category, ...name]);
      }
      return state.deleteIn([category, name]);
    }

    case actions.SET_FIELD_POSITION: {
      const curr = state.getIn([...action.path, action.oldIndex]);
      return state.updateIn(action.path, Immutable.List(), list =>
        list.delete(action.oldIndex).insert(action.newIndex, curr),
      );
    }

    default:
      return state;
  }
}
