const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const test = require('node:test');
const QRCode = require('qrcode');
const { PNG } = require('pngjs');

const script = path.resolve(__dirname, '../render-payment-qr.cjs');
const render = (input) => spawnSync(process.execPath, [script], {
    input, encoding: 'utf8', timeout: 5000, maxBuffer: 1024 * 1024,
});

test('stdin capability produces a local PNG encoding the exact link and a quiet border', () => {
    const data = `https://panel.test/pay/e/${'x'.repeat(64)}`;
    const result = render(JSON.stringify({ data, size: 180 }));
    assert.equal(result.status, 0);
    assert.equal(result.stderr, '');
    assert.ok(result.stdout.startsWith('data:image/png;base64,'));
    const png = PNG.sync.read(Buffer.from(result.stdout.split(',')[1], 'base64'));
    assert.equal(png.width, 180);
    assert.equal(png.height, 180);
    assert.equal(png.data[0], 255);
    const matrix = QRCode.create(data, { errorCorrectionLevel: 'M' }).modules;
    const scale = png.width / (matrix.size + 8);
    for (let y = 0; y < matrix.size; y += 1) {
        for (let x = 0; x < matrix.size; x += 1) {
            const px = Math.floor((x + 4.5) * scale);
            const py = Math.floor((y + 4.5) * scale);
            assert.equal(png.data[(py * png.width + px) * 4] === 0, Boolean(matrix.get(y, x)));
        }
    }
});

test('malformed input fails without echoing payment data to stdout or stderr', () => {
    const result = render('private-capability-not-json');
    assert.equal(result.status, 1);
    assert.equal(result.stdout, '');
    assert.equal(result.stderr, '');
});

test('oversized payloads and invalid dimensions are rejected without image output', () => {
    for (const input of [
        JSON.stringify({ data: 'x'.repeat(4097), size: 180 }),
        'x'.repeat(32769),
        JSON.stringify({ data: 'test', size: 0 }),
        JSON.stringify({ data: 'test', size: 513 }),
        JSON.stringify({ data: 'test', size: '180' }),
    ]) {
        const result = render(input);
        assert.equal(result.status, 1);
        assert.equal(result.stdout, '');
        assert.equal(result.stderr, '');
    }
});
