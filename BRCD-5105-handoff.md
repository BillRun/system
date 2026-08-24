# BRCD-5105 Handoff — React 16→19 / Bootstrap 3→5 upgrade

## Контекст

| | |
|---|---|
| **Ветка** | `BRCD-5105` в `billrun-1` |
| **Эталон** | `customer_portal` (React 16, стабильная база) |
| **Цель** | Визуальный и поведенческий паритет с `customer_portal` после мажорных апгрейдов |

Два открытых бага из тикета:
1. **BillRun Tour не работает** — root cause найден, фикс ещё не написан
2. **Scrollbar в User Management на Chromium snap (Linux)** — **ЗАКРЫТ**

---

## Версии пакетов (до → после)

| Пакет | Было (`customer_portal`) | Стало (`billrun-1`) |
|---|---|---|
| react / react-dom | 16.x | **19.0.0** |
| react-bootstrap | 0.31.5 | **2.10.0** |
| bootstrap | 3.x (yeti.css) | **5.3.0** (yeti.css оставлен!) |
| react-router / react-router-dom | 3.x | **6.26.0** |
| react-redux | 5.x | **9.1.0** |
| redux | 3.x | **4.0.4** |
| react-joyride | **1.11.4** | **2.9.3** |
| react-select | 1.x | **5.8.0** |
| react-datepicker | старая | **9.1.0** |
| react-sortable-hoc | используется | **удалён → @dnd-kit** |
| react-bootstrap-multiselect | jQuery-плагин | **удалён → BS3Multiselect.js** |
| react-tagsinput | 3.19.0 | **удалён → TagsInput.js** |
| redux-localstorage | используется | **удалён → persistMiddleware** |

> **Важно:** yeti.css (Bootstrap 3 тема) оставлена намеренно — большинство BS3 классов (`.panel`, `.well`, `.label-*`, `.form-group`, etc.) используются напрямую или через шимы. Нет цели переехать на BS5-тему.

---

## Шим-файлы (ключевые новые файлы)

### `src/common/BootstrapCompat.js` — Главный Bootstrap 3→5 шим

Экспортирует компоненты-обёртки, которые воспроизводят BS3 API поверх RB2:

| Компонент | Было (RB 0.31) | Стало (BootstrapCompat) | Суть |
|---|---|---|---|
| `Panel` | `<Panel header bsStyle collapsible expanded>` | Card + compat props | collapsible, bsStyle→border-{variant}, defaultExpanded |
| `ControlLabel` | `<ControlLabel>` | Form.Label + класс `control-label` | нужен для `.form-horizontal` CSS |
| `HelpBlock` | `<HelpBlock>` | Form.Text + класс `help-block` | для project CSS |
| `Checkbox` | `<Checkbox>` | Form.Check type="checkbox" | |
| `Grid` | `<Grid fluid>` | Container | |
| `PageHeader` | `<PageHeader>` | `<div class="page-header"><h1>` | |
| `PanelGroup` | `<PanelGroup accordion>` | `<div class="panel-group">` | accordion через Card.Header ломался |
| `FormGroup` | `<FormGroup validationState="error">` | `<div class="form-group has-error">` | RB2 убрал form-group класс |
| `Label` | `<Label bsStyle="danger">` | `<span class="label label-danger">` | RB2 Badge/Badge.bg-danger конфликтует с yeti |
| `InputGroupButton` | `<InputGroup.Button>` | `<div class="input-group-btn">` | RB2 убрал InputGroup.Button |
| `Well` | `<Well bsSize="sm">` | `<div class="well well-sm">` | BS5/RB2 убрал Well |
| `NavDropdownCompat` | `<NavDropdown>` | кастомный `<li class="dropdown">` | RB2 NavDropdown не работал в BS3-navbar |
| `NavDropdownItem` | `<MenuItem>` | `<li role="presentation"><a>` | |

**Паттерн импорта по всей кодовой базе:**
```js
import { Panel, FormGroup, ControlLabel, HelpBlock, Label, Well, Grid } from '@/common/BootstrapCompat';
```

---

### `src/common/withRouter.js` — react-router v3→v6 шим

RR v6 убрал `withRouter` HOC. Новый шим оборачивает компонент и инжектирует v3-совместимые props через v6 хуки.

```js
// Даёт компоненту:
props.router.push(path | { pathname, query, state })
props.router.replace(...)
props.router.isActive(path)   // true если pathname starts with path
props.params                   // useParams()
props.location                 // useLocation() + .query object
props.routes                   // stub [{title:''}] (избегает крашей)
```

**Паттерн использования:**
```js
// Было:
import { withRouter } from 'react-router';
export default withRouter(connect(...)(MyComponent));

// Стало:
import withRouter from '@/common/withRouter';
export default withRouter(connect(...)(MyComponent));
```

`Link` тоже переехал:
```js
// Было:
import { Link } from 'react-router';
// Стало:
import { Link } from 'react-router-dom';
```

---

### `src/components/Filter/BS3Multiselect.js` — замена jQuery-плагина

`react-bootstrap-multiselect` был jQuery-плагином. Заменён чистым React-компонентом, который:
- воспроизводит оригинальный DOM (`.btn-group > .dropdown > ul.multiselect-container > li > a > label.checkbox`)
- yeti.css стилизует его автоматически без новых стилей

```js
<BS3Multiselect
  data={[{ value: 1, label: 'Active', selected: true }]}
  onChange={(value) => ...}    // value = '1,2' или ''
  nonSelectedText="All"
  buttonWidth="100%"
/>
```

---

### `src/components/Field/types/TagsInput.js` — in-house замена react-tagsinput

`react-tagsinput@3.19.0` использовал `componentWillReceiveProps` — несовместимо с React 19 StrictMode.
Написан in-house с той же внешней API (value, onChange, renderTag, renderInput, addOnBlur, onlyUnique).
CSS-классы идентичны (`react-tagsinput`, `react-tagsinput-tag`, `react-tagsinput-input` и т.д.) — существующие SCSS-оверрайды работают без изменений.

---

### `src/components/Elements/DndSortableItemContext.js` — контекст для @dnd-kit

Новый файл. Контекст, через который `SortableFieldsContainer` передаёт dnd-listeners/attributes в `DragHandle` без prop drilling.

```js
const DndSortableItemContext = React.createContext({
  attributes: {}, listeners: undefined,
  setActivatorNodeRef: () => {}, disabled: false,
});
```

---

## React 19 — что изменилось и как починено

### 1. Удалены устаревшие lifecycle методы

React 19 строже с legacy lifecycle в StrictMode. Повсеместно:

```js
// Было:
componentWillMount() { ... }
componentWillReceiveProps(nextProps) { ... }

// Стало:
componentDidMount() { ... }
componentDidUpdate(prevProps) { ... }
```

Важный нюанс: `componentWillReceiveProps` имел доступ к `nextProps`, а `componentDidUpdate` — к `prevProps`. Логика инвертирована:
```js
// Было (в componentWillReceiveProps):
if (!Immutable.is(states, nextProps.states)) {
  this.setState({ states: nextProps.states });
}

// Стало (в componentDidUpdate):
if (!Immutable.is(states, this.props.states)) {  // this.props уже NEW
  this.setState({ states: this.props.states });
}
```

### 2. `defaultProps` deprecated для функциональных компонентов

React 19 убрал поддержку `defaultProps` на function components. По всей кодовой базе переведено на ES6 default params:

```js
// Было:
const MyComp = ({ value, label }) => ...;
MyComp.defaultProps = { value: '', label: '' };

// Стало:
const MyComp = ({ value = '', label = '' }) => ...;
```

### 3. `createRoot` API

```js
// src/index.js — было:
ReactDOM.render(<App />, document.getElementById('root'));

// Стало:
import { createRoot } from 'react-dom/client';
const root = createRoot(document.getElementById('root'));
root.render(<App />);
```

### 4. Автоматический batching в React 19

React 19 батчит обновления state даже внутри setTimeout/Promise. Это влияет на `run: false → true` паттерн в OnBoarding (подробнее в разделе про тур).

### 5. Strict Mode — двойной вызов эффектов

В development StrictMode эффекты и lifecycle методы вызываются дважды для обнаружения side effects. Это может проявляться как "двойной callback" от Joyride.

---

## Bootstrap 3→5 / react-bootstrap 0.31→2 — паттерны

### Глобальные prop renames

| Было (RB 0.31) | Стало (RB 2) |
|---|---|
| `bsStyle="primary"` | `variant="primary"` |
| `bsSize="small"` / `bsSize="large"` | `size="sm"` / `size="lg"` |
| `animation={false}` (Tabs) | `transition={false}` |
| `<Modal bsSize="large">` | `<Modal size="lg">` |
| `showOverlay`, `disableOverlay` | только `disableOverlay` (showOverlay убран) |

### `ModalWrapper.js` — полная перепись

RB2 изменил `Modal` API. Написан с нуля с поддержкой обоих паттернов (onHide для × кнопки, onCancel только для footer):
```js
// Было: bsSize="large", bsStyle на кнопках, встроенная × кнопка
// Стало: size mapModalSize('large'→'lg'), variant, кастомная × с .close классом (для yeti.css)
```

### `Tabs animation` → `transition`

```jsx
// Было:
<Tabs animation={false} ...>
// Стало:
<Tabs transition={false} ...>
```

### Navigator — NavDropdown, MenuItem

`NavDropdown` и `MenuItem` из RB 0.31 работали в BS3-навбаре через CSS. В RB2 они сломали навбар. Заменены на `NavDropdownCompat` и `NavDropdownItem` из `BootstrapCompat.js`.

### CSS-совместимость (почему yeti.css + BS5)

Bootstrap 5 включён как npm-пакет, но **yeti.css (Bootstrap 3 тема) оставлена** в качестве основного CSS. Это создаёт некоторые конфликты, которые решаются через:
- `src/styles/css/index.css` — project-level overrides
- `src/styles/scss/components/Navigator.scss` — RB2 nav-item/dropdown compat rules

---

## react-router v3→v6 — детали

### Routing setup (`src/routes/Routes.js`)

```jsx
// Было (RR v3):
<Router history={hashHistory}>
  <Route path="/" component={App}>
    <Route path="users" component={Users} />
  </Route>
</Router>

// Стало (RR v6):
<HashRouter>
  <Routes>
    <Route path="/" element={<App />}>
      <Route path="users" element={<AuthUsers />} />
    </Route>
  </Routes>
</HashRouter>
```

В v6 вложенный контент рендерится через `<Outlet />` в App.js (вместо `this.props.children`).

### Authentication HOC

`Authentication(Component)` теперь оборачивает в `AuthenticateWithLocation` (function component), который инжектирует `useLocation()` с `.query` объектом:
```js
function AuthenticateWithLocation(props) {
  const rawLocation = useLocation();
  const query = Object.fromEntries(new URLSearchParams(rawLocation.search));
  return <ConnectedAuthenticate {...props} location={{ ...rawLocation, query }} />;
}
```

### Страница истории навигации в Navigator.js

```js
// Было (RR v3 routes):
const oldpath = prevProps.routes[prevProps.routes.length - 1].path;
const newpath = routes[routes.length - 1].path;

// Стало (через withRouter шим):
const prevPath = prevProps.router?.location?.pathname || '';
const currPath = this.props.router?.location?.pathname || '';
```

---

## @dnd-kit — замена react-sortable-hoc

`react-sortable-hoc` несовместим с React 19. Заменён на `@dnd-kit`.

### `SortableFieldsContainer.js`

```js
// Было: SortableContainer HOC
const SortableFieldsContainer = SortableContainer(({ items }) => <div>{items}</div>);

// Стало: DndContext + SortableContext + useSortable
const SortableFieldsContainer = ({ items, onSortEnd, useDragHandle }) => (
  <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
    <SortableContext items={ids} strategy={verticalListSortingStrategy}>
      {items.map((child, i) => <SortableItem key={...} id={...} child={child} />)}
    </SortableContext>
  </DndContext>
);
```

### `DragHandle.js`

```js
// Было: SortableHandle(DragHandle)
// Стало: Context consumer через DndSortableItemContext
const DragHandle = ({ element, disabled }) => {
  const { attributes, listeners, setActivatorNodeRef } = useContext(DndSortableItemContext);
  return <span ref={setActivatorNodeRef} {...attributes} {...listeners}>{element}</span>;
};
```

Ключевое: `DndSortableItemContext.Provider` в SortableItem передаёт dnd-props вниз. `DragHandle` потребляет контекст без prop drilling.

---

## Redux — изменения

### configureStore.js

`redux-localstorage` (старый пакет) заменён на inline `persistMiddleware`:
- Персистит часть стейта (`entityList`, `guiState.menu`, `settings`)
- При загрузке реконструирует Immutable-структуры через `Immutable.fromJS()`
- Ключ хранилища: `REACT_APP_storageVersion` (сейчас `2.2.0`)

```js
// Ключевые части:
const persistMiddleware = store => next => action => {
  const result = next(action);
  // сериализует + сохраняет в localStorage
  return result;
};
```

### redux-thunk

```js
// Было: import thunkMiddleware from 'redux-thunk'
// Стало: import { thunk as thunkMiddleware } from 'redux-thunk'  ← named export
```

### userReducer.js — совместимость `permissions`/`roles`

Бэкенд может возвращать `action.data.permissions` или `action.data.roles`. Добавлена совместимость:
```js
const roles = Array.isArray(action?.data?.permissions)
  ? action.data.permissions
  : Array.isArray(action?.data?.roles) ? action.data.roles : [];
```

---

## react-select v1→v5

### Ключевые изменения

```js
// v1: onChange получал value напрямую
onChange(value)

// v5: onChange получает option + metadata
onChange(option, { action, removedValue, name })
```

Везде в коде обёртка `onChangeValue` в `Select.js` нормализует это. Вызывающий код не менялся.

Новая prop в v5: `captureMenuScroll={false}` — добавлена везде (иначе scroll внутри dropdown перехватывался).

### SCSS оверрайды (`react-select.scss`)

Добавлены стили для `.entity-state-select` (entity list state filter).

---

## Webpack/Babel — изменения

### `config/webpack.config.js`

```js
// 1. Алиас для react-joyride — форсируем CJS бандл
// (webpack 4 не дружит с ESM .mjs от joyride + React interop)
'react-joyride$': path.resolve(paths.appNodeModules, 'react-joyride/dist/index.js'),

// 2. Обработка .mjs файлов из node_modules
{ test: /\.mjs$/, include: /node_modules/, type: 'javascript/auto' }

// 3. Babel plugins для class properties, optional chaining, nullish coalescing
require.resolve('@babel/plugin-proposal-class-properties'),
require.resolve('@babel/plugin-proposal-optional-chaining'),
require.resolve('@babel/plugin-proposal-nullish-coalescing-operator'),
```

Webpack остался на версии 4 (не обновлялся). Это создаёт сложности с ESM пакетами.

### `.env`

`REACT_APP_serverApiVersion` изменён с `5.27.0` на `5.24.3`.

---

## Другие точечные фиксы по кодовой базе

### `entityActions.js` — FormData guard
```js
// Было: вызов file.name падал если file не Blob
formData.append(`files[${i}]`, file, file.name)

// Стало:
if (file instanceof Blob) {
  formData.append(`files[${i}]`, file, file.name);
}
```

### `entitySelector.js` — убраны бессмысленные createSelector
Тривиальные селекторы вида `createSelector(getX, x => x)` заменены прямыми ссылками на getter:
```js
// Было:
export const itemSelector = createSelector(getItem, item => item);
// Стало:
export const itemSelector = getItem;
```

### `ReduxFormModal.js` / `ReduxConfirmModal.js`
- `defaultProps` → default parameter values
- Defensive guard: `errors` может быть undefined до инициализации формы → `Immutable.Map.isMap(errors) ? errors : Immutable.Map()`

### `reactStylableDiffMock.js`
Новый мок-файл для тестов — заглушка компонента `react-stylable-diff` (пакет несовместим с React 19).

### `react-tagsinput.scss`
Полные базовые стили добавлены в проект (раньше они шли из пакета `react-tagsinput/react-tagsinput.css`, который теперь удалён).

### `Authentication.js`
Добавлен обход проверки доступа для роутов `charging` и `charging_plans`:
```js
const isChargingRoute = pageRoute === 'charging' || pageRoute.startsWith('charging/');
if (isChargingRoute || isChargingPlansRoute) {
  return (<ComposedComponent .../>);
}
```

---

## Тур (BillRun Tour) — полный разбор

### Что такое BillRun Tour

Онбординг-тур, доступный через меню навигатора "Start Tour". Компоненты:
- `Tour` (`OnBoarding.js`) — Joyride + модалки старт/финиш
- `ExampleInvoice` (`ExampleInvoice.js`) — большой overlay-инвойс, показывается пока тур идёт
- `OnBoardingNavigation.js` — ссылка в навигаторе (Start Tour / Resume Tour)
- Redux state: `guiState.onBoarding` с полями `status` (IDLE/STARTING/RUNNING/PAUSED/FINISHED) и `step`

Флоу:
1. Пользователь кликает "Start Tour" → `startOnBoarding()` → `isStarting = true` → показывается вводная модалка
2. Клик "Let's start the tour!" → `runOnBoarding()` → `isRunnig = true` → монтируется `<ExampleInvoice />` + `<Joyride />`
3. Joyride показывает 9 шагов поверх инвойса
4. Клик × — Joyride скрывается, но ExampleInvoice остаётся, появляется beacon
5. Клик на beacon — тур возобновляется
6. Последний шаг → `finishOnBoarding()` → финальная модалка

### Joyride v1 (customer_portal) vs v2 (billrun-1) — API diff

| | v1.11.4 | v2.9.3 |
|---|---|---|
| **Шаги** | `selector`, `text`, `title`, `type: 'click'` | `target`, `content`, `title`, `disableBeacon` |
| **Тип тура** | `<Joyride type="continuous">` | `<Joyride continuous={true}>` |
| **Старт** | `autoStart={true}` prop | `run={true}` prop |
| **Стили** | CSS-классы `.joyride-tooltip__*` встроены | inline styles; `.joyride-tooltip__*` отсутствуют |
| **Кастом tooltip** | нет prop | `tooltipComponent={CustomComponent}` |
| **Beacon цвет** | встроенный CSS | `styles.options.primaryColor` |
| **callback** | `(e)` — плоский объект | `({ action, index, status, type, lifecycle })` |
| **Events** | `'finished'`, `'step:before'`, `'step:after'`, `'error:target_not_found'` | `EVENTS.*` константы (те же строки) |
| **Actions** | `'next'`, `'back'`, `'close'`, `'start'`, `'skip'` | `ACTIONS.*` константы (те же строки) |
| **Status** | нет отдельного поля | `STATUS.FINISHED`, `STATUS.SKIPPED`, `STATUS.RUNNING`, `STATUS.PAUSED` |
| **Controlled mode** | `stepIndex` = начальная позиция | `stepIndex` = полный контроль родителя (BREAKING!) |
| **helpers** | нет | `getHelpers` prop → `{ next, prev, go, close, reset, skip }` |

### Root cause сломанного тура

В Joyride v2 передача `stepIndex` как числа включает **controlled mode** (`controlled = is.number(stepIndex)` в store). Наш `<Joyride stepIndex={startIndex} ...>` всегда попадает в controlled mode, даже когда `startIndex = 0`.

В controlled mode `store.next()` (вызывается при клике Next) делает:

```js
// store.next():
this.setState(this.getNextState({ action: ACTIONS.NEXT, index: index + 1 }));

// getNextState() в controlled mode:
const nextIndex = controlled && !force ? index : Math.min(...);
//                             ↑ controlled=true, force=false → nextIndex = ТЕКУЩИЙ index
return { action: NEXT, index: SAME, lifecycle: SAME };
```

`store.setState` сравнивает новое и старое состояние через `hasUpdatedState`:
```js
hasUpdatedState(oldState) {
  return JSON.stringify(oldState) !== JSON.stringify(this.getState());
}
```

После 1-го клика Next: action START → NEXT (изменилось → listener стрелял → STEP_AFTER → работало).
После 2-го клика Next: action NEXT → NEXT (не изменилось → listener не стреляет → STEP_AFTER не стреляет → тур завис).

### Как чинить

**Главный фикс:** убрать `stepIndex={startIndex}` из `<Joyride>`. В non-controlled mode `store.next()` меняет index → state всегда изменяется → listener всегда стреляет.

Добавить `getHelpers={(h) => { this.joyrideHelpers = h; }}` — для `helpers.go(step)` при resume.

**Новый `joyrideEventHandler` (non-controlled):**

```js
joyrideEventHandler = ({ action, index, status, type }) => {
  if (status === STATUS.FINISHED) {
    this.onFinish();
  } else if (status === STATUS.SKIPPED) {
    this.askCancel();
  } else if (type === EVENTS.STEP_BEFORE && action === ACTIONS.NEXT) {
    // Синхронизируем Redux ПЕРЕД показом шага (как в v1: step:before + next)
    this.onStepChanged(index);
  } else if (type === EVENTS.STEP_AFTER && action === ACTIONS.PREV) {
    this.onStepChanged(Math.max(index - 1, 0));
  } else if (type === EVENTS.TARGET_NOT_FOUND) {
    // Non-controlled: Joyride сам не пропускает шаг при TARGET_NOT_FOUND
    action === ACTIONS.NEXT
      ? this.joyrideHelpers?.next()
      : this.joyrideHelpers?.prev();
  } else if (action === ACTIONS.CLOSE && type === EVENTS.STEP_AFTER) {
    // × нажат: beacon mode, ExampleInvoice остаётся смонтированным
    this.setState({ startIndex: index, beaconEnabled: true, run: false }, () => {
      setTimeout(() => {
        this.setState({ run: true });
        // helpers.go работает только когда status === RUNNING
        // setTimeout(0) даёт Joyride обработать run: true → start() → RUNNING
        setTimeout(() => { this.joyrideHelpers?.go(index); }, 0);
      }, 50);
    });
  } else if (action === ACTIONS.START) {
    const { step } = this.props;
    if (step > 0) {
      // Resume после паузы — прыгаем на сохранённый шаг
      setTimeout(() => { this.joyrideHelpers?.go(step); }, 0);
    }
  }
};
```

**`<Joyride>` render:**
```jsx
<Joyride
  continuous={true}
  scrollToFirstStep={true}
  disableOverlay={false}
  showSkipButton={false}
  tooltipComponent={JoyrideTooltipV1}
  // ❌ УБРАТЬ: stepIndex={startIndex}
  steps={this.getSteps()}
  run={run}
  callback={this.joyrideEventHandler}
  styles={JOYRIDE_STYLES}
  getHelpers={(h) => { this.joyrideHelpers = h; }}  // ← добавить
/>
```

---

## Инвентарь изменённых файлов

### Новые файлы (созданы на ветке)
| Файл | Назначение |
|---|---|
| `src/common/BootstrapCompat.js` | BS3→BS5 шим компоненты |
| `src/common/withRouter.js` | react-router v3→v6 шим |
| `src/components/Filter/BS3Multiselect.js` | Замена jQuery react-bootstrap-multiselect |
| `src/components/Field/types/TagsInput.js` | Замена react-tagsinput |
| `src/components/Elements/DndSortableItemContext.js` | Контекст для @dnd-kit DragHandle |
| `src/styles/scss/overrides/react-joyride.scss` | Joyride v1 визуальные стили |
| `src/test/mocks/reactStylableDiffMock.js` | Мок для React 19-несовместимого пакета |

### Ключевые изменённые файлы
| Файл | Что изменилось |
|---|---|
| `src/index.js` | ReactDOM.render → createRoot |
| `src/configureStore.js` | redux-localstorage → persistMiddleware; thunk named import |
| `src/routes/Routes.js` | RR v3 → RR v6 (HashRouter, Routes, Route, Outlet) |
| `src/components/App/App.js` | Outlet вместо children; статический routeTitles map |
| `src/components/App/Authentication.js` | useLocation inject; charging route bypass |
| `src/components/Elements/ModalWrapper.js` | Полная перепись под RB2 |
| `src/components/Elements/SortableFieldsContainer.js` | react-sortable-hoc → @dnd-kit |
| `src/components/Elements/DragHandle.js` | SortableHandle → DndSortableItemContext |
| `src/components/Field/types/Select.js` | react-select v5 compat; captureMenuScroll |
| `src/components/Navigator/Navigator.js` | RR v6; NavDropdownCompat |
| `src/components/OnBoarding/OnBoarding.js` | ⚠️ Joyride v2 migration — NEEDS FIX |
| `src/reducers/userReducer.js` | permissions/roles compat |
| `src/components/EntityList/State.js` | BS3Multiselect замена |
| `config/webpack.config.js` | .mjs handling; joyride alias; babel plugins |
| `src/styles/css/index.css` | Фикс сломанного CSS-комментария |
| `src/styles/scss/overrides/react-tagsinput.scss` | Базовые стили добавлены в проект |

### Паттерн рефакторинга (~200+ файлов)
Все файлы в `src/components/` затронуты одним или несколькими из следующих изменений:
- `componentWillMount` → `componentDidMount`
- `componentWillReceiveProps` → `componentDidUpdate` (инвертированная логика сравнения!)
- `defaultProps` → default params в функциональных компонентах
- `bsStyle` → `variant`, `bsSize` → `size`
- `import { withRouter } from 'react-router'` → `import withRouter from '@/common/withRouter'`
- `import { Link } from 'react-router'` → `import { Link } from 'react-router-dom'`
- `import { Panel, FormGroup, ... } from 'react-bootstrap'` → `from '@/common/BootstrapCompat'`

---

## Грабли — что пробовали, почему не зашло

### ❌ Joyride controlled mode с STEP_AFTER + NEXT
**Попытка:** оставить `stepIndex={startIndex}` и обновлять `startIndex` в хендлере `STEP_AFTER + NEXT`.
**Проблема:** работает только для 1-го клика Next (action START→NEXT). Начиная со 2-го — store state не меняется (action уже NEXT, не меняется), callback не стреляет.
**Фикс:** убрать `stepIndex` prop целиком.

### ❌ `isTourPaused` в App.js
**Попытка:** показывать `<ExampleInvoice />` при `isRunnig || isPaused`, чтобы инвойс не исчезал при паузе.
**Проблема:** при нажатии Pause в ExampleInvoice → `pauseOnBoarding()` → state=READY → `isTourPaused=false` → ExampleInvoice пропадал. Второй клик Pause → `pendingOnBoarding()` → "Resume Tour" исчезал из навигации.
**Фикс:** вернули оригинал App.js. Новый CLOSE-хендлер вообще не вызывает `pauseOnBoarding()`.

### ❌ `run: false → run: true` без setTimeout
**Попытка:** после × синхронно `setState({ run: false })`, сразу `setState({ run: true })`.
**Проблема:** Joyride в PAUSED не успевает обработать `run: true` без небольшой паузы.
**Фикс:** `setTimeout 50ms` между `false` и `true`.

### ❌ `helpers.go()` синхронно после `run: true`
**Попытка:** вызвать `helpers.go(step)` в том же callback, что и `setState({ run: true })`.
**Проблема:** `helpers.go()` требует `status === RUNNING`, а `run: true` → RUNNING происходит в следующем render-цикле Joyride.
**Фикс:** `setTimeout(0)` после `run: true`.

### ❌ STEP_AFTER стреляет из двух мест
В v2 `STEP_AFTER` может стрелять из двух компонентов при клике × :
1. `StepManager` (via `isControlled`)
2. Основной Joyride (via `isAfterAction && changed("status", PAUSED)`)
Это приводит к двойному вызову хендлера при CLOSE. В non-controlled режиме проявляется слабее, но стоит держать в уме.

### ❌ `Tabs animation` prop
В RB2 `animation` → `transition`. С `animation={false}` работает без ошибок, но анимация всё равно есть.

---

## Ключевые пути

```
# Шим-файлы
front-end/src/common/BootstrapCompat.js
front-end/src/common/withRouter.js

# Joyride v2 source (minified)
front-end/node_modules/react-joyride/dist/index.js
  grep "hasUpdatedState"    → store listener guard (главная причина бага)
  grep "getNextState"       → controlled mode index logic
  grep "isControlled"       → StepManager STEP_AFTER condition
  grep "handleClickPrimary" → что вызывается при клике Next

# v1 (рабочий) Joyride handler — эталон
customer_portal/front-end/src/components/OnBoarding/OnBoarding.js
```

---

## Принципы проекта (от пользователя)

- Решение уровня сениор, без костылей
- Переиспользовать старое вместо создания нового
- Быть критичным, перепроверять самого себя
