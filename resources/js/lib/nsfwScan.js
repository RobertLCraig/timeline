/**
 * Client-side NSFW pre-scan.
 *
 * This is a filter that sits IN FRONT of the server-side Sightengine scan in
 * `UploadController`, never a replacement for it. Anything this lets through is
 * still scanned server-side, and anything that goes wrong in here lets the
 * upload proceed rather than locking the user out of a working feature.
 *
 * The threshold is a constant on purpose. The server reads its own threshold
 * from `app_settings` and that one is authoritative; fetching it here would be
 * a second copy of the rule.
 */

export const NSFW_THRESHOLD = 0.7;

const BLOCKED_CLASSES = ['Porn', 'Hentai', 'Sexy'];

/**
 * The name of the first blocked class scoring above the threshold, or null to
 * allow. Pure, so it can be checked without a browser or a model.
 */
export function blockedClass(predictions, threshold = NSFW_THRESHOLD) {
    const hit = (predictions || []).find(
        p => BLOCKED_CLASSES.includes(p.className) && p.probability > threshold
    );
    return hit ? hit.className : null;
}

let modelPromise = null;

/** Loads MobileNetV2 once, lazily. Only this model is bundled — not all three. */
function loadModel() {
    if (!modelPromise) {
        modelPromise = Promise.all([
            import('nsfwjs/core'),
            import('nsfwjs/models/mobilenet_v2'),
        ]).then(([{ load }, { MobileNetV2Model }]) =>
            load('MobileNetV2', { modelDefinitions: [MobileNetV2Model] })
        );
        // A failed load must not be cached, or one bad network moment disables
        // the pre-scan for the rest of the session.
        modelPromise.catch(() => { modelPromise = null; });
    }
    return modelPromise;
}

function decode(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image could not be decoded')); };
        img.src = url;
    });
}

/**
 * Classify a picked file. Returns the blocked class name, or null to allow.
 *
 * Never throws and never rejects: a model that will not load, an image that
 * will not decode, or a browser without WebGL all fall through to the server
 * scan.
 */
export async function scanImageFile(file) {
    try {
        const img = await decode(file);
        const model = await loadModel();
        return blockedClass(await model.classify(img));
    } catch (e) {
        console.warn('NSFW pre-scan unavailable — falling through to the server scan', e);
        return null;
    }
}
