<?php

declare(strict_types=1);

namespace Tests\Feature\FormsV2;

use App\Models\FormQuestion;
use App\Models\FormQuestionOption;
use App\Models\Study;
use App\Models\User;
use App\Services\ConditionalVisibilityEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-IRB-FORMSV2-004 §H.5
 * Covers ConditionalVisibilityEvaluator — reveal_subfields, stop_* terminal
 * routing outcomes, and group_label passthrough behaviour.
 */
class ConditionalVisibilityEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private ConditionalVisibilityEvaluator $evaluator;

    private User $user;

    private Study $study;

    private int $formDefinitionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ConditionalVisibilityEvaluator;
        $this->user = User::factory()->create(['is_approved' => true]);
        $this->study = Study::createForUser($this->user->id, ['application_title' => 'CVE Test']);

        $this->formDefinitionId = $this->study->submissions()
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', 'HRP-503c'))
            ->firstOrFail()
            ->form_definition_id;
    }

    // ── Top-level question visibility ─────────────────────────────────────────

    #[Test]
    public function top_level_question_is_always_visible(): void
    {
        $question = $this->findTopLevelQuestion();
        $this->assertNotNull($question, 'Seeded HRP-503c must have at least one top-level question');

        $result = $this->evaluator->isQuestionVisible($question, []);
        $this->assertTrue($result);
    }

    #[Test]
    public function top_level_question_visible_regardless_of_answers(): void
    {
        $question = $this->findTopLevelQuestion();
        $this->assertNotNull($question);

        // Even with unrelated answers present, top-level questions are always visible.
        $result = $this->evaluator->isQuestionVisible($question, [
            'some_other_key' => 'some_value',
        ]);
        $this->assertTrue($result);
    }

    // ── Subfield (reveal_subfields) visibility ────────────────────────────────

    #[Test]
    public function subfield_is_hidden_when_parent_not_answered(): void
    {
        $subfield = $this->findSubfieldQuestion();

        if ($subfield === null) {
            $this->markTestSkipped('No subfield questions in HRP-503c seed');
        }

        $result = $this->evaluator->isQuestionVisible($subfield, []);
        $this->assertFalse($result);
    }

    #[Test]
    public function subfield_is_hidden_when_parent_option_does_not_reveal(): void
    {
        $subfield = $this->findSubfieldQuestion();

        if ($subfield === null) {
            $this->markTestSkipped('No subfield questions in HRP-503c seed');
        }

        $parent = $subfield->parent;
        $this->assertNotNull($parent);

        // Use an option that is NOT reveal_subfields
        $nonRevealOption = $parent->options
            ->first(fn (FormQuestionOption $opt) => $opt->action_type !== 'reveal_subfields');

        if ($nonRevealOption === null) {
            $this->markTestSkipped('Parent has no non-reveal_subfields option in seed');
        }

        $result = $this->evaluator->isQuestionVisible($subfield, [
            $parent->question_key => $nonRevealOption->option_value,
        ]);
        $this->assertFalse($result);
    }

    #[Test]
    public function subfield_is_visible_when_parent_option_reveals_it(): void
    {
        $subfield = $this->findSubfieldQuestion();

        if ($subfield === null) {
            $this->markTestSkipped('No subfield questions in HRP-503c seed');
        }

        $parent = $subfield->parent;
        $this->assertNotNull($parent);

        $revealOption = $parent->options
            ->first(fn (FormQuestionOption $opt) => $opt->action_type === 'reveal_subfields');

        if ($revealOption === null) {
            $this->markTestSkipped('Parent has no reveal_subfields option in seed');
        }

        $result = $this->evaluator->isQuestionVisible($subfield, [
            $parent->question_key => $revealOption->option_value,
        ]);
        $this->assertTrue($result);
    }

    // ── getRoutingOutcomeFromOption ────────────────────────────────────────────

    #[Test]
    public function non_terminal_option_returns_null_routing_outcome(): void
    {
        $option = FormQuestionOption::query()
            ->whereNotIn('action_type', [
                'stop_and_submit',
                'stop_engaged',
                'stop_not_engaged',
                'stop_or_skip_to_3.0',
            ])
            ->whereHas('question.section', fn ($q) => $q->where('form_definition_id', $this->formDefinitionId))
            ->first();

        if ($option === null) {
            $this->markTestSkipped('No non-terminal option in HRP-503c seed');
        }

        $result = $this->evaluator->getRoutingOutcomeFromOption($option);
        $this->assertNull($result);
    }

    #[Test]
    public function stop_action_option_returns_its_action_type_as_routing_outcome(): void
    {
        $option = FormQuestionOption::query()
            ->whereIn('action_type', [
                'stop_and_submit',
                'stop_engaged',
                'stop_not_engaged',
                'stop_or_skip_to_3.0',
            ])
            ->whereHas('question.section', fn ($q) => $q->where('form_definition_id', $this->formDefinitionId))
            ->first();

        if ($option === null) {
            $this->markTestSkipped('No stop_* option in HRP-503c seed — terminal routing test skipped');
        }

        $result = $this->evaluator->getRoutingOutcomeFromOption($option);
        $this->assertSame($option->action_type, $result);
    }

    #[Test]
    public function each_terminal_action_type_returns_exact_string(): void
    {
        $terminalTypes = [
            'stop_and_submit',
            'stop_engaged',
            'stop_not_engaged',
            'stop_or_skip_to_3.0',
        ];

        $foundAny = false;
        foreach ($terminalTypes as $actionType) {
            $option = FormQuestionOption::query()
                ->where('action_type', $actionType)
                ->whereHas('question.section', fn ($q) => $q->where('form_definition_id', $this->formDefinitionId))
                ->first();

            if ($option === null) {
                continue;
            }

            $foundAny = true;
            $result = $this->evaluator->getRoutingOutcomeFromOption($option);
            $this->assertSame($actionType, $result, "Expected routing outcome '$actionType' for option with matching action_type");
        }

        if (! $foundAny) {
            $this->markTestSkipped('No terminal action_type options found in HRP-503c seed');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findTopLevelQuestion(): ?FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('form_definition_id', $this->formDefinitionId))
            ->whereNull('parent_question_id')
            ->where('question_type', '!=', 'group_label')
            ->first();
    }

    private function findSubfieldQuestion(): ?FormQuestion
    {
        return FormQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('form_definition_id', $this->formDefinitionId))
            ->whereNotNull('parent_question_id')
            ->with(['parent.options'])
            ->first();
    }
}
