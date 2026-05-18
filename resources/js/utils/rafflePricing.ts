import type { Raffle } from '../store/raffles';

export const RAFFLE_SATS_CURRENCY = 'SATS';

export function normalizeRaffleCurrency(currency: string): string {
    return currency.trim().toUpperCase();
}

export function isRaffleSatsCurrency(currency: string): boolean {
    return normalizeRaffleCurrency(currency) === RAFFLE_SATS_CURRENCY;
}

export function formatRaffleTicketPrice(raffle: Pick<Raffle, 'ticketPrice' | 'ticketPriceSats'> & { ticketCurrency?: string }): string {
    const currency = raffle.ticketCurrency
        ? normalizeRaffleCurrency(raffle.ticketCurrency)
        : raffle.ticketPriceSats != null
          ? RAFFLE_SATS_CURRENCY
          : 'EUR';

    if (isRaffleSatsCurrency(currency)) {
        const sats = raffle.ticketPriceSats ?? Math.round(raffle.ticketPrice);
        return `${sats.toLocaleString()} sats`;
    }
    const amount = Number(raffle.ticketPrice);
    const formatted = Number.isInteger(amount)
        ? amount.toLocaleString()
        : amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
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
        const unit = raffle.ticketPriceSats ?? Math.round(raffle.ticketPrice);
        return `${(sold * unit).toLocaleString()} sats`;
    }
    const total = sold * Number(raffle.ticketPrice);
    const formatted = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${formatted} ${currency}`;
}

export interface RafflePricingForm {
    ticketCurrency: string;
    ticketPrice: number;
}

export function buildRafflePricingPayload(form: RafflePricingForm): Record<string, string | number> {
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
            ticketPrice: raffle.ticketPriceSats ?? Math.round(raffle.ticketPrice),
        };
    }
    return { ticketCurrency: currency, ticketPrice: Number(raffle.ticketPrice) };
}

export function defaultRafflePricingForm(storeDefaultCurrency?: string | null): RafflePricingForm {
    const storeCurrency = normalizeRaffleCurrency(storeDefaultCurrency || 'EUR');
    if (isRaffleSatsCurrency(storeCurrency)) {
        return { ticketCurrency: RAFFLE_SATS_CURRENCY, ticketPrice: 21000 };
    }
    return { ticketCurrency: storeCurrency, ticketPrice: 5 };
}
