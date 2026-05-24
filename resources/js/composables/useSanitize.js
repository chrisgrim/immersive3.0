import DOMPurify from 'dompurify'

const BLURB_CONFIG = {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto|tel):|[^a-z]|[a-z+.-]+(?:[^a-z+.\-:]|$))/i,
}

const MESSAGE_CONFIG = {
    ALLOWED_TAGS: ['p', 'br'],
    ALLOWED_ATTR: [],
}

DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A' && node.getAttribute('target') === '_blank') {
        node.setAttribute('rel', 'noopener noreferrer')
    }
})

export function sanitizeBlurb(html) {
    if (!html) return ''
    return DOMPurify.sanitize(html, BLURB_CONFIG)
}

export function sanitizeMessage(html) {
    if (!html) return ''
    return DOMPurify.sanitize(html, MESSAGE_CONFIG)
}
