<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\Study;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.1
 * Scenario coverage: StudyController index, create, store, show, destroy.
 */
class StudyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
    }

    // ── index ──────────────────────────────────────────────────────────────────

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->get(route('studies.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function index_returns_200_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('studies.index'))
            ->assertOk()
            ->assertViewIs('studies.index');
    }

    #[Test]
    public function index_only_shows_user_own_studies(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        Study::createForUser($other->id, ['application_title' => 'Other Study']);
        Study::createForUser($this->user->id, ['application_title' => 'My Study']);

        $response = $this->actingAs($this->user)->get(route('studies.index'));
        $response->assertOk();

        $studies = $response->viewData('studies');
        $this->assertCount(1, $studies);
        $this->assertSame('My Study', $studies->first()->application_title);
    }

    // ── create ─────────────────────────────────────────────────────────────────

    #[Test]
    public function create_returns_200(): void
    {
        $this->actingAs($this->user)
            ->get(route('studies.create'))
            ->assertOk()
            ->assertViewIs('studies.create');
    }

    // ── store ──────────────────────────────────────────────────────────────────

    #[Test]
    public function store_creates_study_and_redirects(): void
    {
        $this->actingAs($this->user)
            ->post(route('studies.store'), [
                'application_title' => 'Safety Study',
                'pi_name' => 'Dr. Smith',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('studies', [
            'application_title' => 'Safety Study',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function store_cannot_rebind_user_id_via_request_input(): void
    {
        // S-P4-7: mass-assignment cannot override user_id
        $otherUser = User::factory()->create(['is_approved' => true]);

        $this->actingAs($this->user)
            ->post(route('studies.store'), [
                'application_title' => 'Attempted Injection',
                'user_id' => $otherUser->id, // attacker-supplied; must be ignored
            ]);

        $study = Study::where('application_title', 'Attempted Injection')->first();
        $this->assertNotNull($study);
        $this->assertSame($this->user->id, $study->user_id, 'user_id must equal Auth::id()');
        $this->assertNotSame($otherUser->id, $study->user_id, 'attacker-supplied user_id must be ignored');
    }

    #[Test]
    public function store_creates_three_child_submissions(): void
    {
        $this->actingAs($this->user)
            ->post(route('studies.store'), ['application_title' => 'Test']);

        $study = Study::where('user_id', $this->user->id)->first();
        $this->assertNotNull($study);
        $this->assertSame(3, $study->submissions()->count());
    }

    // ── show ───────────────────────────────────────────────────────────────────

    #[Test]
    public function show_returns_200_for_owner(): void
    {
        $study = Study::createForUser($this->user->id, ['application_title' => 'Shown Study']);

        $this->actingAs($this->user)
            ->get(route('studies.show', ['uuid' => $study->uuid]))
            ->assertOk()
            ->assertViewIs('studies.show');
    }

    #[Test]
    public function show_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $study = Study::createForUser($other->id, ['application_title' => 'Others Study']);

        $this->actingAs($this->user)
            ->get(route('studies.show', ['uuid' => $study->uuid]))
            ->assertNotFound();
    }

    #[Test]
    public function show_404_for_nonexistent_uuid(): void
    {
        $this->actingAs($this->user)
            ->get(route('studies.show', ['uuid' => 'does-not-exist-uuid']))
            ->assertNotFound();
    }

    // ── destroy ────────────────────────────────────────────────────────────────

    #[Test]
    public function destroy_deletes_study_and_redirects(): void
    {
        $study = Study::createForUser($this->user->id, ['application_title' => 'To Delete']);
        $studyId = $study->id;

        $this->actingAs($this->user)
            ->delete(route('studies.destroy', ['uuid' => $study->uuid]))
            ->assertRedirect(route('studies.index'));

        $this->assertDatabaseMissing('studies', ['id' => $studyId]);
    }

    #[Test]
    public function destroy_cascades_to_submissions(): void
    {
        $study = Study::createForUser($this->user->id, []);
        $submissionIds = $study->submissions()->pluck('id')->all();

        $this->actingAs($this->user)
            ->delete(route('studies.destroy', ['uuid' => $study->uuid]));

        foreach ($submissionIds as $sid) {
            $this->assertDatabaseMissing('submission', ['id' => $sid]);
        }
    }

    #[Test]
    public function destroy_returns_404_for_non_owner(): void
    {
        $other = User::factory()->create(['is_approved' => true]);
        $study = Study::createForUser($other->id, []);

        $this->actingAs($this->user)
            ->delete(route('studies.destroy', ['uuid' => $study->uuid]))
            ->assertNotFound();
    }

    // ── REQ-IRB-FORMSV2-049a badge format (Evaluator F2 fix) ───────────────────

    #[Test]
    public function index_hrp398_badge_shows_items_addressed_count_not_tracking_only(): void
    {
        $study = Study::createForUser($this->user->id, [
            'nickname' => 'Badge format test',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('studies.index'));

        $response->assertOk();
        // REQ-049a: HRP-398 badge MUST show N/M items addressed format,
        // not the permanently-tracking_only status from REQ-014a.
        $response->assertSeeText('HRP-398: 0/40 items addressed');
        $response->assertDontSeeText('HRP-398: tracking only');
    }
}
