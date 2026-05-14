<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SPEC-LLM-001 REQ-LLM-007/008/009/010 — Hardened base_url validator.
 *
 * Performs scheme allowlist (http/https only), DNS resolution of the host
 * (A + AAAA + gethostbynamel) and rejects every resolved IP that falls inside
 * a blocked CIDR range. Tailscale CGNAT (100.64.0.0/10) is explicitly allowed.
 * Non-decimal IPv4 literals are rejected outright; decimal literals are
 * canonicalised via long2ip(). IPv4-mapped IPv6 (::ffff:x.x.x.x) is unpacked
 * to the embedded IPv4 and re-checked. Performs no outbound TCP — DNS only.
 *
 * Usable as a Laravel ValidationRule:
 *     'base_url' => ['required', 'string', new BaseUrlValidator(), 'max:2048']
 * Or directly as an invokable: returns null on success, error-code string on failure.
 */
final class BaseUrlValidator implements ValidationRule
{
    /**
     * @MX:ANCHOR: SPEC-LLM-001 SSRF security gate; fan_in across every provider
     *             validation path (store, update, test, discover).
     *
     * @MX:REASON: This is the only barrier between admin-supplied URLs and
     *             outbound HTTP traffic. Bypassing it enables SSRF.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = $this($value);
        if ($error !== null) {
            $fail($error);
        }
    }

    /**
     * Invokable form. Returns null on success, error message on failure.
     *
     * @MX:ANCHOR: same security gate as validate(), exposed for direct callers
     *             (controller test() endpoint pre-flight, FormRequest hooks).
     *
     * @MX:REASON: REQ-LLM-021 re-validates stored base_url through this method.
     */
    public function __invoke(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return 'The base URL must be a non-empty string.';
        }

        if (strlen($value) > 2048) {
            return 'The base URL must not exceed 2048 characters.';
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return 'The base URL must be a well-formed URL with a host.';
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return 'The base URL scheme must be http or https.';
        }

        $host = (string) $parts['host'];

        // PHP's parse_url() preserves [..] around IPv6 literals in the host field.
        // Strip them so inet_pton() / filter_var() can consume the bare IP.
        $isBracketIpv6 = strlen($host) >= 2 && $host[0] === '[' && substr($host, -1) === ']';
        if ($isBracketIpv6) {
            $host = substr($host, 1, -1);
        }

        $candidates = [];

        if ($isBracketIpv6) {
            $candidates[] = $host;
        } elseif ($this->looksLikeIpv4Literal($host)) {
            $canonical = $this->canonicaliseIpv4Literal($host);
            if ($canonical === null) {
                return 'The base URL host is a non-decimal or short-form IPv4 literal which is not allowed.';
            }
            $candidates[] = $canonical;
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $candidates[] = $host;
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $candidates[] = $host;
        } else {
            // DNS hostname: resolve A and AAAA via TWO separate calls (REQ-LLM-008).
            $aRecords = @dns_get_record($host, DNS_A);
            if (is_array($aRecords)) {
                foreach ($aRecords as $rec) {
                    if (isset($rec['ip']) && is_string($rec['ip'])) {
                        $candidates[] = $rec['ip'];
                    }
                }
            }

            $aaaaRecords = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaaRecords)) {
                foreach ($aaaaRecords as $rec) {
                    if (isset($rec['ipv6']) && is_string($rec['ipv6'])) {
                        $candidates[] = $rec['ipv6'];
                    }
                }
            }

            $hostByName = @gethostbynamel($host);
            if (is_array($hostByName)) {
                foreach ($hostByName as $ip) {
                    if (is_string($ip)) {
                        $candidates[] = $ip;
                    }
                }
            }

            // Empty resolution → no bad-range hits possible. Per REQ-LLM-008
            // wording ("reject if any resolved IP falls in [bad range]") an
            // empty IP set is not a reject condition. The HTTP layer will fail
            // naturally if the host is genuinely unreachable; this also keeps
            // RFC2606 reserved test fixtures (api.example.com) usable.
        }

        $candidates = array_values(array_unique($candidates));

        // @MX:WARN: REQ-LLM-009 — config-gated relaxation. NEVER enable in production.
        // @MX:REASON: Local-dev convenience; permits 127/8, 0.0.0.0/8, ::1, IPv4-mapped-loopback.
        //             Uses config() not env() so value survives php artisan config:cache (M6).
        $allowLoopback = (bool) config('llm.allow_loopback', false);

        foreach ($candidates as $ip) {
            $err = $this->checkIp($ip, $allowLoopback);
            if ($err !== null) {
                return $err;
            }
        }

        return null;
    }

    private function looksLikeIpv4Literal(string $host): bool
    {
        // Pure decimal integer: 2130706433
        if (preg_match('/^\d+$/', $host) === 1) {
            return true;
        }
        // Hex form: 0x7f000001 or with dots
        if (stripos($host, '0x') === 0 || preg_match('/^0x[0-9a-fA-F]+$/i', $host) === 1) {
            return true;
        }
        // Octal form starting with leading zeros and digits (e.g., 017700000001)
        if (preg_match('/^0\d+$/', $host) === 1) {
            return true;
        }
        // Dotted form variants — three or fewer dots (a / a.b / a.b.c / a.b.c.d)
        if (preg_match('/^[\d]+(?:\.[\d]+){0,3}$/', $host) === 1
            && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host) !== 1) {
            // Has a numeric host with dots but is NOT canonical 4-octet IPv4 → short-form.
            return true;
        }
        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host) === 1) {
            return true;
        }

        return false;
    }

    private function canonicaliseIpv4Literal(string $host): ?string
    {
        // Canonical 4-octet decimal IPv4 (each octet 0-255).
        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host) === 1
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        // Pure decimal integer (e.g., 2130706433 → 127.0.0.1).
        if (preg_match('/^[1-9]\d*$/', $host) === 1 || $host === '0') {
            $intVal = (int) $host;
            if ($intVal < 0 || $intVal > 4294967295) {
                return null;
            }
            $canon = long2ip($intVal);

            return $canon !== false ? $canon : null;
        }

        // Hex (0x...), octal (leading 0), short-form (127.1) → REJECT outright.
        return null;
    }

    /**
     * @return string|null null on success, error message on failure.
     */
    private function checkIp(string $ip, bool $allowLoopback): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return 'Could not parse resolved IP address.';
        }

        // IPv4 path (4 bytes).
        if (strlen($packed) === 4) {
            return $this->checkIpv4Bytes($packed, $allowLoopback);
        }

        // IPv6 path (16 bytes).
        if (strlen($packed) === 16) {
            // Detect IPv4-mapped: ::ffff:x.x.x.x — bytes 0-9 = 0x00, bytes 10-11 = 0xff.
            $isMapped = substr($packed, 0, 10) === str_repeat("\x00", 10)
                && substr($packed, 10, 2) === "\xff\xff";

            if ($isMapped) {
                $embedded = substr($packed, 12, 4);
                // IPv4-mapped-loopback is allowed under loopback override (REQ-LLM-009).
                $err = $this->checkIpv4Bytes($embedded, $allowLoopback);
                if ($err !== null) {
                    return $err;
                }

                return null;
            }

            // Detect IPv4-compatible IPv6 (deprecated ::/96, RFC 4291 §2.5.5.1):
            // bytes 0-11 = 0x00, bytes 12-15 = embedded IPv4. Defensive block — some
            // legacy routing stacks still treat ::a.b.c.d as a.b.c.d. Exclude :: and
            // ::1 (the all-zero address and loopback) which are handled separately.
            $isCompat = substr($packed, 0, 12) === str_repeat("\x00", 12)
                && substr($packed, 12, 4) !== "\x00\x00\x00\x00"   // ::
                && substr($packed, 12, 4) !== "\x00\x00\x00\x01";  // ::1
            if ($isCompat) {
                $embedded = substr($packed, 12, 4);
                $err = $this->checkIpv4Bytes($embedded, $allowLoopback);
                if ($err !== null) {
                    return $err;
                }

                return null;
            }

            return $this->checkIpv6Bytes($packed, $allowLoopback);
        }

        return 'Resolved IP has unexpected byte length.';
    }

    private function checkIpv4Bytes(string $packed, bool $allowLoopback): ?string
    {
        // 0.0.0.0/8 — Linux loopback equivalent.
        if ($this->cidrMatchV4($packed, "\x00\x00\x00\x00", 8)) {
            if ($allowLoopback) {
                return null;
            }

            return 'The base URL resolves to 0.0.0.0/8 which is not allowed.';
        }

        // 127.0.0.0/8 — loopback.
        if ($this->cidrMatchV4($packed, "\x7f\x00\x00\x00", 8)) {
            if ($allowLoopback) {
                return null;
            }

            return 'The base URL resolves to a loopback address which is not allowed.';
        }

        // 100.64.0.0/10 — Tailscale CGNAT. EXPLICITLY ALLOWED (REQ-LLM-008).
        if ($this->cidrMatchV4($packed, "\x64\x40\x00\x00", 10)) {
            return null;
        }

        // 10.0.0.0/8 — RFC1918.
        if ($this->cidrMatchV4($packed, "\x0a\x00\x00\x00", 8)) {
            return 'The base URL resolves to RFC1918 private space (10.0.0.0/8).';
        }

        // 172.16.0.0/12 — RFC1918.
        if ($this->cidrMatchV4($packed, "\xac\x10\x00\x00", 12)) {
            return 'The base URL resolves to RFC1918 private space (172.16.0.0/12).';
        }

        // 192.168.0.0/16 — RFC1918.
        if ($this->cidrMatchV4($packed, "\xc0\xa8\x00\x00", 16)) {
            return 'The base URL resolves to RFC1918 private space (192.168.0.0/16).';
        }

        // 169.254.0.0/16 — link-local (and cloud metadata 169.254.169.254).
        if ($this->cidrMatchV4($packed, "\xa9\xfe\x00\x00", 16)) {
            return 'The base URL resolves to a link-local address (169.254.0.0/16).';
        }

        return null;
    }

    private function checkIpv6Bytes(string $packed, bool $allowLoopback): ?string
    {
        // ::1/128 — loopback.
        if ($packed === str_repeat("\x00", 15)."\x01") {
            if ($allowLoopback) {
                return null;
            }

            return 'The base URL resolves to IPv6 loopback (::1).';
        }

        // fe80::/10 — link-local. First 10 bits = 1111111010.
        if ((ord($packed[0]) === 0xFE) && ((ord($packed[1]) & 0xC0) === 0x80)) {
            return 'The base URL resolves to an IPv6 link-local address (fe80::/10).';
        }

        // fd00::/7 — Unique Local Address (covers fc00::/7 conceptually per SPEC).
        if ((ord($packed[0]) & 0xFE) === 0xFC) {
            return 'The base URL resolves to an IPv6 ULA address (fc00::/7).';
        }

        // 2002::/16 — 6to4 (embeds IPv4).
        if (ord($packed[0]) === 0x20 && ord($packed[1]) === 0x02) {
            return 'The base URL resolves to a 6to4 address (2002::/16).';
        }

        // 2001::/32 — Teredo (embeds IPv4).
        if (ord($packed[0]) === 0x20 && ord($packed[1]) === 0x01
            && ord($packed[2]) === 0x00 && ord($packed[3]) === 0x00) {
            return 'The base URL resolves to a Teredo address (2001::/32).';
        }

        return null;
    }

    private function cidrMatchV4(string $packedIp, string $packedNet, int $prefix): bool
    {
        if (strlen($packedIp) !== 4 || strlen($packedNet) !== 4) {
            return false;
        }

        if ($prefix <= 0) {
            return true;
        }
        if ($prefix > 32) {
            return $packedIp === $packedNet;
        }

        $fullBytes = intdiv($prefix, 8);
        $remBits = $prefix % 8;

        if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedNet, 0, $fullBytes)) {
            return false;
        }

        if ($remBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remBits)) & 0xFF);
        $a = $packedIp[$fullBytes] & $mask;
        $b = $packedNet[$fullBytes] & $mask;

        return $a === $b;
    }
}
