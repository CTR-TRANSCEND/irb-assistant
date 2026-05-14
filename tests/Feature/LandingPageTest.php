<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-UI-001: Branded Landing Page with Login + Register Entry.
 *
 * Covers acceptance scenarios A-D (Scenarios E and F are manual axe-core /
 * 375 px viewport checks, documented in acceptance.md).
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scenario A: GET / for guests renders welcome with key strings.
     * REQ-UI-001, REQ-UI-003, REQ-UI-005, REQ-UI-006, REQ-UI-007.
     */
    public function test_guest_visits_root_renders_welcome_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('IRB Assistant', false);
        $response->assertSee('Log in', false);
        $response->assertSee('Register', false);
        $response->assertSee('https://ignet.org/irb-assistant', false);
    }

    /**
     * Scenario B: Authenticated user is redirected to projects.index.
     * REQ-UI-002.
     */
    public function test_authenticated_user_visits_root_redirects_to_projects(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(302);
        $response->assertRedirect(route('studies.index'));
    }

    /**
     * Scenario A (continued): Login + Register links resolve to named routes.
     * REQ-UI-005, REQ-UI-006.
     */
    public function test_welcome_page_links_to_login_and_register_routes(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('register').'"', false);
    }

    /**
     * Scenario C: Three-feature explainer block.
     * REQ-UI-004.
     */
    public function test_welcome_page_describes_three_features(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Upload', false);
        $response->assertSee('AI First-Pass', false);
        $response->assertSee('DOCX Export', false);
    }

    /**
     * Scenario D (part 1): Page references Vite-built assets.
     * REQ-UI-010.
     */
    public function test_welcome_page_uses_vite_assets(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // @vite emits links to /build/... assets (or hot-reload URL in dev).
        $body = $response->getContent();
        $this->assertTrue(
            str_contains($body, '/build/') || str_contains($body, 'resources/css/app.css'),
            'Response should reference Vite-built assets (/build/...) or dev manifest.'
        );
    }

    /**
     * Scenario D (part 2): HTML body stays under 30 KB uncompressed.
     * REQ-UI-016.
     */
    public function test_welcome_page_response_size_under_30kb(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $size = strlen($response->getContent());
        $this->assertLessThan(
            30720,
            $size,
            "Landing page body is {$size} bytes; must be under 30720 bytes (30 KB)."
        );
    }

    /**
     * REQ-UI-017: SHALL NOT load font CDNs or external CSS from the landing page.
     * Regression: prior version embedded fonts.bunny.net <link> tags; replaced
     * with Vite-bundled @fontsource/inter.
     */
    #[Test]
    public function welcome_page_does_not_load_external_cdn_or_font_resources(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('fonts.bunny.net', $body);
        $this->assertStringNotContainsString('fonts.googleapis.com', $body);
        $this->assertStringNotContainsString('fonts.gstatic.com', $body);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $body);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $body);
        $this->assertStringNotContainsString('unpkg.com', $body);
    }

    /**
     * Institutional collaboration credit: Sanford Health appears in the kicker
     * and footer alongside University of North Dakota.
     */
    #[Test]
    public function welcome_page_shows_sanford_health_collaboration(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $response->assertSee('University of North Dakota', false);
        $response->assertSee('In collaboration with Sanford Health', false);
        $response->assertSee('Sanford Health', false);
    }

    /**
     * Developer credit and NIH grant attribution appear in the landing footer.
     */
    #[Test]
    public function welcome_page_shows_developer_credit_and_grant(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $response->assertSee('Dr. Junguk Hur', false);
        $response->assertSee('University of North Dakota School of Medicine and Health Sciences', false);
        $response->assertSee('TRANSCEND RDCDC', false);
        $response->assertSee('NIH/NIGMS P20GM155890', false);
    }
}
