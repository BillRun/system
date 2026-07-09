/* react-joyride v2 on React 19 — non-controlled mode (no stepIndex).
   Covers the v1 stall-at-step-2 bug, Back, and × → beacon → reopen.
   Step options mirror OnBoarding.getSteps(). */
import React from 'react';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import Joyride, { EVENTS, STATUS } from 'react-joyride';

global.IS_REACT_ACT_ENVIRONMENT = true;
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

// Mirrors JoyrideTooltipV1's button wiring.
const Tooltip = ({ backProps, closeProps, primaryProps, index, isLastStep, step }) => (
  <div>
    <button data-testid="close" {...closeProps}>×</button>
    {index > 0 && <button data-testid="back" {...backProps}>Back</button>}
    <button data-testid="next" {...primaryProps}>{isLastStep ? 'Last' : 'Next'}</button>
    <span>{step.title}</span>
  </div>
);

const flush = async (ms = 60) => {
  await act(async () => { await new Promise((r) => setTimeout(r, ms)); });
};

test('v2 tour on React 19: advance, back, and × → beacon → reopen', async () => {
  [0, 1, 2, 3].forEach((i) => {
    const d = document.createElement('div');
    d.className = `t${i}`;
    document.body.appendChild(d);
  });
  // matches OnBoarding.getSteps(): beacon only off on step 0, overlay click disabled
  const steps = [0, 1, 2, 3].map((i) => ({
    title: `Step ${i + 1}`,
    content: `content ${i}`,
    target: `.t${i}`,
    disableBeacon: i === 0,
    disableScrolling: true,
    disableOverlayClose: true,
  }));

  const events = [];
  const container = document.createElement('div');
  document.body.appendChild(container);
  const root = createRoot(container);

  await act(async () => {
    root.render(
      <Joyride continuous run scrollToFirstStep={false} disableOverlay
        tooltipComponent={Tooltip} steps={steps} callback={(d) => events.push(d)} />,
    );
  });
  await flush();

  const started = events.some((e) => e.type === EVENTS.TOUR_START && e.status === STATUS.RUNNING);
  const currentStep = () => {
    const t = [...events].reverse().find((e) => e.type === EVENTS.TOOLTIP);
    return t ? t.index : -1;
  };
  const click = async (id) => {
    const btn = document.querySelector(`[data-testid="${id}"]`)
      || document.querySelector('[data-test-id="button-beacon"]');
    if (!btn) return false;
    await act(async () => { btn.dispatchEvent(new MouseEvent('click', { bubbles: true })); });
    await flush();
    return true;
  };

  await click('next');                 // -> step 1
  await click('next');                 // -> step 2 (past the old stall at index 1)
  const maxIndex = currentStep();
  const before = currentStep();
  await click('back');                 // -> step 1
  const afterBack = currentStep();
  await click('next');                 // -> step 2

  // × on step 2: Joyride advances to step 3 and shows a beacon there (v1 behaviour)
  await click('close');
  const beaconEvents = events.filter((e) => e.type === EVENTS.BEACON).map((e) => e.index);
  const beacon = document.querySelector('[data-test-id="button-beacon"]');
  // click the beacon -> tooltip reopens, tour still running
  await act(async () => { beacon.dispatchEvent(new MouseEvent('click', { bubbles: true })); });
  await flush();
  const afterBeacon = currentStep();

  expect(started).toBe(true);
  expect(maxIndex).toBeGreaterThan(1);          // advanced past step 2 (the fix)
  expect(afterBack).toBe(before - 1);           // Back works
  expect(beaconEvents.length).toBeGreaterThan(0); // × leaves a beacon (tour stays alive)
  expect(afterBeacon).toBeGreaterThan(0);       // beacon click reopens a tooltip
});
