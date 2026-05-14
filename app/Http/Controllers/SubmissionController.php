<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Study;
use App\Models\Submission;
use App\Services\Hrp398PanelService;
use App\Services\SectionTriggerEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles per-form-code Submission views and assistance-mode toggling.
 *
 * SPEC-IRB-FORMSV2-004 §A.2
 */
class SubmissionController extends Controller
{
    /**
     * GET /studies/{uuid}/submissions/{form_code}
     *
     * Resolves Study by uuid, ownership-checks, then resolves the matching
     * Submission. Dispatches to the appropriate view based on form_code.
     */
    public function show(Request $request, string $uuid, string $form_code): View
    {
        $study = Study::query()->where('uuid', $uuid)->firstOrFail();

        if ($study->user_id !== $request->user()->id) {
            abort(404);
        }

        $submission = Submission::query()
            ->where('study_id', $study->id)
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', $form_code))
            ->with([
                'formDefinition.sections.questions.options',
                'formDefinition.sections.questions.children.options',
                'formDefinition.sectionGroups',
                'formDefinition.endnotes',
                'answers',
            ])
            ->firstOrFail();

        if ($form_code === 'HRP-398') {
            $panelService = app(Hrp398PanelService::class);

            return view('submissions.hrp398_panel', [
                'study' => $study,
                'submission' => $submission,
                'panelData' => $panelService->loadItemsForSubmission($submission),
                'counts' => $panelService->aggregateCounts($submission),
            ]);
        }

        // Build answer lookup map: question_key → SubmissionAnswer
        $answersByQuestionKey = $submission->answers->keyBy('question_key');

        // Build trigger-answer map for SectionTriggerEvaluator.
        // Only populated for HRP-503 (the only form with cross-section triggers).
        $sectionVisibility = [];
        $sectionGroups = collect();

        if ($form_code === 'HRP-503') {
            $triggerAnswerValues = $this->buildTriggerAnswerValues($answersByQuestionKey);
            $sectionVisibility = SectionTriggerEvaluator::buildSectionVisibilityMap(
                $submission->formDefinition->sections,
                $triggerAnswerValues,
            );
            $sectionGroups = $submission->formDefinition->sectionGroups
                ->sortBy('display_order');
        }

        return view('submissions.show', [
            'study' => $study,
            'submission' => $submission,
            'formDefinition' => $submission->formDefinition,
            'answersByQuestionKey' => $answersByQuestionKey,
            'sectionVisibility' => $sectionVisibility,
            'sectionGroups' => $sectionGroups,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Build the raw trigger answer values map used by SectionTriggerEvaluator.
     *
     * For checkbox trigger questions (checkbox_multi_with_section_triggers) the value
     * is the json_value array; for radio questions it is option_value.
     *
     * @param  \Illuminate\Support\Collection<string, \App\Models\SubmissionAnswer>  $answersByKey
     * @return array<string, mixed>
     */
    private function buildTriggerAnswerValues(\Illuminate\Support\Collection $answersByKey): array
    {
        $values = [];
        foreach ($answersByKey as $qKey => $answer) {
            if ($answer->json_value !== null && is_array($answer->json_value)) {
                $values[$qKey] = $answer->json_value;
            } elseif ($answer->option_value !== null) {
                $values[$qKey] = $answer->option_value;
            } elseif ($answer->text_value !== null) {
                $values[$qKey] = $answer->text_value;
            }
        }

        return $values;
    }

    /**
     * POST /studies/{uuid}/submissions/{form_code}/assistance-mode
     *
     * Toggles assistance_mode between 'strict' and 'assistant'.
     * Uses forceFill because assistance_mode IS in $fillable, but this
     * enforces the pattern used throughout for protected writes.
     */
    public function updateAssistanceMode(Request $request, string $uuid, string $form_code): RedirectResponse
    {
        $study = Study::query()->where('uuid', $uuid)->firstOrFail();

        if ($study->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'assistance_mode' => ['required', 'string', 'in:strict,assistant'],
        ]);

        $submission = Submission::query()
            ->where('study_id', $study->id)
            ->whereHas('formDefinition', fn ($q) => $q->where('form_code', $form_code))
            ->firstOrFail();

        $before = $submission->assistance_mode;
        $submission->forceFill(['assistance_mode' => $validated['assistance_mode']])->save();

        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $request->user()->id,
            'event_type' => 'submission.assistance_mode.updated',
            'entity_type' => 'submission',
            'entity_id' => $submission->id,
            'entity_uuid' => null,
            'project_id' => null,
            'ip' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'request_id' => null,
            'payload' => [
                'before' => $before,
                'after' => $submission->assistance_mode,
            ],
        ]);

        return redirect()
            ->route('submissions.show', ['uuid' => $uuid, 'form_code' => $form_code])
            ->with('status', 'Assistance mode updated.');
    }
}
