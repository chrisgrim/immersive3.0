// A file's image type. Some browsers and operating systems leave File.type blank,
// or call it application/octet-stream, for formats they don't know yet (AVIF
// most often), even though the browser can
// show the image and the server reads it fine. Fall back to the extension then,
// so a real photo isn't turned away as "not a supported image type".
const BY_EXTENSION = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    avif: 'image/avif',
    gif: 'image/gif',
    svg: 'image/svg+xml',
};

export function imageType(file) {
    const type = file?.type || '';
    if (type && type !== 'application/octet-stream') return type;
    const ext = (file?.name || '').split('.').pop().toLowerCase();
    return BY_EXTENSION[ext] || type;
}
