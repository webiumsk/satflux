import { describe, expect, it } from 'vitest';
import { normalizePaymentIban } from '@/utils/paymentIban';

/** Independent whole-number reference for synthetic checksums; no bank calls. */
function withChecksum(country: string, account: string): string {
    const suffix = [...country].map(char => String(char.charCodeAt(0) - 55)).join('') + '00';
    const check = 98n - BigInt(account + suffix) % 97n;
    return country + String(check).padStart(2, '0') + account;
}

describe('shared registered supplier IBAN validation', () => {
    it.each(['SK6807200002891987426353', 'CZ6508000000192000145399', 'DE89370400440532013000', 'GB82WEST12345698765432'])(
        'accepts a standard published example with its registered country length: %s', iban => {
            expect(normalizePaymentIban(iban)).toBe(iban);
        });

    it('normalizes printed ASCII spaces and case without changing the account', () => {
        expect(normalizePaymentIban('sk68 0720 0002 8919 8742 6353')).toBe('SK6807200002891987426353');
    });

    it('rejects the checksum-valid short Slovak review example', () => {
        const short = withChecksum('SK', '0'.repeat(11));
        expect(short).toBe('SK77' + '0'.repeat(11));
        expect(normalizePaymentIban(short)).toBeNull();
    });

    it.each([['SK', 19], ['SK', 21], ['DE', 19], ['GB', 17], ['ZZ', 20], ['AA', 20]])(
        'rejects unknown countries or incorrect lengths even with a valid checksum: %s/%i', (country, length) => {
            expect(normalizePaymentIban(withChecksum(String(country), '0'.repeat(Number(length))))).toBeNull();
        });

    it('rejects a newline replacing the final zero of an otherwise checksum-valid account', () => {
        const iban = withChecksum('DE', '0'.repeat(18));
        expect(normalizePaymentIban(iban)).toBe(iban);
        expect(normalizePaymentIban(iban.slice(0, -1) + '\n')).toBeNull();
    });

    it.each(['', 'SK6807200002891987426354', 'SK68\t07200002891987426353', 'SK6807200002891987426353\n', 'ＳＫ6807200002891987426353', 'ſk6807200002891987426353'])(
        'rejects malformed data without normalizing away controls: %s', value => {
            expect(normalizePaymentIban(value)).toBeNull();
        });
});
