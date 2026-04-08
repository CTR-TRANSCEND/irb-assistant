<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FieldDefinition;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use App\Services\AuditService;
use App\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminTemplateController extends Controller
{
    public function store(Request $request, TemplateService $templates, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'template' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('template');
        if ($file === null || ! $file->isValid()) {
            return back()->withErrors(['template' => 'Invalid upload']);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'docx') {
            return back()->withErrors(['template' => 'Template must be a .docx']);
        }

        $storageDisk = 'local';
        $tmpPath = $file->storeAs('templates/uploads', (string) Str::uuid().'.docx', $storageDisk);
        if ($tmpPath === false) {
            throw new \RuntimeException('Failed to store template upload');
        }

        $abs = Storage::disk($storageDisk)->path($tmpPath);
        $sha256 = hash_file('sha256', $abs);
        if ($sha256 === false) {
            throw new \RuntimeException('Failed to hash template upload');
        }

        $existing = TemplateVersion::query()->where('sha256', $sha256)->first();
        if ($existing !== null) {
            // Ensure we scanned and seeded.
            $templates->scanControls($existing);
            $templates->seedFieldDefinitionsFromControls($existing, createMappings: false);

            $audit->log(
                request: $request,
                eventType: 'admin.template.uploaded_duplicate',
                project: null,
                entityType: 'template_version',
                entityId: $existing->id,
                entityUuid: $existing->uuid,
                payload: ['original_filename' => $file->getClientOriginalName()],
            );

            return redirect()->route('admin.index', ['tab' => 'templates'])->with('status', 'Template already exists; reused.');
        }

        $previousActive = TemplateVersion::query()->where('is_active', true)->orderByDesc('created_at')->first();

        $finalPath = "templates/hrp503c/{$sha256}.docx";
        Storage::disk($storageDisk)->move($tmpPath, $finalPath);

        $tpl = TemplateVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => (string) ($request->input('name') ?: 'HRP-503c'),
            'sha256' => $sha256,
            'storage_disk' => $storageDisk,
            'storage_path' => $finalPath,
            'is_active' => false,
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $templates->scanControls($tpl);
        $templates->seedFieldDefinitionsFromControls($tpl, createMappings: false);

        $autoMapped = 0;
        $autoMapTotal = 0;
        if ($previousActive !== null) {
            [$autoMapped, $autoMapTotal] = $this->copyMappingsBySignature($previousActive, $tpl, $request->user()->id);
        }

        $audit->log(
            request: $request,
            eventType: 'admin.template.uploaded',
            project: null,
            entityType: 'template_version',
            entityId: $tpl->id,
            entityUuid: $tpl->uuid,
            payload: ['original_filename' => $file->getClientOriginalName()],
        );

        $msg = 'Template uploaded.';
        if ($autoMapTotal > 0) {
            $msg .= " Auto-mapped {$autoMapped}/{$autoMapTotal} controls from active template.";
        }

        return redirect()->route('admin.templates.show', ['template' => $tpl->uuid])->with('status', $msg);
    }

    public function show(Request $request, TemplateVersion $template): View
    {
        $parts = TemplateControl::query()
            ->where('template_version_id', $template->id)
            ->select('part')
            ->distinct()
            ->pluck('part')
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->values()
            ->all();

        $part = (string) $request->query('part', 'document');
        if (! in_array($part, $parts, true)) {
            $part = in_array('document', $parts, true) ? 'document' : (string) ($parts[0] ?? 'document');
        }

        $onlyFillable = $request->query('only_fillable') === '1';
        $onlyUnmapped = $request->query('only_unmapped') === '1';

        $controls = TemplateControl::query()
            ->where('template_version_id', $template->id)
            ->where('part', $part)
            ->orderBy('control_index')
            ->get();

        $controlCounts = TemplateControl::query()
            ->where('template_version_id', $template->id)
            ->selectRaw('part, COUNT(*) as c')
            ->groupBy('part')
            ->pluck('c', 'part');

        $mappedCounts = TemplateControlMapping::query()
            ->where('template_control_mappings.template_version_id', $template->id)
            ->join('template_controls', 'template_control_mappings.template_control_id', '=', 'template_controls.id')
            ->selectRaw('template_controls.part as part, COUNT(*) as c')
            ->groupBy('template_controls.part')
            ->pluck('c', 'part');

        $partStats = [];
        foreach ($parts as $p) {
            $p = (string) $p;
            $total = (int) ($controlCounts[$p] ?? 0);
            $mapped = (int) ($mappedCounts[$p] ?? 0);
            $partStats[] = [
                'part' => $p,
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => max(0, $total - $mapped),
            ];
        }

        $drift = null;
        $driftDetails = null;
        $driftStatusByControlId = [];
        $base = TemplateVersion::query()
            ->where('is_active', true)
            ->where('id', '!=', $template->id)
            ->orderByDesc('created_at')
            ->first();

        if ($base !== null) {
            $a = TemplateControl::query()
                ->where('template_version_id', $template->id)
                ->pluck('signature_sha256')
                ->filter(fn ($s) => is_string($s) && $s !== '')
                ->unique()
                ->values()
                ->all();

            $b = TemplateControl::query()
                ->where('template_version_id', $base->id)
                ->pluck('signature_sha256')
                ->filter(fn ($s) => is_string($s) && $s !== '')
                ->unique()
                ->values()
                ->all();

            $aSet = array_fill_keys($a, true);
            $bSet = array_fill_keys($b, true);

            $overlap = 0;
            foreach ($aSet as $sig => $_) {
                if (isset($bSet[$sig])) {
                    $overlap++;
                }
            }

            $drift = [
                'base_template_uuid' => $base->uuid,
                'base_template_name' => $base->name,
                'this_unique' => count($aSet),
                'base_unique' => count($bSet),
                'overlap' => $overlap,
                'this_only' => max(0, count($aSet) - $overlap),
                'base_only' => max(0, count($bSet) - $overlap),
            ];

            $thisPartControls = TemplateControl::query()
                ->where('template_version_id', $template->id)
                ->where('part', $part)
                ->orderBy('control_index')
                ->get(['id', 'control_index', 'signature_sha256', 'context_before', 'context_after', 'placeholder_text']);

            $basePartControls = TemplateControl::query()
                ->where('template_version_id', $base->id)
                ->where('part', $part)
                ->orderBy('control_index')
                ->get(['id', 'control_index', 'signature_sha256', 'context_before', 'context_after', 'placeholder_text']);

            $baseSigSet = [];
            foreach ($basePartControls as $bc) {
                $sig = $bc->signature_sha256;
                if (is_string($sig) && $sig !== '') {
                    $baseSigSet[$sig] = true;
                }
            }

            $thisSigSet = [];
            foreach ($thisPartControls as $tc) {
                $sig = $tc->signature_sha256;
                if (is_string($sig) && $sig !== '') {
                    $thisSigSet[$sig] = true;
                }
            }

            $matchedControls = [];
            $thisOnlyControls = [];
            foreach ($thisPartControls as $tc) {
                $sig = $tc->signature_sha256;
                $sig = is_string($sig) ? $sig : '';

                $item = [
                    'control_index' => (int) $tc->control_index,
                    'signature_sha256' => $sig,
                    'placeholder_text' => Str::limit((string) ($tc->placeholder_text ?? ''), 140),
                    'context_before' => Str::limit((string) ($tc->context_before ?? ''), 140),
                    'context_after' => Str::limit((string) ($tc->context_after ?? ''), 140),
                ];

                if ($sig !== '' && isset($baseSigSet[$sig])) {
                    $matchedControls[] = $item;
                } else {
                    $thisOnlyControls[] = $item;
                }
            }

            $baseOnlyControls = [];
            foreach ($basePartControls as $bc) {
                $sig = $bc->signature_sha256;
                $sig = is_string($sig) ? $sig : '';
                if ($sig !== '' && isset($thisSigSet[$sig])) {
                    continue;
                }

                $baseOnlyControls[] = [
                    'control_index' => (int) $bc->control_index,
                    'signature_sha256' => $sig,
                    'placeholder_text' => Str::limit((string) ($bc->placeholder_text ?? ''), 140),
                    'context_before' => Str::limit((string) ($bc->context_before ?? ''), 140),
                    'context_after' => Str::limit((string) ($bc->context_after ?? ''), 140),
                ];
            }

            $driftDetails = [
                'part' => $part,
                'base_template_uuid' => $base->uuid,
                'base_template_name' => $base->name,
                'matched_controls' => $matchedControls,
                'this_only_controls' => $thisOnlyControls,
                'base_only_controls' => $baseOnlyControls,
            ];

            foreach ($controls as $c) {
                $sig = $c->signature_sha256;
                $sig = is_string($sig) ? $sig : '';
                if ($sig !== '' && isset($baseSigSet[$sig])) {
                    $driftStatusByControlId[(int) $c->id] = 'matched';
                } else {
                    $driftStatusByControlId[(int) $c->id] = 'new';
                }
            }
        }

        $fields = FieldDefinition::query()->orderBy('sort_order')->get();

        $mappings = TemplateControlMapping::query()
            ->where('template_version_id', $template->id)
            ->get()
            ->keyBy('template_control_id');

        $controls = $controls->filter(function (TemplateControl $c) use ($onlyFillable, $onlyUnmapped, $mappings): bool {
            if ($onlyUnmapped && $mappings->has($c->id)) {
                return false;
            }

            if (! $onlyFillable) {
                return true;
            }

            return $this->isLikelyFillable($c);
        })->values();

        $suggestionsByControlId = [];
        $fieldSearch = [];
        foreach ($fields as $f) {
            $fieldSearch[(int) $f->id] = $this->normalizeSimilarityText(
                trim(implode(' ', [
                    (string) ($f->key ?? ''),
                    (string) ($f->label ?? ''),
                    (string) ($f->question_text ?? ''),
                    (string) ($f->section ?? ''),
                ]))
            );
        }

        foreach ($controls as $c) {
            if ($mappings->has($c->id)) {
                continue;
            }

            $label = $this->guessControlLabel($c->context_before, $c->context_after, (int) $c->control_index);
            $controlSearch = $this->normalizeSimilarityText(
                trim(implode(' ', [
                    $label,
                    (string) ($c->placeholder_text ?? ''),
                    (string) ($c->context_before ?? ''),
                    (string) ($c->context_after ?? ''),
                ]))
            );

            $scored = [];
            foreach ($fields as $f) {
                $fText = $fieldSearch[(int) $f->id] ?? '';
                $score = $this->similarityScore($controlSearch, $fText);
                $scored[] = [
                    'field_definition_id' => (int) $f->id,
                    'key' => (string) ($f->key ?? ''),
                    'label' => (string) ($f->label ?? ''),
                    'score' => $score,
                ];
            }

            usort($scored, function (array $a, array $b): int {
                $as = (float) ($a['score'] ?? 0);
                $bs = (float) ($b['score'] ?? 0);
                if ($as === $bs) {
                    return 0;
                }
                return $as > $bs ? -1 : 1;
            });

            $top = array_slice($scored, 0, 3);
            $hasSignal = $controlSearch !== '';
            if ($hasSignal) {
                $suggestionsByControlId[(int) $c->id] = $top;
            }
        }

        return view('admin.template-controls', [
            'template' => $template,
            'controls' => $controls,
            'fields' => $fields,
            'mappings' => $mappings,
            'part' => $part,
            'parts' => $parts,
            'partStats' => $partStats,
            'drift' => $drift,
            'driftDetails' => $driftDetails,
            'driftStatusByControlId' => $driftStatusByControlId,
            'suggestionsByControlId' => $suggestionsByControlId,
            'onlyFillable' => $onlyFillable,
            'onlyUnmapped' => $onlyUnmapped,
        ]);
    }

    private function normalizeSimilarityText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function tokens(string $normalizedValue): array
    {
        if ($normalizedValue === '') {
            return [];
        }

        static $stop = null;
        if ($stop === null) {
            $stop = array_fill_keys([
                'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'have', 'if', 'in', 'into', 'is',
                'it', 'its', 'may', 'of', 'on', 'or', 'that', 'the', 'their', 'this', 'to', 'was', 'were', 'will', 'with',
                'yes', 'no', 'not',
            ], true);
        }

        $parts = explode(' ', $normalizedValue);
        $out = [];
        $seen = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || isset($stop[$p])) {
                continue;
            }
            if (strlen($p) < 2) {
                continue;
            }
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $out[] = $p;
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    private function similarityScore(string $a, string $b): float
    {
        $a = $this->normalizeSimilarityText($a);
        $b = $this->normalizeSimilarityText($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        $aTokens = $this->tokens($a);
        $bTokens = $this->tokens($b);

        $tokenScore = 0.0;
        if (count($aTokens) > 0 && count($bTokens) > 0) {
            $bSet = array_fill_keys($bTokens, true);
            $hit = 0;
            foreach ($aTokens as $t) {
                if (isset($bSet[$t])) {
                    $hit++;
                }
            }
            $tokenScore = (2.0 * $hit / (count($aTokens) + count($bTokens))) * 60.0;
        }

        $aShort = mb_substr($a, 0, 400);
        $bShort = mb_substr($b, 0, 400);
        $pct = 0.0;
        similar_text($aShort, $bShort, $pct);
        $charScore = ($pct / 100.0) * 40.0;

        return round($tokenScore + $charScore, 1);
    }

    private function guessControlLabel(?string $before, ?string $after, int $index): string
    {
        $ctx = trim((string) $before);
        if ($ctx === '') {
            $ctx = trim((string) $after);
        }

        if ($ctx !== '') {
            $ctx = preg_replace('/\s+/', ' ', $ctx) ?? $ctx;
            $matches = [];
            preg_match_all('/([A-Z][A-Z0-9 \-\/()]{2,80})\s*:\s*/', $ctx, $matches);
            $labels = $matches[1] ?? [];
            if (is_array($labels) && count($labels) > 0) {
                $label = trim((string) end($labels));
                $label = preg_replace('/\s+/', ' ', $label) ?? $label;
                $label = trim($label);
                if ($label !== '' && strlen($label) >= 3) {
                    return $label;
                }
            }
        }

        $candidate = trim((string) $before);
        $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
        if ($candidate !== '') {
            return $this->tail($candidate, 80);
        }

        $afterCandidate = trim((string) $after);
        $afterCandidate = preg_replace('/\s+/', ' ', $afterCandidate) ?? $afterCandidate;
        if ($afterCandidate !== '') {
            return $this->head($afterCandidate, 80);
        }

        return 'Field '.$index;
    }

    private function head(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }

    private function tail(string $s, int $max): string
    {
        $len = mb_strlen($s);
        if ($len <= $max) {
            return $s;
        }

        return mb_substr($s, $len - $max, $max);
    }

    public function activate(Request $request, TemplateVersion $template, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        TemplateVersion::query()->where('id', '!=', $template->id)->update(['is_active' => false]);
        $template->update(['is_active' => true]);

        $audit->log(
            request: $request,
            eventType: 'admin.template.activated',
            project: null,
            entityType: 'template_version',
            entityId: $template->id,
            entityUuid: $template->uuid,
            payload: [],
        );

        return redirect()->route('admin.templates.show', ['template' => $template->uuid])->with('status', 'Template activated.');
    }

    public function saveMappings(Request $request, TemplateVersion $template, AuditService $audit): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['array'],
            'mapping.*.*' => ['nullable', 'string'],
            'part' => ['nullable', 'string'],
        ]);

        $mappingByPart = $data['mapping'];

        $parts = TemplateControl::query()
            ->where('template_version_id', $template->id)
            ->select('part')
            ->distinct()
            ->pluck('part')
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->values()
            ->all();

        $part = (string) ($data['part'] ?? 'document');
        if (! in_array($part, $parts, true)) {
            $part = in_array('document', $parts, true) ? 'document' : (string) ($parts[0] ?? 'document');
        }

        $controls = TemplateControl::query()
            ->where('template_version_id', $template->id)
            ->where('part', $part)
            ->get(['id', 'control_index', 'part']);

        $controlByIndex = $controls->keyBy(fn ($c) => (string) $c->control_index);

        $updated = 0;

        $mapping = $mappingByPart[$part] ?? [];
        if (! is_array($mapping)) {
            $mapping = [];
        }

        foreach ($mapping as $controlIndex => $fieldIdRaw) {
            $ctrl = $controlByIndex->get((string) $controlIndex);
            if ($ctrl === null) {
                continue;
            }

            $fieldId = null;
            if (is_string($fieldIdRaw) && $fieldIdRaw !== '' && ctype_digit($fieldIdRaw)) {
                $fieldId = (int) $fieldIdRaw;
            }

            if ($fieldId === null) {
                TemplateControlMapping::query()->where('template_control_id', $ctrl->id)->delete();
                continue;
            }

            TemplateControlMapping::query()->updateOrCreate(
                ['template_control_id' => $ctrl->id],
                [
                    'template_version_id' => $template->id,
                    'field_definition_id' => $fieldId,
                    'mapped_by_user_id' => $request->user()->id,
                ],
            );
            $updated++;
        }

        $audit->log(
            request: $request,
            eventType: 'admin.template.mappings_saved',
            project: null,
            entityType: 'template_version',
            entityId: $template->id,
            entityUuid: $template->uuid,
            payload: ['updated' => $updated],
        );

        return redirect()->route('admin.templates.show', [
            'template' => $template->uuid,
            'part' => $part,
            'only_fillable' => $request->query('only_fillable'),
            'only_unmapped' => $request->query('only_unmapped'),
        ])->with('status', 'Mappings saved.');
    }

    private function isLikelyFillable(TemplateControl $c): bool
    {
        $text = (string) ($c->placeholder_text ?? '');
        $len = mb_strlen($text);
        if ($len === 0) {
            return true;
        }
        if ($len <= 120) {
            return true;
        }

        $ctx = trim((string) ($c->context_before ?? ''));
        if (preg_match('/[?:]$/', $ctx) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0:int,1:int} mapped, total
     */
    private function copyMappingsBySignature(TemplateVersion $from, TemplateVersion $to, int $actorUserId): array
    {
        $fromMappings = TemplateControlMapping::query()
            ->where('template_version_id', $from->id)
            ->with('control')
            ->get();

        $sigToField = [];
        foreach ($fromMappings as $m) {
            $sig = $m->control?->signature_sha256;
            if (! is_string($sig) || $sig === '') {
                continue;
            }
            $sigToField[$sig] = (int) $m->field_definition_id;
        }

        $toControls = TemplateControl::query()
            ->where('template_version_id', $to->id)
            ->get(['id', 'signature_sha256']);

        $sigToControlId = [];
        foreach ($toControls as $c) {
            if (! is_string($c->signature_sha256) || $c->signature_sha256 === '') {
                continue;
            }
            // first wins
            if (! isset($sigToControlId[$c->signature_sha256])) {
                $sigToControlId[$c->signature_sha256] = (int) $c->id;
            }
        }

        $total = count($sigToField);
        $mapped = 0;
        foreach ($sigToField as $sig => $fieldId) {
            $ctrlId = $sigToControlId[$sig] ?? null;
            if ($ctrlId === null) {
                continue;
            }

            TemplateControlMapping::query()->updateOrCreate(
                ['template_control_id' => $ctrlId],
                [
                    'template_version_id' => $to->id,
                    'field_definition_id' => $fieldId,
                    'mapped_by_user_id' => $actorUserId,
                ],
            );
            $mapped++;
        }

        return [$mapped, $total];
    }
}
