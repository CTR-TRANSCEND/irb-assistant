<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Export;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\TemplateVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Generates DOCX exports for HRP-503c (Phase 4 PR-1) submissions.
 *
 * Retrofits DocxExportService for the Submission model. Uses the bundled
 * template identified by form_code, fills SDT controls keyed by question_key
 * (or docx_field_alias where available), and writes an exports row.
 *
 * Phase 4 PR-1 scope: basic text/textarea/radio/checkbox types.
 * Complex types (subfields, scenarios) serialized as JSON in the SDT.
 *
 * REQ-IRB-FORMSV2-061..070
 * SPEC-IRB-FORMSV2-004 §B.3
 *
 * @MX:ANCHOR: [AUTO] generate() is the single DOCX generation entry point for Submission exports.
 *
 * @MX:REASON: fan_in >= 3 — SubmissionExportController::store(), SubmissionExportTest, AnalyzeSubmissionJob (future).
 *
 * @MX:WARN: [AUTO] Uses Symfony Process (unzip + zip) as external subprocess — blocking I/O.
 *
 * @MX:REASON: DOCX is a ZIP container; PHP's ZipArchive produces corrupt output when large XML
 *             files are modified. Subprocess unzip/zip is the reliable alternative (mirrors DocxExportService).
 */
class SubmissionDocxExportService
{
    public function __construct(private readonly TemplateService $templateService) {}

    public function generate(Submission $submission, int $actorUserId): Export
    {
        $formCode = $submission->formDefinition->form_code;

        // Normalize form_code to the TemplateService key convention
        $templateKey = strtolower(str_replace('-', '', $formCode)); // HRP-503c → hrp503c

        $template = TemplateVersion::query()
            ->where('name', $formCode)
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->first();

        if ($template === null) {
            // Ensure template is installed (idempotent)
            $template = $this->templateService->ensureTemplateForFormCode($templateKey, $actorUserId);
        }

        $export = Export::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => null,
            'submission_id' => $submission->id,
            'template_version_id' => $template->id,
            'created_by_user_id' => $actorUserId,
            'status' => 'generating',
            'storage_disk' => 'local',
        ]);

        $tmpDirAbs = null;

        try {
            $outputPath = $this->generateDocx($submission, $template, $export->uuid, $tmpDirAbs);

            $export->update([
                'status' => 'ready',
                'storage_path' => $outputPath,
                'is_encrypted' => false,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($tmpDirAbs !== null && is_dir($tmpDirAbs)) {
                File::deleteDirectory($tmpDirAbs);
            }
        }

        return $export;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @MX:WARN: [AUTO] Cyclomatic complexity >= 10 — nested loops over answers + SDT controls.
     *
     * @MX:REASON: DOCX XML manipulation requires matching answers to SDT controls by question_key/alias.
     */
    private function generateDocx(Submission $submission, TemplateVersion $template, string $exportUuid, ?string &$tmpDirAbsOut = null): string
    {
        $templateAbs = Storage::disk($template->storage_disk)->path($template->storage_path);
        $tmpDirRel = 'tmp/export_'.$exportUuid;
        $tmpDirAbs = Storage::disk('local')->path($tmpDirRel);
        $tmpDirAbsOut = $tmpDirAbs;

        if (! is_dir($tmpDirAbs) && ! mkdir($tmpDirAbs, 0700, true) && ! is_dir($tmpDirAbs)) {
            throw new \RuntimeException('Failed to create temp dir');
        }

        $unzip = new Process(['unzip', '-q', $templateAbs, '-d', $tmpDirAbs]);
        $unzip->setTimeout(60);
        $unzip->mustRun();

        // Build question_key → answer value map for SDT filling.
        // HRP-503 uses the Phase 5 map which applies type-specific serialization
        // and excludes trigger-locked section answers (S-P5-10).
        $answers = $submission->answers->keyBy('question_key');
        $formCode = $submission->formDefinition->form_code;
        $questionKeyToValue = $formCode === 'HRP-503'
            ? $this->buildValueMapPhase5($answers, $submission)
            : $this->buildValueMap($answers);

        // Fill the main document XML
        $documentXml = $tmpDirAbs.'/word/document.xml';
        if (is_file($documentXml)) {
            $this->fillSdtsByQuestionKey($documentXml, $questionKeyToValue, $submission);
        }

        // Set document properties (REQ-IRB-FORMSV2-069)
        $this->writeDocumentProperties(
            $tmpDirAbs,
            title: $submission->study?->application_title ?? $submission->title ?? '',
            author: $submission->study?->pi_name ?? $submission->principal_investigator ?? '',
        );

        $outRel = "exports/submission_{$submission->id}/{$exportUuid}.docx";
        $outAbs = Storage::disk('local')->path($outRel);
        $outDir = dirname($outAbs);

        if (! is_dir($outDir) && ! mkdir($outDir, 0700, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('Failed to create export directory');
        }

        $zip = new Process(['zip', '-qr', $outAbs, '.']);
        $zip->setWorkingDirectory($tmpDirAbs);
        $zip->setTimeout(60);
        $zip->mustRun();

        return $outRel;
    }

    /**
     * Build question_key → printable string map from submission answers.
     *
     * Phase 5: honor cross-section trigger visibility — locked sections are excluded
     * from the export per REQ-P5-006 / S-P5-10.
     *
     * @param  \Illuminate\Support\Collection<string, SubmissionAnswer>  $answers
     * @return array<string, string>
     *
     * @MX:NOTE: [AUTO] Phase 5 extension: SectionTriggerEvaluator filters locked-section answers before building the map.
     */
    private function buildValueMap(\Illuminate\Support\Collection $answers): array
    {
        $map = [];

        foreach ($answers as $questionKey => $answer) {
            if ($answer->text_value !== null) {
                $map[$questionKey] = $answer->text_value;
            } elseif ($answer->option_value !== null) {
                $map[$questionKey] = $answer->option_value;
            } elseif ($answer->bool_value !== null) {
                $map[$questionKey] = $answer->bool_value ? 'Yes' : 'No';
            } elseif ($answer->json_value !== null) {
                // Complex types: serialize as JSON for Phase 4 PR-1; Phase 5 refines.
                $map[$questionKey] = json_encode($answer->json_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
            }
        }

        return $map;
    }

    /**
     * Build a question_key → printable-string map that applies Phase 5 type-specific
     * serialization and excludes answers belonging to trigger-locked sections.
     *
     * Called by generateDocx() for HRP-503 submissions; HRP-503c falls back to
     * the existing buildValueMap() path (no section triggers in that form).
     *
     * @param  \Illuminate\Support\Collection<string, SubmissionAnswer>  $answers
     * @return array<string, string>
     *
     * @MX:ANCHOR: [AUTO] buildValueMapPhase5() is the DOCX serialization entry point for HRP-503 (all 15 types).
     *
     * @MX:REASON: fan_in >= 3 — generateDocx(), Hrp503DocxExportTest, and S-P5-10 acceptance scenario.
     *
     * @MX:WARN: [AUTO] Cyclomatic complexity >= 10 — match over 7 new types + section-visibility filter.
     *
     * @MX:REASON: Each Phase 5 type requires a distinct serialization; cannot collapse without losing human-readable output.
     */
    private function buildValueMapPhase5(\Illuminate\Support\Collection $answers, Submission $submission): array
    {
        // Build visibility map: section_code → bool
        $formDef = $submission->formDefinition;
        $formDef->loadMissing(['sections.questions']);

        // Compute answer values for trigger questions (needed before evaluating visibility)
        $allAnswerValues = $this->extractTriggerAnswerValues($answers);

        // Map question_key → section_code for locked-section filtering
        $questionToSection = [];
        foreach ($formDef->sections as $section) {
            foreach ($section->questions as $q) {
                $questionToSection[$q->question_key] = $section->section_code;
            }
        }

        $sectionVisibility = SectionTriggerEvaluator::buildSectionVisibilityMap(
            $formDef->sections,
            $allAnswerValues,
        );

        $map = [];

        foreach ($answers as $questionKey => $answer) {
            // S-P5-10: skip answers belonging to locked sections
            $sectionCode = $questionToSection[$questionKey] ?? null;
            if ($sectionCode !== null && isset($sectionVisibility[$sectionCode]) && ! $sectionVisibility[$sectionCode]) {
                continue;
            }

            $serialized = $this->serializeAnswerPhase5($answer);
            if ($serialized !== null) {
                $map[$questionKey] = $serialized;
            }
        }

        return $map;
    }

    /**
     * Serialize a single SubmissionAnswer to a printable string, applying Phase 5
     * type-specific formatting when json_value is present and structured.
     *
     * @return string|null null if the answer has no value
     */
    private function serializeAnswerPhase5(SubmissionAnswer $answer): ?string
    {
        if ($answer->text_value !== null) {
            return $answer->text_value;
        }

        if ($answer->option_value !== null) {
            return $answer->option_value;
        }

        if ($answer->bool_value !== null) {
            return $answer->bool_value ? 'Yes' : 'No';
        }

        if ($answer->json_value !== null) {
            return $this->serializeJsonValue($answer->json_value);
        }

        return null;
    }

    /**
     * Serialize a json_value payload into a human-readable string.
     *
     * Handles all Phase 5 JSON-shaped types:
     * - checkbox_multi_with_section_triggers: one label per line (from option labels)
     * - numbered_options_with_criteria: one value per line
     * - textarea_with_na_and_followup: "N/A" or text + optional followup
     * - textarea_with_alternative_radio: text or radio value
     * - checkbox_with_optional_textarea: checked status + optional text
     * - Fallback: compact JSON for unknown shapes
     */
    private function serializeJsonValue(mixed $jsonValue): string
    {
        if (! is_array($jsonValue)) {
            return json_encode($jsonValue, JSON_UNESCAPED_UNICODE) ?: '';
        }

        // Simple indexed array (checkbox_multi or numbered_options_with_criteria)
        if (array_is_list($jsonValue)) {
            return implode("\n", array_map('strval', $jsonValue));
        }

        // textarea_with_na_and_followup: {na, text, followup}
        if (array_key_exists('na', $jsonValue)) {
            $na = filter_var($jsonValue['na'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($na) {
                return 'N/A';
            }
            $parts = array_filter([
                isset($jsonValue['text']) && $jsonValue['text'] !== null ? (string) $jsonValue['text'] : null,
                isset($jsonValue['followup']) && $jsonValue['followup'] !== null ? 'Follow-up: '.(string) $jsonValue['followup'] : null,
            ]);

            return implode("\n", $parts) ?: '';
        }

        // textarea_with_alternative_radio: {mode, text, radio}
        if (array_key_exists('mode', $jsonValue)) {
            $mode = (string) ($jsonValue['mode'] ?? 'text');
            if ($mode === 'radio') {
                return (string) ($jsonValue['radio'] ?? '');
            }

            return (string) ($jsonValue['text'] ?? '');
        }

        // checkbox_with_optional_textarea: {checked, text}
        if (array_key_exists('checked', $jsonValue)) {
            $checked = filter_var($jsonValue['checked'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $text = isset($jsonValue['text']) && $jsonValue['text'] !== null ? (string) $jsonValue['text'] : null;
            if ($checked) {
                return $text !== null ? "Yes\n{$text}" : 'Yes';
            }

            return 'No';
        }

        // Fallback: compact JSON
        return json_encode($jsonValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Extract trigger question answer values from the answers collection.
     *
     * @param  \Illuminate\Support\Collection<string, SubmissionAnswer>  $answers
     * @return array<string, mixed>
     */
    private function extractTriggerAnswerValues(\Illuminate\Support\Collection $answers): array
    {
        $values = [];

        foreach ($answers as $qKey => $answer) {
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
     * Walk the document XML and fill SDT controls whose alias/tag matches a question_key.
     *
     * Phase 4 PR-1: SDT tag name = question_key (minimal mapping).
     *
     * @param  array<string, string>  $questionKeyToValue
     */
    private function fillSdtsByQuestionKey(string $xmlPath, array $questionKeyToValue, Submission $submission): void
    {
        if (count($questionKeyToValue) === 0) {
            return;
        }

        $xml = file_get_contents($xmlPath);
        if ($xml === false) {
            return;
        }

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;

        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $sdts = $xpath->query('//w:sdt');
        if ($sdts === false) {
            return;
        }

        foreach ($sdts as $sdt) {
            if (! ($sdt instanceof \DOMElement)) {
                continue;
            }

            // Resolve SDT tag name: prefer w:sdtPr/w:alias[@w:val] or w:sdtPr/w:tag[@w:val]
            $alias = $xpath->evaluate('string(w:sdtPr/w:alias/@w:val)', $sdt);
            $tag = $xpath->evaluate('string(w:sdtPr/w:tag/@w:val)', $sdt);
            $key = ($alias !== '' && $alias !== false) ? $alias : $tag;

            if (! is_string($key) || $key === '' || ! isset($questionKeyToValue[$key])) {
                continue;
            }

            $this->setSdtText($xpath, $dom, $sdt, $questionKeyToValue[$key]);
        }

        $dom->formatOutput = false;
        $result = $dom->saveXML();
        if ($result !== false) {
            file_put_contents($xmlPath, $result);
        }
    }

    private function setSdtText(\DOMXPath $xpath, \DOMDocument $dom, \DOMElement $sdt, string $value): void
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = explode("\n", $value);

        $contents = $xpath->query('w:sdtContent', $sdt);
        if ($contents === false || $contents->length === 0) {
            return;
        }

        $content = $contents->item(0);
        if (! ($content instanceof \DOMElement)) {
            return;
        }

        // Clear existing content
        while ($content->firstChild !== null) {
            $content->removeChild($content->firstChild);
        }

        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        foreach ($lines as $lineIdx => $line) {
            $para = $dom->createElementNS($ns, 'w:p');
            $run = $dom->createElementNS($ns, 'w:r');
            $text = $dom->createElementNS($ns, 'w:t');
            $text->setAttribute('xml:space', 'preserve');
            $text->textContent = $line;
            $run->appendChild($text);
            $para->appendChild($run);
            $content->appendChild($para);
        }
    }

    private function writeDocumentProperties(string $tmpDirAbs, string $title, string $author): void
    {
        $corePath = $tmpDirAbs.'/docProps/core.xml';
        if (! is_file($corePath)) {
            return;
        }

        $xml = file_get_contents($corePath);
        if ($xml === false) {
            return;
        }

        // Replace or inject dc:title and dc:creator
        $title = htmlspecialchars($title, ENT_XML1, 'UTF-8');
        $author = htmlspecialchars($author, ENT_XML1, 'UTF-8');

        $xml = preg_replace('/<dc:title>[^<]*<\/dc:title>/', "<dc:title>{$title}</dc:title>", $xml) ?? $xml;
        $xml = preg_replace('/<dc:creator>[^<]*<\/dc:creator>/', "<dc:creator>{$author}</dc:creator>", $xml) ?? $xml;

        file_put_contents($corePath, $xml);
    }
}
