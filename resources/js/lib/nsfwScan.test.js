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

test('allows the upload when the scan cannot run at all', async () => {
    // No DOM here, so decode() fails the way a broken model load would.
    assert.equal(await scanImageFile(null), null);
});
