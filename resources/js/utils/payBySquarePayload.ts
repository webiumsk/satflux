/**
 * Browser-only PAY by square 1.2.0 single credit-transfer encoder.
 * https://www.sbaonline.sk/wp-content/uploads/2020/03/bysquare-PAYspecifications-1.2.0.pdf
 *
 * The raw LZMA1 stream uses literals only: no match finder or dictionary is
 * necessary for these small bounded payments. It includes an end marker, and
 * uses the standard's lc=3, lp=0, pb=2, dictionary=128 KiB decoder settings.
 * All financial data stays in the browser; this function performs no requests.
 */
export function encodePayBySquare(input: {
    iban: string;
    amount: string;
    currency: 'EUR';
    variableSymbol: string;
    beneficiaryName?: string;
}): string {
    const iban = input.iban.replace(/ /g, '').toUpperCase();
    const name = (input.beneficiaryName ?? '').replace(/[\t\r\n]+/g, ' ').trim();
    if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(iban) || !validIbanChecksum(iban)
        || input.currency !== 'EUR' || !/^\d{1,12}\.\d{2}$/.test(input.amount)
        || !/[1-9]/.test(input.amount) || !/^\d{1,10}$/.test(input.variableSymbol)
        || !name || Array.from(name).length > 70
        || Array.from(name).some((character) => character.charCodeAt(0) < 32 || character.charCodeAt(0) === 127)) {
        throw new Error('Invalid PAY by square payment');
    }

    // InvoiceID, one payment, credit transfer, amount/currency, no scheduled
    // date, VS/KS/SS/reference/note, one account, IBAN/BIC, no standing order or
    // direct debit, then beneficiary name/address extension fields.
    const fields = ['', '1', '1', input.amount, 'EUR', '', input.variableSymbol,
        '', '', '', '', '1', iban, '', '0', '0', name, '', ''];
    const data = new TextEncoder().encode(fields.join('\t'));
    if (data.length > 550) throw new Error('PAY by square payment is too long');
    const checksummed = new Uint8Array(data.length + 4);
    new DataView(checksummed.buffer).setUint32(0, crc32(data), true);
    checksummed.set(data, 4);
    const compressed = literalLzma(checksummed);
    const framed = new Uint8Array(compressed.length + 4);
    // Header: PAY=0, version=0, document=0, reserved=0; decoded byte length.
    new DataView(framed.buffer).setUint16(2, checksummed.length, true);
    framed.set(compressed, 4);
    return base32hex(framed);
}

function validIbanChecksum(iban: string): boolean {
    let remainder = 0;
    for (const char of iban.slice(4) + iban.slice(0, 4)) {
        const value = char >= 'A' ? String(char.charCodeAt(0) - 55) : char;
        for (const digit of value) remainder = (remainder * 10 + Number(digit)) % 97;
    }
    return remainder === 1;
}

function crc32(data: Uint8Array): number {
    let crc = 0xffffffff;
    for (const byte of data) {
        crc ^= byte;
        for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function base32hex(data: Uint8Array): string {
    const alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
    let bits = 0;
    let value = 0;
    let output = '';
    for (const byte of data) {
        value = (value << 8) | byte;
        bits += 8;
        while (bits >= 5) {
            bits -= 5;
            output += alphabet[(value >>> bits) & 31];
        }
    }
    if (bits) output += alphabet[(value << (5 - bits)) & 31];
    return output;
}

function literalLzma(data: Uint8Array): Uint8Array {
    const output: number[] = [];
    let low = 0n;
    let range = 0xffffffff;
    let cache = 0;
    let cacheSize = 1;
    const probabilities = (length: number) => new Uint16Array(length).fill(1024);
    const isMatch = probabilities(16);
    const literals = probabilities(8 * 0x300);

    function shiftLow() {
        const lower = Number(low & 0xffffffffn);
        const carry = Number(low >> 32n);
        if (lower < 0xff000000 || carry !== 0) {
            let pending = cache;
            do {
                output.push((pending + carry) & 255);
                pending = 255;
            } while (--cacheSize > 0);
            cache = lower >>> 24;
        }
        cacheSize++;
        low = BigInt((lower & 0xffffff) * 256);
    }

    function normalize() {
        if (range < 0x1000000) {
            range *= 256;
            shiftLow();
        }
    }

    function bit(model: Uint16Array, index: number, value: number) {
        const probability = model[index];
        const bound = (range >>> 11) * probability;
        if (value === 0) {
            range = bound;
            model[index] += (2048 - probability) >>> 5;
        } else {
            low += BigInt(bound);
            range -= bound;
            model[index] -= probability >>> 5;
        }
        normalize();
    }

    function tree(model: Uint16Array, width: number, value: number) {
        let index = 1;
        for (let shift = width - 1; shift >= 0; shift--) {
            const valueBit = (value >>> shift) & 1;
            bit(model, index, valueBit);
            index = (index << 1) | valueBit;
        }
    }

    let previous = 0;
    for (let position = 0; position < data.length; position++) {
        bit(isMatch, position & 3, 0);
        let index = 1;
        const context = (previous >>> 5) * 0x300;
        const byte = data[position];
        for (let shift = 7; shift >= 0; shift--) {
            const valueBit = (byte >>> shift) & 1;
            bit(literals, context + index, valueBit);
            index = (index << 1) | valueBit;
        }
        previous = byte;
    }

    // EOS is a non-repeated length-2 match at distance 0xffffffff. Literal
    // state remains zero throughout. Encode the fresh length/distance models.
    bit(isMatch, data.length & 3, 1);
    bit(probabilities(1), 0, 0); // isRep
    bit(probabilities(1), 0, 0); // length choice: low
    tree(probabilities(8), 3, 0); // length 2
    tree(probabilities(64), 6, 63); // maximum distance slot
    for (let i = 0; i < 26; i++) {
        range = Math.floor(range / 2);
        low += BigInt(range); // direct bit 1
        normalize();
    }
    tree(probabilities(16), 4, 15); // reverse-tree alignment, all bits 1
    for (let i = 0; i < 5; i++) shiftLow();
    return new Uint8Array(output);
}
