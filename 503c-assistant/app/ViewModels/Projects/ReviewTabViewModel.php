<?php

namespace App\ViewModels\Projects;

use App\Models\Project;
use App\Models\ProjectFieldValue;
use Illuminate\Support\Collection;

class ReviewTabViewModel
{
    public function __construct(
        private Project $project,
        private ?ProjectFieldValue $selected = null
    ) {
    }

    /**
     * Prepare data for the review field list component.
     *
     * @param  Collection<int, ProjectFieldValue>  $fieldValues
     * @return array{fieldValues: Collection, stats: array}
     */
    public function getFieldListData(Collection $fieldValues): array
    {
        // Calculate statistics
        $stats = [
            'total' => $fieldValues->count(),
            'missing' => $fieldValues->where('status', 'missing')->count(),
            'suggested' => $fieldValues->where('status', 'suggested')->count(),
            'edited' => $fieldValues->where('status', 'edited')->count(),
            'confirmed' => $fieldValues->where('status', 'confirmed')->count(),
        ];

        // Prepare field values with computed properties
        $preparedFieldValues = $fieldValues->map(function (ProjectFieldValue $fv) {
            $label = (string) ($fv->field->label ?? $fv->field->key ?? '');
            $key = (string) ($fv->field->key ?? '');

            return [
                'id' => $fv->id,
                'label' => $label,
                'key' => $key,
                'search_text' => strtolower($key.' '.$label),
                'is_selected' => $this->selected && $this->selected->id === $fv->id,
                'badge_class' => match($fv->status) {
                    'confirmed' => 'bg-green-50 text-green-700 border-green-200',
                    'edited' => 'bg-amber-50 text-amber-800 border-amber-200',
                    'suggested' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                },
                'status' => $fv->status,
                'evidence_count' => $fv->evidence_count ?? 0,
            ];
        });

        return [
            'fieldValues' => $preparedFieldValues,
            'stats' => $stats,
        ];
    }

    /**
     * Prepare data for the review field editor component.
     *
     * @return array{label: string, key: string, suggested_value: string|null, final_value: string|null, show_suggested: bool, show_final: bool, is_edited: bool, status: string, confidence: string|null, evidence_count: int}
     */
    public function getFieldEditorData(): array
    {
        if (! $this->selected) {
            return $this->getEmptyFieldEditorData();
        }

        $label = (string) ($this->selected->field->label ?? $this->selected->field->key ?? '');
        $key = (string) ($this->selected->field->key ?? '');
        $suggestedValue = $this->selected->suggested_value;
        $finalValue = $this->selected->final_value;

        $showSuggested = $suggestedValue !== null && trim($suggestedValue) !== '';
        $showFinal = $finalValue !== null && trim($finalValue) !== '';
        $isEdited = $showFinal && $showSuggested && trim((string) $finalValue) !== trim((string) $suggestedValue);

        $confidence = null;
        if ($this->selected->confidence !== null) {
            $confidence = number_format($this->selected->confidence, 2);
        }

        return [
            'id' => $this->selected->id,
            'label' => $label,
            'key' => $key,
            'suggested_value' => $suggestedValue,
            'final_value' => $finalValue,
            'show_suggested' => $showSuggested,
            'show_final' => $showFinal,
            'is_edited' => $isEdited,
            'status' => strtoupper($this->selected->status),
            'confidence' => $confidence,
            'evidence_count' => $this->selected->evidence->count(),
        ];
    }

    /**
     * Prepare data for the review evidence viewer component.
     *
     * @param  int|null  $activeEvidenceId  The active evidence ID from query parameter
     * @return array{has_evidence: bool, evidence: Collection, active_evidence: mixed, active_chunk: mixed, active_document: mixed, highlighted_chunk: string, quote_mismatch: bool}
     */
    public function getEvidenceViewerData(?int $activeEvidenceId = null): array
    {
        if (! $this->selected) {
            return $this->getEmptyEvidenceViewerData();
        }

        $evidenceCollection = $this->selected->evidence;

        if ($evidenceCollection->isEmpty()) {
            return [
                'has_evidence' => false,
                'evidence' => collect(),
                'active_evidence' => null,
                'active_chunk' => null,
                'active_document' => null,
                'highlighted_chunk' => '',
                'quote_mismatch' => false,
            ];
        }

        // Resolve active evidence
        $activeEvidence = null;
        if ($activeEvidenceId !== null) {
            $activeEvidence = $evidenceCollection->firstWhere('id', $activeEvidenceId);
        }

        if ($activeEvidence === null) {
            $activeEvidence = $evidenceCollection->first();
        }

        // Load relationships
        $activeChunk = $activeEvidence?->chunk;
        $activeDocument = $activeChunk?->document;

        // Prepare highlighted chunk text
        $chunkText = (string) ($activeChunk?->text ?? '');
        $quoteText = (string) ($activeEvidence?->excerpt_text ?? '');

        $highlightedChunk = $this->highlightChunkText($chunkText, $quoteText);
        $quoteMismatch = $quoteText !== '' && ! str_contains($chunkText, $quoteText);

        // Prepare evidence list with metadata
        $preparedEvidence = $evidenceCollection->map(function ($ev) use ($activeEvidence) {
            $docName = $ev->chunk?->document?->original_filename ?? 'Document';
            $page = $ev->chunk?->page_number;
            $chunkIndex = $ev->chunk?->chunk_index;
            $isActive = $activeEvidence && $activeEvidence->id === $ev->id;

            return [
                'id' => $ev->id,
                'doc_name' => $docName,
                'page' => $page,
                'chunk_index' => $chunkIndex,
                'excerpt' => \Illuminate\Support\Str::limit((string) $ev->excerpt_text, 180),
                'is_active' => $isActive,
            ];
        });

        return [
            'has_evidence' => true,
            'evidence' => $preparedEvidence,
            'active_evidence' => $activeEvidence,
            'active_chunk' => $activeChunk,
            'active_document' => $activeDocument,
            'highlighted_chunk' => $highlightedChunk,
            'quote_mismatch' => $quoteMismatch,
        ];
    }

    /**
     * Highlight quote text within chunk text using HTML mark tags.
     *
     * @param  string  $chunkText  The chunk text to search within
     * @param  string  $quoteText  The quote text to highlight
     * @return string The chunk text with HTML highlighting
     */
    private function highlightChunkText(string $chunkText, string $quoteText): string
    {
        $chunkEsc = e($chunkText);
        $quoteEsc = e($quoteText);

        if ($quoteEsc === '' || ! str_contains($chunkEsc, $quoteEsc)) {
            return $chunkEsc;
        }

        return str_replace(
            $quoteEsc,
            '<mark class="bg-yellow-100">'.$quoteEsc.'</mark>',
            $chunkEsc
        );
    }

    /**
     * Get empty field editor data structure.
     *
     * @return array
     */
    private function getEmptyFieldEditorData(): array
    {
        return [
            'id' => 0,
            'label' => '',
            'key' => '',
            'suggested_value' => null,
            'final_value' => null,
            'show_suggested' => false,
            'show_final' => false,
            'is_edited' => false,
            'status' => '',
            'confidence' => null,
            'evidence_count' => 0,
        ];
    }

    /**
     * Get empty evidence viewer data structure.
     *
     * @return array
     */
    private function getEmptyEvidenceViewerData(): array
    {
        return [
            'has_evidence' => false,
            'evidence' => collect(),
            'active_evidence' => null,
            'active_chunk' => null,
            'active_document' => null,
            'highlighted_chunk' => '',
            'quote_mismatch' => false,
        ];
    }
}
