// Keep payment capabilities on this server: input is stdin, never argv or an external QR service.
const fs = require('node:fs');
const QRCode = require('qrcode');

async function main() {
    const input = Buffer.alloc(32769);
    let length = 0;
    while (length < input.length) {
        const count = fs.readSync(0, input, length, input.length - length, null);
        if (count === 0) break;
        length += count;
    }
    if (length > 32768) throw new Error('Invalid input.');
    const payload = JSON.parse(input.subarray(0, length).toString('utf8'));
    if (!payload || typeof payload.data !== 'string' || !payload.data
        || Buffer.byteLength(payload.data, 'utf8') > 4096
        || !Number.isInteger(payload.size) || payload.size < 64 || payload.size > 512) {
        throw new Error('Invalid input.');
    }
    const image = await QRCode.toDataURL(payload.data, {
        type: 'image/png', width: payload.size, margin: 4, errorCorrectionLevel: 'M',
    });
    process.stdout.write(image);
}

main().catch(() => {
    // Do not include the bearer link or library exception in diagnostics.
    process.exitCode = 1;
});
