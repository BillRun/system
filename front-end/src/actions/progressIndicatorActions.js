export const PROGRESS_INDICATOR_FINISH = 'PROGRESS_INDICATOR_FINISH';
export const PROGRESS_INDICATOR_START = 'PROGRESS_INDICATOR_START';
export const PROGRESS_INDICATOR_DISMISS = 'PROGRESS_INDICATOR_DISMISS';

export const startProgressIndicator = () => ({
  type: PROGRESS_INDICATOR_START,
});

export const finishProgressIndicator = () => ({
  type: PROGRESS_INDICATOR_FINISH,
});

export const dismissProgressIndicator = () => ({
  type: PROGRESS_INDICATOR_DISMISS,
});
