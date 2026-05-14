<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\FormDefinition;
use App\Models\Study;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the (study_id, form_definition_id) UNIQUE constraint on submission.
 *
 * SPEC-IRB-FORMSV2-003: UniqueConstraintTest
 * Cites REQ-IRB-FORMSV2-013.
 */
class UniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function attempting_duplicate_submission_throws_integrity_constraint_exception(): void
    {
        $user = User::factory()->create(['is_approved' => true]);

        // Creating a Study auto-creates 3 submissions (one per form code)
        $study = Study::createForUser($user->id, ['pi_name' => 'Dr. Test',
        ]);

        $this->assertSame(3, $study->submissions()->count(), 'Precondition: 3 submissions exist');

        // Attempt to insert a 4th submission with an already-used form_definition_id
        $existingFormDef = FormDefinition::where('form_code', 'HRP-503c')->first();

        $this->expectException(QueryException::class);

        // This should throw because (study_id, form_definition_id) already exists
        \Illuminate\Support\Facades\DB::table('submission')->insert([
            'study_id' => $study->id,
            'form_definition_id' => $existingFormDef->id,
            'user_id' => $user->id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function different_studies_can_have_same_form_code(): void
    {
        $user = User::factory()->create(['is_approved' => true]);

        $study1 = Study::createForUser($user->id, ['pi_name' => 'Dr. A']);
        $study2 = Study::createForUser($user->id, ['pi_name' => 'Dr. B']);

        // Both should have 3 submissions each without conflict
        $this->assertSame(3, $study1->submissions()->count());
        $this->assertSame(3, $study2->submissions()->count());

        // Total of 6 submissions in DB
        $this->assertEquals(6, \Illuminate\Support\Facades\DB::table('submission')->count());
    }
}
