export type IssuePeriodPreset =
  | 'all'
  | 'today'
  | 'yesterday'
  | 'this_month'
  | 'last_month'
  | 'this_quarter'
  | 'last_quarter'
  | 'this_year'
  | 'last_year'
  | 'custom';

export interface IssuePeriodState {
  preset: IssuePeriodPreset;
  customFrom: string;
  customTo: string;
}

export function defaultIssuePeriodState(): IssuePeriodState {
  return {
    preset: 'this_year',
    customFrom: '',
    customTo: '',
  };
}

function pad(n: number) {
  return String(n).padStart(2, '0');
}

export function toIsoDate(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function startOfDay(d: Date) {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function startOfMonth(d: Date) {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function endOfMonth(d: Date) {
  return new Date(d.getFullYear(), d.getMonth() + 1, 0);
}

function startOfQuarter(d: Date) {
  const q = Math.floor(d.getMonth() / 3);
  return new Date(d.getFullYear(), q * 3, 1);
}

function endOfQuarter(d: Date) {
  const start = startOfQuarter(d);
  return new Date(start.getFullYear(), start.getMonth() + 3, 0);
}

function startOfYear(d: Date) {
  return new Date(d.getFullYear(), 0, 1);
}

function endOfYear(d: Date) {
  return new Date(d.getFullYear(), 11, 31);
}

export function resolveIssuePeriodRange(state: IssuePeriodState): { from?: string; to?: string } {
  const now = startOfDay(new Date());

  if (state.preset === 'all') {
    return {};
  }

  if (state.preset === 'custom') {
    const from = state.customFrom || undefined;
    const to = state.customTo || undefined;
    return { from, to };
  }

  if (state.preset === 'today') {
    const iso = toIsoDate(now);
    return { from: iso, to: iso };
  }

  if (state.preset === 'yesterday') {
    const y = new Date(now);
    y.setDate(y.getDate() - 1);
    const iso = toIsoDate(y);
    return { from: iso, to: iso };
  }

  if (state.preset === 'this_month') {
    return { from: toIsoDate(startOfMonth(now)), to: toIsoDate(endOfMonth(now)) };
  }

  if (state.preset === 'last_month') {
    const m = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    return { from: toIsoDate(startOfMonth(m)), to: toIsoDate(endOfMonth(m)) };
  }

  if (state.preset === 'this_quarter') {
    return { from: toIsoDate(startOfQuarter(now)), to: toIsoDate(endOfQuarter(now)) };
  }

  if (state.preset === 'last_quarter') {
    const qStart = startOfQuarter(now);
    const prev = new Date(qStart);
    prev.setMonth(prev.getMonth() - 3);
    return { from: toIsoDate(startOfQuarter(prev)), to: toIsoDate(endOfQuarter(prev)) };
  }

  if (state.preset === 'this_year') {
    return { from: toIsoDate(startOfYear(now)), to: toIsoDate(endOfYear(now)) };
  }

  if (state.preset === 'last_year') {
    const y = new Date(now.getFullYear() - 1, 0, 1);
    return { from: toIsoDate(startOfYear(y)), to: toIsoDate(endOfYear(y)) };
  }

  return {};
}

export function initCustomRangeFromPreset(state: IssuePeriodState): IssuePeriodState {
  if (state.preset === 'custom') {
    return state;
  }
  const range = resolveIssuePeriodRange(state);
  return {
    preset: 'custom',
    customFrom: range.from ?? '',
    customTo: range.to ?? '',
  };
}
