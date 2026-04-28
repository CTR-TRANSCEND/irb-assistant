<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FieldDefinition;
use App\Models\TemplateControl;
use App\Models\TemplateControlMapping;
use App\Models\TemplateVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TemplateService
{
    public function ensureDefaultTemplateInstalled(?int $uploadedByUserId = null): TemplateVersion
    {
        $sourcePath = base_path('resources/templates/HRP-503c.docx');
        if (! is_file($sourcePath)) {
            throw new \RuntimeException('Default template not found: '.$sourcePath);
        }

        $sha256 = hash_file('sha256', $sourcePath);
        if ($sha256 === false) {
            throw new \RuntimeException('Failed to hash default template');
        }

        $existing = TemplateVersion::query()->where('sha256', $sha256)->first();
        if ($existing !== null) {
            $this->scanControls($existing);
            $this->applyBundledMappingPack($existing);
            $this->seedFieldDefinitionsFromControls($existing, createMappings: true, onlyUnmappedControls: true);

            return $existing;
        }

        $uuid = (string) Str::uuid();
        $storageDisk = 'local';
        $storagePath = "templates/hrp503c/{$sha256}.docx";

        $bytes = file_get_contents($sourcePath);
        if ($bytes === false) {
            throw new \RuntimeException('Failed to read default template');
        }

        Storage::disk($storageDisk)->put($storagePath, $bytes);

        $tpl = TemplateVersion::query()->create([
            'uuid' => $uuid,
            'name' => 'HRP-503c',
            'sha256' => $sha256,
            'storage_disk' => $storageDisk,
            'storage_path' => $storagePath,
            'is_active' => true,
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);

        $this->scanControls($tpl);
        $this->applyBundledMappingPack($tpl);
        $this->seedFieldDefinitionsFromControls($tpl, createMappings: true, onlyUnmappedControls: true);

        return $tpl;
    }

    public function scanControls(TemplateVersion $templateVersion): void
    {
        $absPath = Storage::disk($templateVersion->storage_disk)->path($templateVersion->storage_path);

        $parts = $this->discoverParts($absPath);

        foreach ($parts as $partName => $innerPath) {
            $xml = $this->unzipPrintFile($absPath, $innerPath);
            if ($xml === '') {
                continue;
            }

            $controls = $this->extractSdtControls($xml);
            foreach ($controls as $i => $ctrl) {
                TemplateControl::query()->updateOrCreate(
                    [
                        'template_version_id' => $templateVersion->id,
                        'part' => $partName,
                        'control_index' => $i,
                    ],
                    [
                        'context_before' => $ctrl['context_before'],
                        'context_after' => $ctrl['context_after'],
                        'placeholder_text' => $ctrl['placeholder_text'],
                        'signature_sha256' => hash('sha256', implode('|', [
                            $partName,
                            (string) ($ctrl['context_before'] ?? ''),
                            (string) ($ctrl['context_after'] ?? ''),
                            (string) ($ctrl['placeholder_text'] ?? ''),
                        ])),
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, string> partName => innerPath
     */
    private function discoverParts(string $docxPath): array
    {
        $process = new Process(['unzip', '-Z1', $docxPath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return [
                'document' => 'word/document.xml',
                'endnotes' => 'word/endnotes.xml',
            ];
        }

        $out = trim((string) $process->getOutput());
        $files = $out === '' ? [] : preg_split("/\r?\n/", $out);
        if (! is_array($files)) {
            $files = [];
        }

        $parts = [];
        foreach ($files as $f) {
            $f = trim((string) $f);
            if ($f === '') {
                continue;
            }

            if ($f === 'word/document.xml') {
                $parts['document'] = $f;

                continue;
            }
            if ($f === 'word/endnotes.xml') {
                $parts['endnotes'] = $f;

                continue;
            }
            if ($f === 'word/footnotes.xml') {
                $parts['footnotes'] = $f;

                continue;
            }

            $m = [];
            if (preg_match('~^word/(header|footer)(\d+)\.xml$~', $f, $m) === 1) {
                $parts[(string) ($m[1].$m[2])] = $f;

                continue;
            }
        }

        // Ensure at least document exists.
        if (! isset($parts['document'])) {
            $parts['document'] = 'word/document.xml';
        }

        // Stable ordering for UI.
        $ordered = [];
        foreach (['document', 'endnotes', 'footnotes'] as $k) {
            if (isset($parts[$k])) {
                $ordered[$k] = $parts[$k];
                unset($parts[$k]);
            }
        }

        $headers = [];
        $footers = [];
        foreach ($parts as $k => $path) {
            if (preg_match('/^header(\d+)$/', (string) $k, $m) === 1) {
                $headers[(int) $m[1]] = $path;

                continue;
            }
            if (preg_match('/^footer(\d+)$/', (string) $k, $m) === 1) {
                $footers[(int) $m[1]] = $path;

                continue;
            }
            $ordered[(string) $k] = $path;
        }

        ksort($headers);
        foreach ($headers as $n => $path) {
            $ordered['header'.$n] = $path;
        }

        ksort($footers);
        foreach ($footers as $n => $path) {
            $ordered['footer'.$n] = $path;
        }

        return $ordered;
    }

    public function seedFieldDefinitionsFromControls(
        TemplateVersion $templateVersion,
        bool $createMappings = true,
        bool $onlyUnmappedControls = false,
    ): void {
        $controls = TemplateControl::query()
            ->where('template_version_id', $templateVersion->id)
            ->orderBy('part')
            ->orderBy('control_index')
            ->get();

        $mappedControlIds = [];
        if ($createMappings || $onlyUnmappedControls) {
            $mappedControlIds = TemplateControlMapping::query()
                ->where('template_version_id', $templateVersion->id)
                ->pluck('template_control_id')
                ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $mappedControlLookup = array_fill_keys($mappedControlIds, true);

        $mappedFieldIds = [];
        if ($createMappings) {
            $mappedFieldIds = TemplateControlMapping::query()
                ->where('template_version_id', $templateVersion->id)
                ->pluck('field_definition_id')
                ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $mappedFieldLookup = array_fill_keys($mappedFieldIds, true);

        foreach ($controls as $ctrl) {
            if ($onlyUnmappedControls && isset($mappedControlLookup[$ctrl->id])) {
                continue;
            }

            $key = $this->syntheticFieldKeyForControl($ctrl->part, (int) $ctrl->control_index);

            $label = $this->guessLabel($ctrl->context_before, $ctrl->context_after, (int) $ctrl->control_index);
            $required = $ctrl->part === 'document' && in_array($label, ['TITLE', 'PRINCIPAL INVESTIGATOR (PI)', 'OVERSIGHT'], true);

            $field = FieldDefinition::query()->firstOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'section' => 'HRP-503c',
                    'sort_order' => (int) $ctrl->control_index,
                    'is_required' => $required,
                    'input_type' => 'text',
                    'question_text' => $label,
                ],
            );

            if ($createMappings) {
                if (isset($mappedControlLookup[$ctrl->id])) {
                    continue;
                }

                if (isset($mappedFieldLookup[$field->id])) {
                    continue;
                }

                TemplateControlMapping::query()->create([
                    'template_version_id' => $templateVersion->id,
                    'template_control_id' => $ctrl->id,
                    'field_definition_id' => $field->id,
                    'mapped_by_user_id' => null,
                ]);

                $mappedControlLookup[$ctrl->id] = true;
                $mappedFieldLookup[$field->id] = true;
            }
        }
    }

    public function applyBundledMappingPack(TemplateVersion $templateVersion): int
    {
        $path = $this->resolveBundledMappingPackPath($templateVersion);
        $pack = $this->loadBundledMappingPack($path);

        return $this->applyMappingPack($templateVersion, $pack);
    }

    /**
     * Resolve the bundled mapping-pack file path for the given template version.
     *
     * The HRP-503 Protocol/Application template uses a dedicated pack. All
     * other templates (including HRP-503c) fall back to the HRP-503c pack.
     */
    private function resolveBundledMappingPackPath(TemplateVersion $templateVersion): string
    {
        // SHA-256 of docs/HRP-503-TEMPLATE-PROTOCOL.docx at time of authoring.
        $hrp503Sha256 = '1c26f1893830efeb99fba14ca0e1cf5606d785017a36e689c1042c16bfe2ea8d';

        if (hash_equals($hrp503Sha256, strtolower((string) $templateVersion->sha256))) {
            return base_path('resources/mapping-packs/hrp503-default.php');
        }

        return base_path('resources/mapping-packs/hrp503c-default.php');
    }

    public function loadBundledMappingPack(?string $path = null): array
    {
        $path = $path ?? base_path('resources/mapping-packs/hrp503c-default.php');

        if (! is_file($path)) {
            return [];
        }

        $pack = require $path;

        return is_array($pack) ? $pack : [];
    }

    public function applyMappingPack(TemplateVersion $templateVersion, array $pack): int
    {
        if (! $this->mappingPackAppliesToTemplate($templateVersion, $pack)) {
            return 0;
        }

        $entries = $pack['mappings'] ?? null;
        if (! is_array($entries) || $entries === []) {
            return 0;
        }

        $controls = TemplateControl::query()
            ->where('template_version_id', $templateVersion->id)
            ->orderBy('part')
            ->orderBy('control_index')
            ->get();

        if ($controls->isEmpty()) {
            return 0;
        }

        $fieldKeys = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $fieldKey = $entry['field_key'] ?? null;
            if (is_string($fieldKey) && $fieldKey !== '') {
                $fieldKeys[] = $fieldKey;
            }
        }

        if ($fieldKeys === []) {
            return 0;
        }

        $fieldsByKey = FieldDefinition::query()
            ->whereIn('key', array_values(array_unique($fieldKeys)))
            ->get()
            ->keyBy('key');

        $existingMappings = TemplateControlMapping::query()
            ->where('template_version_id', $templateVersion->id)
            ->get();

        $mappedControlLookup = [];
        $mappedFieldLookup = [];
        foreach ($existingMappings as $mapping) {
            $mappedControlLookup[(int) $mapping->template_control_id] = true;
            $mappedFieldLookup[(int) $mapping->field_definition_id] = true;
        }

        $created = 0;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $fieldKey = $entry['field_key'] ?? null;
            if (! is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            $field = $fieldsByKey->get($fieldKey);
            if (! ($field instanceof FieldDefinition)) {
                continue;
            }

            if (isset($mappedFieldLookup[$field->id])) {
                continue;
            }

            foreach ($controls as $ctrl) {
                if (isset($mappedControlLookup[$ctrl->id])) {
                    continue;
                }

                if (! $this->controlMatchesPackEntry($ctrl, $entry)) {
                    continue;
                }

                TemplateControlMapping::query()->create([
                    'template_version_id' => $templateVersion->id,
                    'template_control_id' => $ctrl->id,
                    'field_definition_id' => $field->id,
                    'mapped_by_user_id' => null,
                ]);

                $mappedControlLookup[$ctrl->id] = true;
                $mappedFieldLookup[$field->id] = true;
                $created++;
                break;
            }
        }

        return $created;
    }

    private function mappingPackAppliesToTemplate(TemplateVersion $templateVersion, array $pack): bool
    {
        $allowlist = $pack['template_sha256'] ?? null;

        if ($allowlist === null) {
            return true;
        }

        if (is_string($allowlist)) {
            return hash_equals(strtolower($allowlist), strtolower($templateVersion->sha256));
        }

        if (! is_array($allowlist)) {
            return false;
        }

        foreach ($allowlist as $sha) {
            if (! is_string($sha) || $sha === '') {
                continue;
            }

            if (hash_equals(strtolower($sha), strtolower($templateVersion->sha256))) {
                return true;
            }
        }

        return false;
    }

    private function controlMatchesPackEntry(TemplateControl $control, array $entry): bool
    {
        $part = $entry['part'] ?? null;
        if (is_string($part) && $part !== '' && $control->part !== $part) {
            return false;
        }

        $controlIndex = $entry['control_index'] ?? null;
        if (is_int($controlIndex) && (int) $control->control_index !== $controlIndex) {
            return false;
        }

        if (is_string($controlIndex) && ctype_digit($controlIndex) && (int) $control->control_index !== (int) $controlIndex) {
            return false;
        }

        $signature = $entry['signature_sha256'] ?? null;
        if (is_string($signature) && $signature !== '' && ! hash_equals(strtolower($signature), strtolower((string) $control->signature_sha256))) {
            return false;
        }

        $placeholder = $entry['placeholder_text'] ?? null;
        if (is_string($placeholder) && $this->normalizeLooseText((string) $control->placeholder_text) !== $this->normalizeLooseText($placeholder)) {
            return false;
        }

        $guessedLabel = $entry['guessed_label'] ?? null;
        if (is_string($guessedLabel) && $guessedLabel !== '') {
            $current = $this->guessLabel($control->context_before, $control->context_after, (int) $control->control_index);
            if (strtoupper($guessedLabel) !== strtoupper($current)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeLooseText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    private function syntheticFieldKeyForControl(string $part, int $controlIndex): string
    {
        if ($part === 'document') {
            return 'ctrl_doc_'.str_pad((string) $controlIndex, 3, '0', STR_PAD_LEFT);
        }

        $partToken = strtolower($part);
        $partToken = preg_replace('/[^a-z0-9]+/', '_', $partToken) ?? $partToken;
        $partToken = trim($partToken, '_');
        if ($partToken === '') {
            $partToken = 'part';
        }

        return 'ctrl_'.$partToken.'_'.str_pad((string) $controlIndex, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array{context_before: string|null, context_after: string|null, placeholder_text: string|null}>
     */
    private function extractSdtControls(string $xml): array
    {
        $out = [];

        $matches = [];
        preg_match_all('~<w:sdt\b.*?</w:sdt>~s', $xml, $matches, PREG_OFFSET_CAPTURE);

        foreach (($matches[0] ?? []) as $m) {
            $block = (string) ($m[0] ?? '');
            $offset = (int) ($m[1] ?? 0);
            $len = strlen($block);

            $placeholderText = $this->extractTextFromSdtXml($block);
            $placeholderText = $placeholderText === '' ? null : $placeholderText;

            $window = 8000;
            $beforeXml = substr($xml, max(0, $offset - $window), min($window, $offset));
            $afterXml = substr($xml, $offset + $len, $window);

            $beforeText = $this->xmlToPlainText((string) $beforeXml);
            $afterText = $this->xmlToPlainText((string) $afterXml);

            $contextBefore = $beforeText === '' ? null : $this->tail($beforeText, 180);
            $contextAfter = $afterText === '' ? null : $this->head($afterText, 180);

            $out[] = [
                'context_before' => $contextBefore,
                'context_after' => $contextAfter,
                'placeholder_text' => $placeholderText,
            ];
        }

        return $out;
    }

    private function extractTextFromSdtXml(string $sdtXml): string
    {
        $matches = [];
        preg_match_all('~<w:t\b[^>]*>(.*?)</w:t>~s', $sdtXml, $matches);

        $parts = [];
        foreach (($matches[1] ?? []) as $t) {
            $parts[] = html_entity_decode((string) $t, ENT_QUOTES | ENT_XML1);
        }

        $text = trim(implode('', $parts));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function xmlToPlainText(string $xml): string
    {
        $xml = preg_replace('~<[^>]+>~', ' ', $xml) ?? $xml;
        $xml = html_entity_decode($xml, ENT_QUOTES | ENT_XML1);
        $xml = preg_replace('/\s+/', ' ', $xml) ?? $xml;

        return trim($xml);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function extractBeforeAfterTextInParagraph(\DOMXPath $xpath, \DOMElement $paragraph, \DOMElement $sdt): array
    {
        $container = $this->findDirectChildContainer($paragraph, $sdt);

        $segments = [];
        foreach ($paragraph->childNodes as $child) {
            if (! ($child instanceof \DOMElement)) {
                continue;
            }

            if ($container !== null && $child->isSameNode($container)) {
                $segments[] = ['type' => 'sdt', 'node' => $child];

                continue;
            }

            $t = $this->extractVisibleText($xpath, $child);
            if ($t !== '') {
                $segments[] = ['type' => 'text', 'text' => $t];
            }
        }

        $pos = null;
        foreach ($segments as $i => $seg) {
            if (($seg['type'] ?? null) === 'sdt') {
                $pos = $i;
                break;
            }
        }

        if ($pos === null) {
            return [null, null];
        }

        $before = '';
        for ($i = 0; $i < $pos; $i++) {
            if (($segments[$i]['type'] ?? null) === 'text') {
                $before .= (string) $segments[$i]['text'];
            }
        }

        $after = '';
        for ($i = $pos + 1; $i < count($segments); $i++) {
            if (($segments[$i]['type'] ?? null) === 'text') {
                $after .= (string) $segments[$i]['text'];
            }
        }

        $before = trim($before);
        $after = trim($after);

        $before = $before === '' ? null : $this->tail($before, 180);
        $after = $after === '' ? null : $this->head($after, 180);

        return [$before, $after];
    }

    private function findDirectChildContainer(\DOMElement $paragraph, \DOMElement $sdt): ?\DOMElement
    {
        $node = $sdt;

        while ($node->parentNode instanceof \DOMElement) {
            $parent = $node->parentNode;

            if ($parent->isSameNode($paragraph)) {
                return $node;
            }

            $node = $parent;
        }

        return null;
    }

    private function extractVisibleText(\DOMXPath $xpath, \DOMElement $node): string
    {
        $texts = [];
        $nodes = $xpath->query('.//w:t', $node);
        if ($nodes === false) {
            return '';
        }

        foreach ($nodes as $t) {
            $texts[] = $t->textContent;
        }

        return trim(implode('', $texts));
    }

    private function unzipPrintFile(string $docxPath, string $innerPath): string
    {
        $process = new Process(['unzip', '-p', $docxPath, $innerPath]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return (string) $process->getOutput();
    }

    private function guessLabel(?string $before, ?string $after, int $index): string
    {
        $fromContext = $this->guessAllCapsLabelFromContext($before, $after);
        if ($fromContext !== null) {
            return $fromContext;
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

    private function guessAllCapsLabelFromContext(?string $before, ?string $after): ?string
    {
        $ctx = trim((string) $before);
        if ($ctx === '') {
            $ctx = trim((string) $after);
        }

        if ($ctx === '') {
            return null;
        }

        // Try to extract the last all-caps label like "TITLE:" or "PRINCIPAL INVESTIGATOR (PI):".
        $ctx = preg_replace('/\s+/', ' ', $ctx) ?? $ctx;
        $matches = [];
        preg_match_all('/([A-Z][A-Z0-9 \-\/()]{2,80})\s*:\s*/', $ctx, $matches);

        $labels = $matches[1] ?? [];
        if (! is_array($labels) || count($labels) === 0) {
            return null;
        }

        $label = trim((string) end($labels));
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        $label = trim($label);

        if ($label === '' || strlen($label) < 3) {
            return null;
        }

        return $label;
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
}
