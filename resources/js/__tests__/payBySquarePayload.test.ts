import { execFileSync } from 'node:child_process';
import { crc32 } from 'node:zlib';
import { describe, expect, it } from 'vitest';
import { encodePayBySquare } from '@/utils/payBySquarePayload';

const payment = {
    iban: 'SK6807200002891987426353', // Published NBS example, never a real test payment.
    amount: '200.30',
    currency: 'EUR' as const,
    variableSymbol: '0000123456',
    beneficiaryName: 'Example supplier s.r.o.',
};

// Independent decoder: xz-utils is already part of the Ubuntu CI runner and
// existing PHP PAY by square runtime. No network, wallet, bank or API calls.
function decode(payload: string) {
    const bits = Array.from(payload, (char) => '0123456789ABCDEFGHIJKLMNOPQRSTUV'
        .indexOf(char).toString(2).padStart(5, '0')).join('');
    const bytes = Buffer.from(Array.from({ length: Math.floor(bits.length / 8) },
        (_, index) => Number.parseInt(bits.slice(index * 8, index * 8 + 8), 2)));
    expect(bytes.subarray(0, 2)).toEqual(Buffer.from([0, 0]));
    const decoded = execFileSync('xz', ['--decompress', '--format=raw',
        '--lzma1=lc=3,lp=0,pb=2,dict=128KiB', '--stdout'], { input: bytes.subarray(4), timeout: 2000, maxBuffer: 4096 });
    expect(decoded.length).toBe(bytes.readUInt16LE(2));
    expect(decoded.readUInt32LE(0)).toBe(crc32(decoded.subarray(4)));
    return decoded.subarray(4).toString('utf8').split('\t');
}

describe('local PAY by square payload', () => {
    it('round-trips through the existing independent LZMA decoder and preserves recipient, cents and VS zeros', () => {
        const payload = encodePayBySquare(payment);
        expect(payload).toBe('0005Q0003UHGOR5H3S2CNO46AM8MOR28R2VNUTTRFQ6GPI0D260AII6PNISQHVJNRL6EB5MKK5L0M7FUJKBO5CGULASA51K6AVVV253FK8L8O1NNI6LK1R4MFVKKMULLCLS44QK9QPKLPVL8KN0CC6MTHFVVURR80000');
        expect(payload).toMatch(/^[0-9A-V]+$/);
        expect(decode(payload)).toEqual(['', '1', '1', '200.30', 'EUR', '', '0000123456',
            '', '', '', '', '1', 'SK6807200002891987426353', '', '0', '0',
            'Example supplier s.r.o.', '', '']);
    });

    it('encodes UTF-8 supplier names without adding fields or scheduling a future transfer', () => {
        const fields = decode(encodePayBySquare({ ...payment, amount: '0.01', beneficiaryName: 'Žltý dodávateľ\nTest' }));
        expect(fields[3]).toBe('0.01');
        expect(fields[5]).toBe('');
        expect(fields[16]).toBe('Žltý dodávateľ Test');
        expect(fields).toHaveLength(19);
    });

    it('normalizes the printed IBAN without changing the account', () => {
        expect(encodePayBySquare({ ...payment, iban: 'sk68 0720 0002 8919 8742 6353' }))
            .toBe(encodePayBySquare(payment));
    });

    it.each([
        { amount: '0.00' }, { amount: '-1.00' }, { amount: '1e3' }, { amount: '1.001' },
        { amount: '1000000000000.00' }, { currency: 'CZK' as 'EUR' },
        { iban: 'SK6807200002891987426354' }, { iban: 'not-an-iban' }, { iban: 'SK7700000000000' },
        { variableSymbol: '' }, { variableSymbol: '12345678901' }, { variableSymbol: 'INV-123' },
        { beneficiaryName: '' }, { beneficiaryName: undefined },
        { beneficiaryName: 'A'.repeat(71) }, { beneficiaryName: 'Supplier\u0000Other' },
    ])('rejects malformed or incomplete payment terms: %o', (invalid) => {
        expect(() => encodePayBySquare({ ...payment, ...invalid })).toThrow('Invalid PAY by square payment');
    });

    it('decodes the maximum supported amount, name and VS with matching CRC', () => {
        const fields = decode(encodePayBySquare({ ...payment, amount: '999999999999.99', beneficiaryName: 'Ž'.repeat(70) }));
        expect(fields[3]).toBe('999999999999.99');
        expect(fields[16]).toBe('Ž'.repeat(70));
    });
});
