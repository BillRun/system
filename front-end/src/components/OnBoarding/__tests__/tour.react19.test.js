/* Empirical check: react-joyride v2 actually runs on React 19 AND advances past step 2
   in NON-controlled mode (the fix). Renders the REAL installed <Joyride> in jsdom. */
import React from 'react';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import Joyride, { EVENTS, STATUS } from 'react-joyride';

global.IS_REACT_ACT_ENVIRONMENT = true;
// Popper.js v1 / Floater need these in jsdom:
global.requestAnimationFrame = (cb) => setTimeout(cb, 0);
global.cancelAnimationFrame = (id) => clearTimeout(id);
window.scrollTo = () => {};
document.createRange = () => ({
  setStart: () => {},
  setEnd: () => {},
  getBoundingClientRect: () => ({ top: 0, bottom: 0, left: 0, right: 0, width: 0, height: 0 }),
  getClientRects: () => [],
  commonAncestorContainer: document.documentElement,
});

const Tooltip = ({ primaryProps, step }) => (
  <button data-testid="next" {...primaryProps}>{step.title}</button>
);

const flush = async (ms = 60) => {
  await act(async () => { await new Promise((r) => setTimeout(r, ms)); });
};

test('v2 Joyride runs on React 19 and advances past step 2 (non-controlled)', async () => {
  // mount 4 targets
  [0, 1, 2, 3].forEach((i) => {
    const d = document.createElement('div');
    d.className = `t${i}`;
    document.body.appendChild(d);
  });
  const steps = [0, 1, 2, 3].map((i) => ({
    title: `Step ${i + 1}`,
    content: `content ${i}`,
    target: `.t${i}`,
    disableBeacon: true,
    disableScrolling: true,
  }));

  const events = [];
  const container = document.createElement('div');
  document.body.appendChild(container);
  const root = createRoot(container);

  await act(async () => {
    root.render(
      <Joyride
        continuous
        run
        scrollToFirstStep={false}
        disableOverlay
        tooltipComponent={Tooltip}
        steps={steps}
        callback={(d) => events.push(d)}
      />,
    );
  });
  await flush();

  // (A) COMPAT: Joyride mounted and started on React 19 without hitting a removed API
  const started = events.some((e) => e.type === EVENTS.TOUR_START && e.status === STATUS.RUNNING);
  expect(started).toBe(true);

  // (B) ADVANCE: click "Next" up to 3 times, record the furthest index seen
  let maxIndex = 0;
  for (let click = 0; click < 3; click += 1) {
    events.forEach((e) => { if (typeof e.index === 'number' && e.index > maxIndex) maxIndex = e.index; });
    const btn = container.querySelector('[data-testid="next"]')
      || document.querySelector('[data-testid="next"]');
    if (!btn) break;
    await act(async () => { btn.dispatchEvent(new MouseEvent('click', { bubbles: true })); });
    await flush();
  }
  events.forEach((e) => { if (typeof e.index === 'number' && e.index > maxIndex) maxIndex = e.index; });

  // eslint-disable-next-line no-console
  console.log('TOUR EMPIRICAL >> started:', started, '| maxIndex reached:', maxIndex,
    '| tooltip rendered:', !!document.querySelector('[data-testid="next"]'),
    '| event types:', Array.from(new Set(events.map((e) => e.type))).join(','));

  // the original bug stalled at ~step 2 (index 1). Passing index 1 proves the fix.
  expect(maxIndex).toBeGreaterThan(1);
});
