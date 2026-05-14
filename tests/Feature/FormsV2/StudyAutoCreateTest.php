<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\Study;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies Study auto-creates exactly 3 child Submissions atomically.
 *
 * SPEC-IRB-FORMSV2-003: StudyAutoCreateTest
 * Scenario S-P3-2: Study auto-creation produces 3 Submissions atomically.
 * Cites REQ-IRB-FORMSV2-011a, REQ-IRB-FORMSV2-006.
 */
class StudyAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_approved' => true]);
    }

    #[Test]
    public function study_create_produces_exactly_three_submissions(): void
    {
        $study = Study::createForUser($this->user->id, ['application_title' => 'Test Study',
            'pi_name' => 'Dr. Test',
            'oversight' => 'Test Director',
        ]);

        $this->assertSame(3, $study->submissions()->count(), 'Expected exactly 3 child Submissions');
    }

    #[Test]
    public function auto_created_submissions_have_correct_form_codes(): void
    {
        $study = Study::createForUser($this->user->id, ['application_title' => 'Test Study',
            'pi_name' => 'Dr. Test',
        ]);

        $formCodes = $study->submissions()
            ->join('form_definition', 'submission.form_definition_id', '=', 'form_definition.id')
            ->pluck('form_definition.form_code')
            ->sort()
            ->values()
            ->all();

        $this->assertEquals(['HRP-398', 'HRP-503', 'HRP-503c'], $formCodes);
    }

    #[Test]
    public function hrp398_submission_has_tracking_only_status(): void
    {
        $study = Study::createForUser($this->user->id, ['pi_name' => 'Dr. Test',
        ]);

        $hrp398Submission = $study->submissions()
            ->join('form_definition', 'submission.form_definition_id', '=', 'form_definition.id')
            ->where('form_definition.form_code', 'HRP-398')
            ->select('submission.*')
            ->first();

        $this->assertNotNull($hrp398Submission);
        $this->assertEquals('tracking_only', $hrp398Submission->status);
    }

    #[Test]
    public function hrp503_and_hrp503c_submissions_have_draft_status(): void
    {
        $study = Study::createForUser($this->user->id, ['pi_name' => 'Dr. Test',
        ]);

        $draftSubmissions = $study->submissions()
            ->join('form_definition', 'submission.form_definition_id', '=', 'form_definition.id')
            ->whereIn('form_definition.form_code', ['HRP-503', 'HRP-503c'])
            ->select('submission.*')
            ->get();

        foreach ($draftSubmissions as $sub) {
            $this->assertEquals('draft', $sub->status);
        }
    }

    #[Test]
    public function denormalized_fields_are_copied_to_each_submission(): void
    {
        $study = Study::createForUser($this->user->id, ['application_title' => 'AI Safety Study',
            'pi_name' => 'Dr. Smith',
            'oversight' => 'Director Jones',
        ]);

        $submissions = $study->submissions()->get();

        foreach ($submissions as $sub) {
            $this->assertEquals('AI Safety Study', $sub->title, 'Title not copied to submission');
            $this->assertEquals('Dr. Smith', $sub->principal_investigator, 'PI not copied to submission');
            $this->assertEquals('Director Jones', $sub->oversight, 'Oversight not copied to submission');
        }
    }

    #[Test]
    public function study_created_audit_event_is_emitted(): void
    {
        $study = Study::createForUser($this->user->id, ['pi_name' => 'Dr. Test',
        ]);

        $auditEvent = DB::table('audit_events')
            ->where('event_type', 'study.created')
            ->where('entity_type', 'study')
            ->where('entity_id', $study->id)
            ->first();

        $this->assertNotNull($auditEvent, 'study.created audit event not found');

        $payload = json_decode($auditEvent->payload, true);
        $this->assertEquals($study->id, $payload['study_id']);
        $this->assertCount(3, $payload['submission_ids']);
        $this->assertEqualsCanonicalizing(['HRP-503', 'HRP-503c', 'HRP-398'], $payload['form_codes']);

        // Evaluator F3 (HIGH): submission_ids is a form_code-keyed dict.
        $this->assertArrayHasKey('HRP-503', $payload['submission_ids']);
        $this->assertArrayHasKey('HRP-503c', $payload['submission_ids']);
        $this->assertArrayHasKey('HRP-398', $payload['submission_ids']);
        $this->assertIsInt($payload['submission_ids']['HRP-503']);

        $hrp503Sub = $study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503'))
            ->firstOrFail();
        $this->assertSame($hrp503Sub->id, $payload['submission_ids']['HRP-503']);
    }

    /**
     * Evaluator F4 (HIGH): when FormDefinition rows are missing, Study::createForUser
     * MUST roll back so zero rows persist in both `studies` and `submission`.
     * Validates REQ-IRB-FORMSV2-011a atomicity (fixed in createForUser via DB::transaction).
     *
     * @test
     */
    public function study_row_does_not_persist_when_form_definition_missing(): void
    {
        // Delete the HRP-398 FormDefinition so the boot hook's lookup fails.
        \App\Models\FormDefinition::where('form_code', 'HRP-398')->delete();

        $studiesBefore = Study::count();
        $submissionsBefore = \App\Models\Submission::count();

        try {
            Study::createForUser($this->user->id, ['pi_name' => 'Will Fail']);
            $this->fail('Expected RuntimeException when FormDefinition missing.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HRP-398', $e->getMessage());
        }

        // Atomicity: no Study, no Submission persisted.
        $this->assertSame($studiesBefore, Study::count(), 'Study row leaked despite FormDefinition lookup failure.');
        $this->assertSame($submissionsBefore, \App\Models\Submission::count(), 'Submission row leaked.');
    }

    #[Test]
    public function study_has_auto_generated_uuid(): void
    {
        $study = Study::createForUser($this->user->id, []);
        $this->assertNotEmpty($study->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $study->uuid
        );
    }

    #[Test]
    public function all_submissions_belong_to_correct_user(): void
    {
        $study = Study::createForUser($this->user->id, ['pi_name' => 'Dr. Test',
        ]);

        $submissions = $study->submissions()->get();
        foreach ($submissions as $sub) {
            $this->assertEquals($this->user->id, $sub->user_id);
        }
    }
}
