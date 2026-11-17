import countryLengths from '../../data/iban-lengths.json';

/** SWIFT registered country length precedes checksum validation; no account existence is inferred. */
export function normalizePaymentIban(value: string): string | null {
    if (/[^A-Za-z0-9 ]/.test(value)) return null;
    const iban = value.replace(/ /g, '').toUpperCase();
    const country = iban.slice(0, 2) as keyof typeof countryLengths;
    if (!/^[A-Z]{2}\d{2}/.test(iban) || countryLengths[country] !== iban.length) return null;
    let remainder = 0;
    for (const char of iban.slice(4) + iban.slice(0, 4)) {
        for (const digit of char >= 'A' ? String(char.charCodeAt(0) - 55) : char) {
            remainder = (remainder * 10 + Number(digit)) % 97;
        }
    }
    return remainder === 1 ? iban : null;
}
