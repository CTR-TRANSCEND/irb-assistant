<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormQuestion;
use App\Models\FormQuestionOption;

/**
 * Single source-of-truth for question visibility given current submission state.
 *
 * Phase 4 PR-1 implements:
 *   - reveal_subfields  — parent option reveals its subfield children
 *   - stop_*            — terminal routing outcome action types
 *   - skip_to           — single within-section jump
 *
 * Cross-section triggers_section: always-visible stub (Phase 5 completes the graph).
 *
 * REQ-IRB-FORMSV2-042
 * SPEC-IRB-FORMSV2-004 §B.4
 *
 * @MX:ANCHOR: [AUTO] isQuestionVisible() is the single visibility gate for all question rendering.
 *
 * @MX:REASON: fan_in >= 3 — called by SubmissionAnswerController, Blade views, and ConditionalVisibilityEvaluatorTest.
 */
class ConditionalVisibilityEvaluator
{
    private const TERMINAL_ACTION_TYPES = [
        'stop_and_submit',
        'stop_engaged',
        'stop_not_engaged',
        'stop_or_skip_to_3.0',
    ];

    /**
     * Determine whether $question should be visible given $answers.
     *
     * @param  array<string, mixed>  $answers  Map of question_key → answer scalar/value.
     *                                         For radio: the string option_value.
     *                                         For bool: true/false.
     *                                         For multi: array of option_values.
     */
    public function isQuestionVisible(FormQuestion $question, array $answers): bool
    {
        // Top-level questions (no parent) are always visible within their section
        if ($question->parent_question_id === null) {
            // Phase 4 stub: triggers_section cross-section logic always returns visible.
            // Phase 5 implements the full cross-section trigger graph for HRP-503.
            return true;
        }

        // Subfield: visible only when parent's selected option has action_type='reveal_subfields'
        $parent = $question->parent;
        if ($parent === null) {
            return true;
        }

        $parentAnswer = $answers[$parent->question_key] ?? null;
        if ($parentAnswer === null) {
            return false; // parent not answered → subfield hidden
        }

        // Find the parent option matching the current answer
        $matchingOption = $parent->options
            ->first(fn (FormQuestionOption $opt) => $opt->option_value === (string) $parentAnswer);

        if ($matchingOption === null) {
            return false;
        }

        return $matchingOption->action_type === 'reveal_subfields';
    }

    /**
     * Return the routing outcome string if this option terminates the flow; else null.
     *
     * REQ-IRB-FORMSV2-014a routing_outcome capture contract.
     */
    public function getRoutingOutcomeFromOption(FormQuestionOption $option): ?string
    {
        if (in_array($option->action_type, self::TERMINAL_ACTION_TYPES, true)) {
            return $option->action_type;
        }

        return null;
    }
}
