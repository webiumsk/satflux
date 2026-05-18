import type { Raffle } from '../store/raffles';

export const RAFFLE_SATS_CURRENCY = 'SATS';

export function normalizeRaffleCurrency(currency: string): string {
    return currency.trim().toUpperCase();
}

export function isRaffleSatsCurrency(currency: string): boolean {
    return normalizeRaffleCurrency(currency) === RAFFLE_SATS_CURRENCY;
}

function resolveSatsAmount(ticketPriceSats: number | null | undefined, ticketPrice: number): number {
    if (Number.isFinite(ticketPriceSats)) {
        return Math.round(ticketPriceSats as number);
    }
    if (Number.isFinite(ticketPrice)) {
        return Math.round(ticketPrice);
    }
    return 0;
}

export function formatRaffleTicketPrice(raffle: Pick<Raffle, 'ticketPrice' | 'ticketPriceSats'> & { ticketCurrency?: string }): string {
    const currency = raffle.ticketCurrency
        ? normalizeRaffleCurrency(raffle.ticketCurrency)
        : raffle.ticketPriceSats != null
          ? RAFFLE_SATS_CURRENCY
          : 'EUR';

    if (isRaffleSatsCurrency(currency)) {
        const sats = resolveSatsAmount(raffle.ticketPriceSats, raffle.ticketPrice);
        return `${sats.toLocaleString()} sats`;
    }
    const amount = Number(raffle.ticketPrice);
    const formatted = Number.isFinite(amount)
        ? Number.isInteger(amount)
            ? amount.toLocaleString()
            : amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 })
        : '0';
    return `${formatted} ${currency}`;
}

export function formatRaffleRevenue(
    raffle: Pick<Raffle, 'ticketPrice' | 'ticketPriceSats' | 'ticketsSold'> & { ticketCurrency?: string },
): string {
    const sold = raffle.ticketsSold ?? 0;
    const currency = raffle.ticketCurrency
        ? normalizeRaffleCurrency(raffle.ticketCurrency)
        : raffle.ticketPriceSats != null
          ? RAFFLE_SATS_CURRENCY
          : 'EUR';

    if (sold === 0) {
        return isRaffleSatsCurrency(currency) ? '0 sats' : `0 ${currency}`;
    }
    if (isRaffleSatsCurrency(currency)) {
        const unit = resolveSatsAmount(raffle.ticketPriceSats, raffle.ticketPrice);
        return `${(sold * unit).toLocaleString()} sats`;
    }
    const unit = Number(raffle.ticketPrice);
    const total = Number.isFinite(unit) ? sold * unit : 0;
    const formatted = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${formatted} ${currency}`;
}

export interface RafflePricingForm {
    ticketCurrency: string;
    ticketPrice: number;
}

type RafflePricingSatsPayload = { ticketPriceSats: number; ticketCurrency?: never; ticketPrice?: never };
type RafflePricingFiatPayload = { ticketCurrency: string; ticketPrice: number; ticketPriceSats?: never };

export function buildRafflePricingPayload(form: RafflePricingForm): RafflePricingSatsPayload | RafflePricingFiatPayload {
    const currency = normalizeRaffleCurrency(form.ticketCurrency);
    const price = Number(form.ticketPrice);
    if (isRaffleSatsCurrency(currency)) {
        return { ticketPriceSats: Math.round(price) };
    }
    return { ticketCurrency: currency, ticketPrice: price };
}

export function pricingFromRaffle(raffle: Pick<Raffle, 'ticketCurrency' | 'ticketPrice' | 'ticketPriceSats'>): RafflePricingForm {
    const currency = normalizeRaffleCurrency(raffle.ticketCurrency || RAFFLE_SATS_CURRENCY);
    if (isRaffleSatsCurrency(currency)) {
        return {
            ticketCurrency: RAFFLE_SATS_CURRENCY,
            ticketPrice: resolveSatsAmount(raffle.ticketPriceSats, raffle.ticketPrice),
        };
    }
    return { ticketCurrency: currency, ticketPrice: Number.isFinite(raffle.ticketPrice) ? Number(raffle.ticketPrice) : 0 };
}

export function defaultRafflePricingForm(storeDefaultCurrency?: string | null): RafflePricingForm {
    const storeCurrency = normalizeRaffleCurrency(storeDefaultCurrency || 'EUR');
    if (isRaffleSatsCurrency(storeCurrency)) {
        return { ticketCurrency: RAFFLE_SATS_CURRENCY, ticketPrice: 21000 };
    }
    return { ticketCurrency: storeCurrency, ticketPrice: 5 };
}
