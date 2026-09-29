// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * TinyMCE image auto-compression for Moodle 5.1.
 *
 * Compresses JPEG/PNG/WebP images on the client side before they are
 * uploaded to the Moodle draft area. Covers three scenarios:
 *   1. Image upload dialog (dropzone / file input)
 *   2. Drag & drop images onto the TinyMCE editor body
 *   3. Paste (Ctrl+V) images from the clipboard into the editor
 *
 * Implementation note:
 *   All of the above scenarios ultimately funnel through the
 *   `editor_tiny/uploader` module's `uploadFile()`, which builds a FormData
 *   containing `repo_upload_file` and sends it via XMLHttpRequest to
 *   `repository_ajax.php?action=upload`. Therefore a single, low-level hook
 *   on `XMLHttpRequest.prototype.send` reliably intercepts every image upload
 *   — including the Moodle image dialog's dropzone/file-input path that
 *   bypasses TinyMCE's own `images_upload_handler`.
 *
 *   This avoids any dependency on the async-loaded `window.tinyMCE` global,
 *   so no polling / waiting is required: the interceptor is installed
 *   immediately when this AMD module initialises.
 *
 * @module     local_mention/tiny_image_compress
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable no-console */

/** MIME types we support for compression. */
const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/** MIME types we NEVER touch. */
const SKIP_TYPES = ['image/gif', 'image/svg+xml'];

/**
 * Compress a single image File.
 *
 * Rules:
 *   - Only JPEG, PNG, WebP are processed.
 *   - Width ≤ maxWidth → no resize, but still re-encode (so same-or-bigger is skipped).
 *   - Width > maxWidth → scale proportionally to maxWidth.
 *   - PNG with transparency → drawn on a cleared canvas to preserve the alpha channel.
 *   - GIF / SVG → returned as-is.
 *   - If the compressed blob is ≥ the original → original is used (null is returned).
 *
 * @param {File} file
 * @param {{maxWidth: number, quality: number}} config
 * @returns {Promise<File|null>} Compressed File, or null if compression was skipped or not beneficial.
 */
async function compressImage(file, config) {
    const {maxWidth = 1080, quality = 0.82} = config;
    const mimeType = file.type;

    // --- Skip unsupported types ---
    if (SKIP_TYPES.includes(mimeType)) {
        console.log(
            `[local_mention/tiny_image_compress] Skipped ${mimeType} (unsupported type):`,
            file.name
        );
        return null;
    }

    if (!MIME_TYPES.includes(mimeType)) {
        console.log(
            `[local_mention/tiny_image_compress] Skipped ${mimeType} (not in supported list):`,
            file.name
        );
        return null;
    }

    // --- Log original info ---
    const origSizeKB = (file.size / 1024).toFixed(2);
    console.log(
        `[local_mention/tiny_image_compress] Processing: ${file.name} | ` +
        `type: ${mimeType} | size: ${origSizeKB} KB`
    );

    // --- Decode image ---
    let img;
    try {
        img = await createImageBitmap(file);
    } catch (err) {
        console.warn(
            `[local_mention/tiny_image_compress] createImageBitmap failed for`,
            file.name,
            err
        );
        return null;
    }

    const origW = img.width;
    const origH = img.height;
    console.log(
        `[local_mention/tiny_image_compress] Original dimensions: ${origW} × ${origH}`
    );

    // --- Calculate target size ---
    let targetW = origW;
    let targetH = origH;

    if (origW > maxWidth) {
        const ratio = maxWidth / origW;
        targetW = Math.round(maxWidth);
        targetH = Math.round(origH * ratio);
        console.log(
            `[local_mention/tiny_image_compress] Resizing: ${origW}×${origH} → ${targetW}×${targetH}`
        );
    } else {
        console.log(
            `[local_mention/tiny_image_compress] No resize (${origW} ≤ ${maxWidth}px)`
        );
    }

    // --- Draw onto canvas ---
    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');

    if (mimeType === 'image/png') {
        // Clear to transparent so PNG alpha is preserved.
        ctx.clearRect(0, 0, targetW, targetH);
    }

    ctx.drawImage(img, 0, 0, targetW, targetH);
    img.close(); // free GPU / system memory

    // --- Encode ---
    const outputType = mimeType; // keep original format
    // PNG ignores the quality parameter in toBlob(), but we pass it anyway for consistency.
    const outputQuality = outputType === 'image/png' ? undefined : quality;

    const blob = await new Promise((resolve) => {
        canvas.toBlob(
            (b) => resolve(b),
            outputType,
            outputQuality
        );
    });

    if (!blob) {
        console.warn(
            `[local_mention/tiny_image_compress] canvas.toBlob() returned null for`,
            file.name
        );
        return null;
    }

    const newSizeKB = (blob.size / 1024).toFixed(2);
    console.log(
        `[local_mention/tiny_image_compress] Compressed: ${targetW}×${targetH} | ${newSizeKB} KB`
    );

    // --- Compare sizes ---
    if (blob.size >= file.size) {
        console.log(
            `[local_mention/tiny_image_compress] Skipped (compressed ${newSizeKB} KB ≥ original ${origSizeKB} KB)`
        );
        return null;
    }

    const reduction = ((1 - blob.size / file.size) * 100).toFixed(1);
    console.log(
        `[local_mention/tiny_image_compress] Success: reduced by ${reduction}% ` +
        `(${origSizeKB} KB → ${newSizeKB} KB)`
    );

    // Keep the original filename; the extension stays the same because we keep the same MIME type.
    return new File([blob], file.name, {type: outputType});
}

/**
 * Guards against installing the XHR wrapper more than once.
 * @type {boolean}
 */
let xhrPatched = false;

/**
 * Install a wrapper around XMLHttpRequest.prototype.send to intercept image
 * uploads that reach the repository/ajax.php endpoint.
 *
 * This single, low-level hook covers all image upload paths:
 *   - TinyMCE `images_upload_handler` (drag/drop onto editor, paste)
 *   - Moodle image dialog dropzone & file input
 *
 * Only requests matching these criteria are intercepted:
 *   - FormData body containing a 'repo_upload_file' entry
 *   - The file's MIME type is in our supported list
 *
 * @param {{maxWidth: number, quality: number}} config
 */
function setupXHRInterceptor(config) {
    if (xhrPatched) {
        return;
    }
    xhrPatched = true;

    const originalSend = XMLHttpRequest.prototype.send;

    // Re-entrancy guard: while we are about to re-invoke send() after
    // compression, we must NOT recursively try to intercept the same request.
    let patching = false;

    XMLHttpRequest.prototype.send = function (body) {
        // Fast bail-out for non-FormData or already-patching calls.
        if (patching || !(body instanceof FormData) || !body.has('repo_upload_file')) {
            return originalSend.call(this, body);
        }

        const file = body.get('repo_upload_file');
        if (!(file instanceof Blob) || !file.type || !MIME_TYPES.includes(file.type)) {
            return originalSend.call(this, body);
        }

        // --- This looks like an image upload we want to compress ---
        const xhr = this;

        // Log the target for debugging.
        console.log(
            `[local_mention/tiny_image_compress] XHR intercepted: ${file.name} ` +
            `(${(file.size / 1024).toFixed(2)} KB)`
        );

        // Compress, then replace the file in the FormData, then send.
        compressImage(file, config).then((compressed) => {
            if (compressed) {
                // Replace the file entry in the FormData.
                body.delete('repo_upload_file');
                body.append('repo_upload_file', compressed, compressed.name);
            }

            // Send with the (possibly modified) body.
            patching = true;
            try {
                originalSend.call(xhr, body);
            } finally {
                patching = false;
            }
        });
        // IMPORTANT: we do NOT call originalSend here – the upload is deferred
        // until after compression completes. The XHR has already been opened
        // (xhr.open() was called) and event listeners have been attached by
        // uploadFile(), so delaying send() is safe.
    };
}

/**
 * AMD entry point.
 *
 * Called by PHP via:
 *   $PAGE->requires->js_call_amd('local_mention/tiny_image_compress', 'init', [maxWidth, quality]);
 *
 * @param {number} [maxWidth=1080]  Maximum image width in pixels.
 * @param {number} [quality=0.82]   JPEG/WebP compression quality (0–1).
 */
export const init = (maxWidth = 1080, quality = 0.82) => {
    const config = {
        maxWidth: Math.max(1, Math.round(parseInt(maxWidth, 10) || 1080)),
        quality: Math.min(1, Math.max(0, parseFloat(quality) || 0.82)),
    };

    // Install the interceptor immediately. It does not depend on the async
    // tinymce global, so no polling is required and no console spam occurs.
    setupXHRInterceptor(config);

    console.log(
        `[local_mention/tiny_image_compress] Initialised (maxWidth=${config.maxWidth}, quality=${config.quality})`
    );
};