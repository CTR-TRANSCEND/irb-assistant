<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SPEC-LLM-001 REQ-LLM-014: Strip userinfo (user:pass@) from base_url before
 * persisting to audit payload. `http://user:pass@evil/` becomes `http://evil/`.
 *
 * @MX:NOTE: audit sanitization helper; idempotent on inputs without userinfo.
 */
final class BaseUrlAuditSanitizer
{
    public static function sanitize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            // Unparseable: return as-is, but still strip an embedded userinfo
            // pattern via regex as defence-in-depth.
            return preg_replace('#^([a-zA-Z][a-zA-Z0-9+\-.]*://)([^/@]*@)#', '$1', $url) ?? $url;
        }

        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        $prefix = $scheme !== '' ? $scheme.'://' : '';

        return $prefix.$host.$port.$path.$query.$fragment;
    }
}
