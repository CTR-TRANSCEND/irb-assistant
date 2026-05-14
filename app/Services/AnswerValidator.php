<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormQuestion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates raw HTTP input against a FormQuestion's question_type and returns
 * the correctly-typed payload for submission_answer insertion.
 *
 * Single source-of-truth for per-type validation so controllers stay thin.
 * SPEC-IRB-FORMSV2-004 §B helper.
 *
 * @MX:ANCHOR: [AUTO] validateAnswer() is called by every answer-write path.
 *
 * @MX:REASON: fan_in >= 3 — SubmissionAnswerController::update(), SubmissionAnalysisService, and tests.
 */
class AnswerValidator
{
    private const TEXT_TYPES = [
        'text_short',
        'text_long',
        'textarea',
        'textarea_with_na',
        'textarea_with_multiple_na',
        'radio_with_conditional_text',
        'na_or_explain',
        'group_label',
    ];

    private const RADIO_TYPES = [
        'radio_single',
        'radio_with_conditional_text',
        'na_or_explain',
        'na_or_confirm',
        'na_or_criteria_checklist',
        'na_or_multi_checkbox',
    ];

    private const BOOL_TYPES = [
        'checkbox_single',
        'na_or_confirm',
        'criterion_checkbox',
    ];

    private const JSON_TYPES = [
        'checkbox_multi',
        'checkbox_multi_with_subfields',
        'scenario_group',
        'exception_group',
    ];

    // ── Phase 5 new types (HRP-503) ────────────────────────────────────────────

    /**
     * Question types added in Phase 5 (HRP-503 long form).
     * Each has a dedicated validate* method below.
     */
    private const PHASE5_JSON_TYPES = [
        'checkbox_multi_with_section_triggers',
        'radio_with_nested_options',
        'numbered_options_with_criteria',
        'textarea_with_na_and_followup',
        'textarea_with_alternative_radio',
        'checkbox_with_optional_textarea',
    ];

    /**
     * Maximum byte size of an inbound JSON-string `json_value` payload before
     * decode. Mirrors the `max:65535` rule applied to text_value. Defense
     * against memory-exhaustion DoS via deeply-nested or long JSON strings.
     *
     * Security review F-SEC-2 (Phase 5).
     */
    private const JSON_MAX_BYTES = 65_535;

    /**
     * Maximum nesting depth for json_decode. All Phase 5 shapes nest at most
     * 2 levels (object→array→scalar). Depth 8 leaves generous headroom but
     * caps adversarial nesting at a memory-bounded value.
     *
     * Security review F-SEC-2 (Phase 5).
     */
    private const JSON_MAX_DEPTH = 8;

    /**
     * Safely decode a user-supplied JSON string into an array, with size and
     * depth caps applied. Returns null when the input is not a string, exceeds
     * the size cap, fails to parse, or does not decode to an array.
     *
     * @MX:ANCHOR: [AUTO] Cross-cutting guard — every Phase 4/5 json_value path routes through here.
     *
     * @MX:REASON: F-SEC-2 — without size/depth caps the validator is a DoS vector.
     */
    private static function safeDecodeJsonString(mixed $raw): ?array
    {
        if (! is_string($raw)) {
            return null;
        }
        if (strlen($raw) > self::JSON_MAX_BYTES) {
            return null;
        }
        $decoded = json_decode($raw, true, self::JSON_MAX_DEPTH);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Validate $input against $question->question_type rules and return a
     * 4-column payload: {text_value, option_value, bool_value, json_value}.
     *
     * Throws ValidationException on type mismatch or missing required value.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateAnswer(FormQuestion $question, array $input): array
    {
        $type = $question->question_type;

        $payload = [
            'text_value' => null,
            'option_value' => null,
            'bool_value' => null,
            'json_value' => null,
        ];

        if ($type === 'group_label') {
            // group_label has no input — reject any submitted value per REQ-P5-004.
            // The Phase 5 SPEC states group_label MUST never produce a submission_answer row.
            $hasInput = array_filter(
                array_intersect_key($input, array_flip(['text_value', 'option_value', 'bool_value', 'json_value', 'value'])),
                fn ($v) => $v !== null && $v !== '',
            );
            if (count($hasInput) > 0) {
                $validator = Validator::make($input, ['_group_label_guard' => 'prohibited']);
                // Build a synthetic failure for group_label
                $failedValidator = Validator::make([], ['value' => ['prohibited']]);
                throw new ValidationException($failedValidator);
            }

            return $payload;
        }

        // ── Phase 5 types: delegate to dedicated validators ────────────────────
        if (in_array($type, self::PHASE5_JSON_TYPES, true)) {
            return $this->validatePhase5Type($question, $input, $payload);
        }

        $rules = $this->buildRules($question);
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Map validated values into the correct column
        if (in_array($type, self::JSON_TYPES, true)) {
            $raw = $input['json_value'] ?? null;
            if (is_string($raw)) {
                $payload['json_value'] = self::safeDecodeJsonString($raw);
            } elseif (is_array($raw)) {
                $payload['json_value'] = $raw;
            }
        } elseif (in_array($type, self::BOOL_TYPES, true)) {
            $raw = $input['bool_value'] ?? null;
            $payload['bool_value'] = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        } else {
            // Text types get text_value; radio types additionally get option_value
            if (in_array($type, self::TEXT_TYPES, true)) {
                $payload['text_value'] = isset($input['text_value']) ? (string) $input['text_value'] : null;
            }
            if (in_array($type, self::RADIO_TYPES, true)) {
                $payload['option_value'] = isset($input['option_value']) ? (string) $input['option_value'] : null;
                // radio_with_conditional_text may also carry a text_value for the textarea
                if ($type === 'radio_with_conditional_text' && isset($input['text_value'])) {
                    $payload['text_value'] = (string) $input['text_value'];
                }
            }
            // textarea_with_multiple_na stores a JSON-encoded list of na flags
            if ($type === 'textarea_with_multiple_na' && isset($input['json_value'])) {
                $raw = $input['json_value'];
                $payload['json_value'] = is_array($raw) ? $raw : null;
            }
        }

        // Fallback: if everything is null and 'value' key is present, use it for text
        if ($payload === ['text_value' => null, 'option_value' => null, 'bool_value' => null, 'json_value' => null]
            && isset($input['value'])) {
            $payload['text_value'] = (string) $input['value'];
        }

        return $payload;
    }

    /**
     * Build laravel validation rules per question_type.
     *
     * @return array<string, mixed>
     */
    private function buildRules(FormQuestion $question): array
    {
        $type = $question->question_type;
        $required = $question->is_required ? 'required' : 'nullable';

        if (in_array($type, self::JSON_TYPES, true)) {
            return ['json_value' => [$required, 'array']];
        }

        if (in_array($type, self::BOOL_TYPES, true)) {
            return ['bool_value' => [$required]];
        }

        if ($type === 'radio_single') {
            return ['option_value' => [$required, 'string', 'max:64']];
        }

        if ($type === 'radio_with_conditional_text') {
            return [
                'option_value' => [$required, 'string', 'max:64'],
                'text_value' => ['nullable', 'string', 'max:65535'],
            ];
        }

        if (in_array($type, ['na_or_explain', 'na_or_confirm', 'na_or_criteria_checklist', 'na_or_multi_checkbox'], true)) {
            return [
                'option_value' => [$required, 'string', 'max:64'],
                'text_value' => ['nullable', 'string', 'max:65535'],
                'json_value' => ['nullable', 'array'],
            ];
        }

        if ($type === 'textarea_with_multiple_na') {
            // Security review F4: text_value capped at 65535 chars (TEXT column max).
            return [
                'text_value' => ['nullable', 'string', 'max:65535'],
                'json_value' => ['nullable', 'array'],
            ];
        }

        // Default: text_short, text_long, textarea, textarea_with_na
        return ['text_value' => [$required, 'string', 'max:65535']];
    }

    // ── Phase 5 type validation (HRP-503 long form) ────────────────────────────

    /**
     * Dispatch Phase 5 type validation and return the 4-column payload.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @MX:ANCHOR: [AUTO] validatePhase5Type() is the single entry point for all 6 Phase 5 JSON-shaped types.
     *
     * @MX:REASON: fan_in >= 3 — validateAnswer(), Phase5 test suite, SubmissionAnalysisService mock path.
     *
     * @throws ValidationException
     */
    private function validatePhase5Type(FormQuestion $question, array $input, array $payload): array
    {
        return match ($question->question_type) {
            'checkbox_multi_with_section_triggers' => $this->validateCheckboxMultiWithSectionTriggers($question, $input, $payload),
            'radio_with_nested_options' => $this->validateRadioWithNestedOptions($question, $input, $payload),
            'numbered_options_with_criteria' => $this->validateNumberedOptionsWithCriteria($question, $input, $payload),
            'textarea_with_na_and_followup' => $this->validateTextareaWithNaAndFollowup($question, $input, $payload),
            'textarea_with_alternative_radio' => $this->validateTextareaWithAlternativeRadio($question, $input, $payload),
            'checkbox_with_optional_textarea' => $this->validateCheckboxWithOptionalTextarea($question, $input, $payload),
            default => $payload,
        };
    }

    /**
     * checkbox_multi_with_section_triggers — array of strings from allowed options[].value.
     *
     * json_value stores the selected option values array.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateCheckboxMultiWithSectionTriggers(FormQuestion $question, array $input, array $payload): array
    {
        $allowedValues = $question->options()->pluck('option_value')->all();

        // An empty array is a valid "none selected" / deselect-all state for checkbox-multi.
        // We use 'present' so the field must be supplied but may be empty (REQ-P5-007).
        $rules = [
            'json_value' => ['present', 'array'],
            'json_value.*' => ['string', 'max:65535', Rule::in($allowedValues)],
        ];

        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $raw = $input['json_value'] ?? [];
        if (is_string($raw)) {
            $raw = self::safeDecodeJsonString($raw) ?? [];
        }

        $payload['json_value'] = is_array($raw) ? array_values($raw) : [];

        return $payload;
    }

    /**
     * radio_with_nested_options — single string from outer OR any nested option value.
     *
     * option_value stores the selected leaf value.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateRadioWithNestedOptions(FormQuestion $question, array $input, array $payload): array
    {
        // Collect outer option values
        $outerValues = $question->options()->pluck('option_value')->all();

        // Collect nested option values via children questions
        $nestedValues = [];
        $children = $question->children()->with('options')->get();
        foreach ($children as $child) {
            foreach ($child->options as $opt) {
                $nestedValues[] = $opt->option_value;
            }
        }

        $allAllowed = array_merge($outerValues, $nestedValues);
        $required = $question->is_required ? 'required' : 'nullable';

        $rules = [
            'option_value' => [$required, 'string', 'max:65535', Rule::in($allAllowed)],
        ];

        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $payload['option_value'] = isset($input['option_value']) ? (string) $input['option_value'] : null;

        return $payload;
    }

    /**
     * numbered_options_with_criteria — array of option IDs (strings).
     *
     * json_value stores the selected option value array.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateNumberedOptionsWithCriteria(FormQuestion $question, array $input, array $payload): array
    {
        $allowedValues = $question->options()->pluck('option_value')->all();
        $required = $question->is_required ? 'required' : 'nullable';

        $rules = [
            'json_value' => [$required, 'array'],
            'json_value.*' => ['string', 'max:65535', Rule::in($allowedValues)],
        ];

        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $raw = $input['json_value'] ?? [];
        if (is_string($raw)) {
            $raw = self::safeDecodeJsonString($raw) ?? [];
        }

        $payload['json_value'] = is_array($raw) ? array_values($raw) : [];

        return $payload;
    }

    /**
     * textarea_with_na_and_followup — JSON {na: bool, text: string|null, followup: string|null}.
     * If na=true, text and followup must be null.
     *
     * json_value stores the shape.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     *
     * @MX:WARN: [AUTO] Branch complexity: na=true path requires text/followup to be null; na=false path requires text/followup constraints.
     *
     * @MX:REASON: JSON shape has mutual exclusion semantics; all paths must be validated before writing to DB.
     */
    private function validateTextareaWithNaAndFollowup(FormQuestion $question, array $input, array $payload): array
    {
        // Accept either a pre-decoded array or a JSON string
        $raw = $input['json_value'] ?? null;
        if (is_string($raw)) {
            $raw = self::safeDecodeJsonString($raw);
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $required = $question->is_required ? 'required' : 'nullable';

        $rules = [
            'na' => [$required, 'boolean'],
            'text' => ['nullable', 'string', 'max:65535'],
            'followup' => ['nullable', 'string', 'max:65535'],
        ];

        $validator = Validator::make($raw, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $na = filter_var($raw['na'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        if ($na) {
            // na=true: text and followup must be null
            if (! empty($raw['text']) || ! empty($raw['followup'])) {
                $failValidator = Validator::make([], []);
                $failValidator->errors()->add('text', 'text must be null when na is true.');
                throw new ValidationException($failValidator);
            }
            $shape = ['na' => true, 'text' => null, 'followup' => null];
        } else {
            $shape = [
                'na' => false,
                'text' => isset($raw['text']) && $raw['text'] !== '' ? (string) $raw['text'] : null,
                'followup' => isset($raw['followup']) && $raw['followup'] !== '' ? (string) $raw['followup'] : null,
            ];
        }

        $payload['json_value'] = $shape;

        return $payload;
    }

    /**
     * textarea_with_alternative_radio — JSON {mode: "text"|"radio", text: string|null, radio: string|null}.
     * Exactly one of text/radio must be non-null. Radio wins when both are provided.
     *
     * json_value stores the shape.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateTextareaWithAlternativeRadio(FormQuestion $question, array $input, array $payload): array
    {
        $raw = $input['json_value'] ?? null;
        if (is_string($raw)) {
            $raw = self::safeDecodeJsonString($raw);
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $required = $question->is_required ? 'required' : 'nullable';

        $rules = [
            'mode' => [$required, 'string', 'in:text,radio'],
            'text' => ['nullable', 'string', 'max:65535'],
            'radio' => ['nullable', 'string', 'max:65535'],
        ];

        $validator = Validator::make($raw, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Radio wins per S-P5-7: if radio is non-null, mode=radio and text=null
        $radioValue = isset($raw['radio']) && $raw['radio'] !== '' ? (string) $raw['radio'] : null;
        $textValue = isset($raw['text']) && $raw['text'] !== '' ? (string) $raw['text'] : null;

        if ($radioValue !== null) {
            $shape = ['mode' => 'radio', 'text' => null, 'radio' => $radioValue];
        } else {
            $shape = ['mode' => 'text', 'text' => $textValue, 'radio' => null];
        }

        $payload['json_value'] = $shape;

        return $payload;
    }

    /**
     * checkbox_with_optional_textarea — JSON {checked: bool, text: string|null}.
     * If checked=false, text must be null.
     *
     * json_value stores the shape.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateCheckboxWithOptionalTextarea(FormQuestion $question, array $input, array $payload): array
    {
        $raw = $input['json_value'] ?? null;
        if (is_string($raw)) {
            $raw = self::safeDecodeJsonString($raw);
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $required = $question->is_required ? 'required' : 'nullable';

        $rules = [
            'checked' => [$required, 'boolean'],
            'text' => ['nullable', 'string', 'max:65535'],
        ];

        $validator = Validator::make($raw, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $checked = filter_var($raw['checked'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $text = isset($raw['text']) && $raw['text'] !== '' ? (string) $raw['text'] : null;

        if (! $checked && $text !== null) {
            $failValidator = Validator::make([], []);
            $failValidator->errors()->add('text', 'text must be null when checked is false.');
            throw new ValidationException($failValidator);
        }

        $payload['json_value'] = ['checked' => $checked, 'text' => $text];

        return $payload;
    }
}
