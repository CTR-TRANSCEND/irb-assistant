<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BaseUrlValidator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-LLM-001 — BaseUrlValidator unit coverage.
 *
 * Covers acceptance scenarios:
 *   S1, S2, S5, S6, S6b, S7 (length cap), S11 (5 schemes),
 *   S12 (no outbound TCP during validation), S17, S18, S19 (4 numeric forms).
 *
 * The validator MUST perform DNS only — it must NOT issue any outbound HTTP.
 * For tests that target hostnames not under our control, we use IP literals or
 * RFC1918 hosts that short-circuit before DNS, OR rely on the validator's
 * own fail-fast paths (scheme, length, numeric literal).
 *
 * Traceability:
 *   REQ-LLM-007, REQ-LLM-008, REQ-LLM-009, REQ-LLM-010, REQ-LLM-012
 */
final class BaseUrlValidatorTest extends TestCase
{
    private BaseUrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BaseUrlValidator;
        // M6: loopback gate now reads config('llm.allow_loopback'), not env().
        // Default: flag OFF for every test except those that opt-in.
        config(['llm.allow_loopback' => false]);
    }

    protected function tearDown(): void
    {
        config(['llm.allow_loopback' => false]);
        parent::tearDown();
    }

    private function setLoopbackFlag(bool $value): void
    {
        // M6: set via config() layer instead of putenv() so config:cache does not break this.
        config(['llm.allow_loopback' => $value]);
    }

    private function assertRejected(string $url, ?string $messageContains = null): void
    {
        $err = ($this->validator)($url);
        $this->assertNotNull($err, "Expected URL to be rejected: {$url}");
        if ($messageContains !== null) {
            $this->assertStringContainsString($messageContains, $err);
        }
    }

    private function assertAccepted(string $url): void
    {
        $err = ($this->validator)($url);
        $this->assertNull($err, 'Expected URL to be accepted but got error: '.(string) $err."  url={$url}");
    }

    // ─── S1: RFC1918 192.168.x.x rejected (REQ-LLM-008) ────────────────────────

    #[Test]
    public function s1_rfc1918_192_168_rejected(): void
    {
        $this->assertRejected('http://192.168.1.5:11434', '192.168');
    }

    #[Test]
    public function s1b_rfc1918_10_rejected(): void
    {
        $this->assertRejected('http://10.0.0.5/', '10.0.0.0/8');
    }

    #[Test]
    public function s1c_rfc1918_172_16_rejected(): void
    {
        $this->assertRejected('http://172.20.0.5/', '172.16.0.0/12');
    }

    // ─── S2: Cloud metadata 169.254.169.254 rejected (REQ-LLM-008) ──────────────

    #[Test]
    public function s2_cloud_metadata_169_254_169_254_rejected(): void
    {
        $this->assertRejected('http://169.254.169.254/latest/meta-data', '169.254');
    }

    // ─── S5: Loopback rejected by default (REQ-LLM-009) ─────────────────────────

    #[Test]
    public function s5_loopback_127_0_0_1_default_rejected(): void
    {
        $this->assertRejected('http://127.0.0.1:11434', 'loopback');
    }

    #[Test]
    public function s5b_ipv6_loopback_default_rejected(): void
    {
        $this->assertRejected('http://[::1]/', 'IPv6 loopback');
    }

    // ─── S6: Loopback accepted with IRB_ALLOW_LLM_LOOPBACK=true ─────────────────

    #[Test]
    public function s6_loopback_with_env_flag_accepted(): void
    {
        $this->setLoopbackFlag(true);
        $this->assertAccepted('http://127.0.0.1:11434');
    }

    #[Test]
    public function s6_ipv6_loopback_with_env_flag_accepted(): void
    {
        $this->setLoopbackFlag(true);
        $this->assertAccepted('http://[::1]/');
    }

    // ─── S6b: Loopback flag does NOT relax RFC1918 (REQ-LLM-009) ────────────────

    #[Test]
    public function s6b_loopback_flag_does_not_open_rfc1918(): void
    {
        $this->setLoopbackFlag(true);
        $this->assertRejected('http://10.0.0.5/', '10.0.0.0/8');
    }

    #[Test]
    public function s6b_loopback_flag_does_not_open_link_local(): void
    {
        $this->setLoopbackFlag(true);
        $this->assertRejected('http://169.254.169.254/', '169.254');
    }

    #[Test]
    public function s6b_loopback_flag_does_not_open_ipv6_link_local(): void
    {
        $this->setLoopbackFlag(true);
        $this->assertRejected('http://[fe80::1]/', 'fe80::/10');
    }

    #[Test]
    public function s6b_loopback_flag_does_not_open_ipv6_ula(): void
    {
        $this->setLoopbackFlag(true);
        $this->assertRejected('http://[fd00::1]/', 'ULA');
    }

    // ─── S7: max:2048 length cap (controller-side); validator hard-caps at 2048 ─

    #[Test]
    public function s7_max_2048_oversize_rejected(): void
    {
        // Construct a URL with 2049 chars total (host portion padded).
        $host = str_repeat('a', 2049 - strlen('http://'));
        $url = 'http://'.$host;
        $this->assertSame(2049, strlen($url));
        $this->assertRejected($url, '2048');
    }

    // ─── S11: Scheme rejection (REQ-LLM-007) ────────────────────────────────────

    #[Test]
    public function s11_scheme_rejection_ftp(): void
    {
        $this->assertRejected('ftp://example.com/', 'http or https');
    }

    #[Test]
    public function s11_scheme_rejection_javascript(): void
    {
        $this->assertRejected('javascript:alert(1)');
    }

    #[Test]
    public function s11_scheme_rejection_file(): void
    {
        $this->assertRejected('file:///etc/passwd');
    }

    #[Test]
    public function s11_scheme_rejection_gopher(): void
    {
        $this->assertRejected('gopher://evil/');
    }

    #[Test]
    public function s11_scheme_rejection_data(): void
    {
        $this->assertRejected('data:text/plain,test');
    }

    // ─── S12: Validator MUST NOT issue outbound HTTP (REQ-LLM-010) ──────────────

    #[Test]
    public function s12_no_outbound_tcp_during_validation(): void
    {
        Http::fake();

        // Trigger validator on three distinct paths: scheme reject, IP-literal
        // reject (RFC1918), and length-cap reject. None should make HTTP calls.
        ($this->validator)('ftp://example.com/');
        ($this->validator)('http://192.168.1.5/');
        ($this->validator)('http://10.0.0.5/');

        Http::assertNothingSent();
    }

    // ─── S17: IPv4-mapped IPv6 rejected (REQ-LLM-008) ───────────────────────────

    #[Test]
    public function s17_ipv4_mapped_ipv6_loopback_rejected_default(): void
    {
        // Default: loopback flag OFF → ::ffff:127.0.0.1 rejected.
        $this->assertRejected('http://[::ffff:127.0.0.1]/', 'loopback');
    }

    #[Test]
    public function s17_ipv4_mapped_ipv6_rfc1918_always_rejected(): void
    {
        // Even with loopback flag ON, embedded RFC1918 stays rejected.
        $this->setLoopbackFlag(true);
        $this->assertRejected('http://[::ffff:10.0.0.5]/', '10.0.0.0/8');
    }

    // ─── S18: 0.0.0.0 rejected (REQ-LLM-008) ────────────────────────────────────

    #[Test]
    public function s18_zero_zero_zero_zero_rejected(): void
    {
        $this->assertRejected('http://0.0.0.0:6379/', '0.0.0.0/8');
    }

    // ─── S19: Numeric IPv4 literals rejected (REQ-LLM-008) ──────────────────────

    #[Test]
    public function s19_decimal_ipv4_literal_rejected(): void
    {
        // 2130706433 == 127.0.0.1; canonicalised then rejected as loopback.
        $this->assertRejected('http://2130706433/');
    }

    #[Test]
    public function s19_hex_ipv4_literal_rejected(): void
    {
        // Hex form rejected outright (non-decimal literal).
        $this->assertRejected('http://0x7f000001/', 'non-decimal');
    }

    #[Test]
    public function s19_octal_ipv4_literal_rejected(): void
    {
        // Octal form (leading zero) rejected outright.
        $this->assertRejected('http://017700000001/');
    }

    #[Test]
    public function s19_short_form_ipv4_rejected(): void
    {
        // 127.1 short-form rejected outright (not a canonical 4-octet IPv4).
        $this->assertRejected('http://127.1/');
    }

    // ─── Acceptance: Tailscale CGNAT 100.64/10 explicitly allowed (REQ-LLM-008) ─

    #[Test]
    public function tailscale_100_64_accepted(): void
    {
        $this->assertAccepted('http://100.64.0.5:1234/');
    }

    #[Test]
    public function tailscale_100_127_accepted(): void
    {
        // Tailscale CGNAT is 100.64.0.0/10 (100.64.0.0 - 100.127.255.255).
        $this->assertAccepted('http://100.127.255.254:1234/');
    }

    // ─── Acceptance: public URL accepted (sanity) ───────────────────────────────

    #[Test]
    public function public_https_url_accepted(): void
    {
        $this->assertAccepted('https://api.openai.com/v1');
    }

    // ─── Empty / malformed / non-string inputs ─────────────────────────────────

    #[Test]
    public function empty_string_rejected(): void
    {
        $this->assertRejected('', 'non-empty');
    }

    #[Test]
    public function url_without_host_rejected(): void
    {
        $this->assertRejected('http:///path');
    }

    // ─── ValidationRule integration: Closure $fail invoked on failure ──────────

    #[Test]
    public function validation_rule_invokes_fail_closure_on_reject(): void
    {
        $captured = [];
        $this->validator->validate(
            'base_url',
            'http://192.168.1.5/',
            function (string $msg) use (&$captured): void {
                $captured[] = $msg;
            },
        );
        $this->assertNotEmpty($captured, 'fail closure must be invoked when validation rejects');
        $this->assertStringContainsString('192.168', $captured[0]);
    }

    #[Test]
    public function validation_rule_does_not_invoke_fail_on_accept(): void
    {
        $captured = [];
        $this->validator->validate(
            'base_url',
            'https://api.openai.com/v1',
            function (string $msg) use (&$captured): void {
                $captured[] = $msg;
            },
        );
        $this->assertSame([], $captured);
    }

    // ─── S17b: IPv4-compatible IPv6 (::a.b.c.d, deprecated ::/96) rejected ──────

    #[Test]
    public function s17b_ipv4_compatible_ipv6_loopback_rejected(): void
    {
        // ::7f00:0001 == ::127.0.0.1 — embedded loopback, must reject by default.
        $err = ($this->validator)('http://[::7f00:0001]/');
        $this->assertNotNull($err);
    }

    #[Test]
    public function s17b_ipv4_compatible_ipv6_rfc1918_rejected(): void
    {
        // ::a00:0005 == ::10.0.0.5 — embedded RFC1918, must reject.
        $err = ($this->validator)('http://[::a00:0005]/');
        $this->assertNotNull($err);
    }

    // ─── S17c: IPv6 6to4 (2002::/16) and Teredo (2001::/32) rejected ────────────

    #[Test]
    public function s17c_ipv6_6to4_rejected(): void
    {
        // 6to4 wrapping 127.0.0.1 — falls in the 2002::/16 reject range.
        $err = ($this->validator)('http://[2002:7f00:0001::]/');
        $this->assertNotNull($err);
    }

    #[Test]
    public function s17c_ipv6_teredo_rejected(): void
    {
        // Teredo prefix 2001::/32.
        $err = ($this->validator)('http://[2001::1]/');
        $this->assertNotNull($err);
    }

    // ─── M6: config('llm.allow_loopback') drives loopback gate ──────────────────

    /**
     * M6: When config('llm.allow_loopback') is true, 127.0.0.1 must be accepted.
     * This mirrors s6_loopback_with_env_flag_accepted but via config() layer.
     */
    #[Test]
    public function m6_loopback_allowed_via_config_key(): void
    {
        config(['llm.allow_loopback' => true]);

        $this->assertAccepted('http://127.0.0.1:11434');
    }

    /**
     * M6: When config('llm.allow_loopback') is false (default), 127.0.0.1 must be rejected.
     */
    #[Test]
    public function m6_loopback_rejected_when_config_false(): void
    {
        config(['llm.allow_loopback' => false]);

        $this->assertRejected('http://127.0.0.1:11434', 'loopback');
    }
}
