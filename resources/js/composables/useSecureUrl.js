/**
 * Composable for secure URL handling
 * Determines if a URL is internal or external and provides appropriate security attributes
 */
export function useSecureUrl(url) {
    if (!url) {
        return {
            isInternal: true,
            target: null,
            rel: null
        }
    }

    // Handle relative URLs (always internal)
    if (url.startsWith('/')) {
        return {
            isInternal: true,
            target: null,
            rel: null
        }
    }

    // Get the current domain
    const currentDomain = window.location.hostname
    
    try {
        const urlObj = new URL(url, window.location.origin)
        const urlDomain = urlObj.hostname
        
        // Check if it's the same domain or subdomain
        const isInternal = urlDomain === currentDomain || 
                          urlDomain.endsWith(`.${currentDomain}`) ||
                          currentDomain.endsWith(`.${urlDomain}`)
        
        return {
            isInternal,
            target: isInternal ? null : '_blank',
            rel: isInternal ? null : 'noopener noreferrer nofollow'
        }
    } catch (e) {
        // If URL parsing fails, treat as internal for safety
        console.warn('Invalid URL:', url)
        return {
            isInternal: true,
            target: null,
            rel: null
        }
    }
}

