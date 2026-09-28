// Run with: npm run test:js   (node's built-in runner, no framework)
import test from 'node:test';
import assert from 'node:assert/strict';

import { blockedClass, scanImageFile, NSFW_THRESHOLD } from './nsfwScan.js';

test('blocks a picture scoring over the threshold', () => {
    const predictions = [
        { className: 'Neutral', probability: 0.2 },
        { className: 'Porn', probability: 0.91 },
    ];
    assert.equal(blockedClass(predictions), 'Porn');
});

test('allows a picture scoring under the threshold', () => {
    const predictions = [
        { className: 'Neutral', probability: 0.6 },
        { className: 'Sexy', probability: NSFW_THRESHOLD - 0.01 },
    ];
    assert.equal(blockedClass(predictions), null);
});

test('ignores classes that are not blocked, however high they score', () => {
    assert.equal(blockedClass([{ className: 'Drawing', probability: 0.99 }]), null);
});

test('allows the upload when the model fails to load', async () => {
    let loads = 0;
    const load = () => { loads++; return Promise.reject(new Error('weights 404')); };

    assert.equal(await scanImageFile(null, { load }), null);
    assert.equal(loads, 1, 'the model load was never attempted, so its failure was not what was caught');
});

test('allows the upload when the model load stalls', { timeout: 2000 }, async () => {
    let loads = 0;
    const load = () => { loads++; return new Promise(() => {}); };

    assert.equal(await scanImageFile(null, { load, timeoutMs: 50 }), null);
    assert.equal(loads, 1, 'the model load was never attempted, so its stall was not what was caught');
});

test('skips the scan and never fetches the model when checks are off', async () => {
    let loads = 0;
    const load = () => { loads++; return Promise.reject(new Error('should not load')); };

    assert.equal(await scanImageFile(null, { enabled: false, load }), null);
    assert.equal(loads, 0);
});
