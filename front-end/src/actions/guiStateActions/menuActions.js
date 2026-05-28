import Immutable from 'immutable';
import { getConfig } from '@/common/Util';


export const PREPARE_MAIN_MENU_STRUCTURE = 'PREPARE_MAIN_MENU_STRUCTURE';

export const TOGGLE_SIDE_BAR = 'TOGGLE_SIDE_BAR';

export const toggleSideBar = (state = null) => ({
  type: TOGGLE_SIDE_BAR,
  state,
});

export const initMainMenu = (mainMenuOverrides = {}) => ({
  type: PREPARE_MAIN_MENU_STRUCTURE,
  mainMenuOverrides,
});

export const combineMenuOverrides = overrides => (
  getConfig('mainMenu', Immutable.Map()).withMutations((mainMenuTreeWithMutations) => {
    if (overrides && overrides.size) {
      overrides.forEach((menuData, menuKey) => {
        if (mainMenuTreeWithMutations.has(menuKey)) {
          menuData.forEach((value, menuProperty) => {
            mainMenuTreeWithMutations.setIn([menuKey, menuProperty], value);
          });
        }
      });
    }
  })
);

export const prossessMenuTree = (tree, parentId) => (
  tree
    .filter(menuItem => menuItem.get('parent') === parentId)// Filter tree level by parnet ID
    .map((menuItem, id) => menuItem.set('id', id)) // Set Id propertu from object key
    .toList() // Convert to Array
    .sort((menuItemA, menuB) => {
      const defaultOrder = 999;
      return (menuItemA.get('order', defaultOrder) < menuB.get('order', defaultOrder)) ? -1 : 1;
    }) // Sort menu level by order property
    .map((menuItem, order) => {
      const subtree = prossessMenuTree(tree, menuItem.get('id'));
      return menuItem.withMutations((menuItemWithMutations) => {
        // Build sub tree menus if exist
        if (subtree.size) {
          menuItemWithMutations.set('subMenus', subtree);
        }
        // Set new order after mereg config + json menu items
        menuItemWithMutations.set('order', order);
      });
    })
);
