import { reactive } from 'vue';
import {
  defaultIssuePeriodState,
  resolveIssuePeriodRange,
  type IssuePeriodState,
} from './useInvoicingIssuePeriod';

export type DocumentAdvancedStatus = 'all' | 'draft' | 'issued' | 'paid' | 'cancelled';
export type DocumentAdvancedPaid = 'all' | 'yes' | 'no';
export type DocumentAdvancedDue = 'all' | 'overdue' | 'custom';

export interface DocumentAdvancedFilters {
  status: DocumentAdvancedStatus;
  paid: DocumentAdvancedPaid;
  due: DocumentAdvancedDue;
  dueFrom: string;
  dueTo: string;
  amountMin: string;
  amountMax: string;
  search: string;
}

export function defaultAdvancedFilters(): DocumentAdvancedFilters {
  return {
    status: 'all',
    paid: 'all',
    due: 'all',
    dueFrom: '',
    dueTo: '',
    amountMin: '',
    amountMax: '',
    search: '',
  };
}

export function useInvoicingDocumentListFilters() {
  const issuePeriod = reactive<IssuePeriodState>(defaultIssuePeriodState());
  const advancedApplied = reactive<DocumentAdvancedFilters>(defaultAdvancedFilters());
  const advancedDraft = reactive<DocumentAdvancedFilters>(defaultAdvancedFilters());

  function resetAdvancedDraft() {
    Object.assign(advancedDraft, { ...advancedApplied });
  }

  function applyAdvancedDraft() {
    Object.assign(advancedApplied, { ...advancedDraft });
  }

  function clearAdvanced() {
    Object.assign(advancedApplied, defaultAdvancedFilters());
    Object.assign(advancedDraft, defaultAdvancedFilters());
  }

  function hasActiveAdvanced(): boolean {
    const d = advancedApplied;
    return (
      d.status !== 'all'
      || d.paid !== 'all'
      || d.due !== 'all'
      || d.amountMin !== ''
      || d.amountMax !== ''
      || d.search.trim() !== ''
    );
  }

  function appendListQueryParams(
    params: Record<string, unknown>,
    activeFilter: string
  ): Record<string, unknown> {
    const period = resolveIssuePeriodRange(issuePeriod);
    if (period.from) params.issue_from = period.from;
    if (period.to) params.issue_to = period.to;

    const adv = advancedApplied;
    if (adv.status !== 'all') params.document_status = adv.status;
    if (adv.paid === 'yes') params.paid_filter = 'yes';
    if (adv.paid === 'no') params.paid_filter = 'no';
    if (adv.due === 'overdue') params.due_filter = 'overdue';
    if (adv.due === 'custom') {
      if (adv.dueFrom) params.due_from = adv.dueFrom;
      if (adv.dueTo) params.due_to = adv.dueTo;
    }
    if (adv.amountMin !== '') params.amount_min = adv.amountMin;
    if (adv.amountMax !== '') params.amount_max = adv.amountMax;
    if (adv.search.trim()) params.search = adv.search.trim();

    params.filter = activeFilter;

    return params;
  }

  function resetOnRouteChange() {
    Object.assign(issuePeriod, defaultIssuePeriodState());
    clearAdvanced();
  }

  return {
    issuePeriod,
    advancedApplied,
    advancedDraft,
    resetAdvancedDraft,
    applyAdvancedDraft,
    clearAdvanced,
    hasActiveAdvanced,
    appendListQueryParams,
    resetOnRouteChange,
  };
}
