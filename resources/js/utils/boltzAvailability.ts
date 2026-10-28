import type { Store } from '../store/stores';

/** Re-enable only after Satflux merchant receiving has been qualified end to end. */
export const BOLTZ_RECEIVING_AVAILABLE = false;

/** Older API responses still identify the affected provider through wallet_type. */
export function hasBoltzServiceAlert(store: Pick<Store, 'wallet_type' | 'boltz_service_alert'>): boolean {
    return store.boltz_service_alert?.active ?? store.wallet_type === 'aqua_boltz';
}
