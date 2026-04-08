<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Export;
use App\Models\ProjectDocument;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RetentionPruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'irb:retention-prune {--days=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete uploaded documents and exports older than retention policy.';

    /**
     * Execute the console command.
     */
    public function handle(SettingsService $settings): int
    {
        $daysOpt = $this->option('days');
        $days = null;

        if (is_string($daysOpt) && $daysOpt !== '') {
            if (! ctype_digit($daysOpt)) {
                $this->error('--days must be an integer');
                return self::FAILURE;
            }
            $days = (int) $daysOpt;
        }

        $days = $days ?? $settings->int('retention_days', (int) env('IRB_RETENTION_DAYS', 14));
        if ($days < 1) {
            $this->error('Retention days must be >= 1');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info('Retention prune');
        $this->line('days='.$days.' cutoff='.$cutoff->toDateTimeString().' dry_run='.(int) $dryRun);

        $docCount = 0;
        $docFileDeleted = 0;
        $exportCount = 0;
        $exportFileDeleted = 0;

        $oldDocs = ProjectDocument::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($docs) use ($dryRun, &$docCount, &$docFileDeleted) {
                foreach ($docs as $doc) {
                    $docCount++;
                    $disk = $doc->storage_disk;
                    $path = $doc->storage_path;

                    $this->line("doc#{$doc->id} {$disk}:{$path} {$doc->original_filename}");

                    if (! $dryRun) {
                        if ($path !== null && Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                            $docFileDeleted++;
                        }
                        $doc->delete();
                    }
                }
            });

        unset($oldDocs);

        $oldExports = Export::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($exports) use ($dryRun, &$exportCount, &$exportFileDeleted) {
                foreach ($exports as $ex) {
                    $exportCount++;
                    $disk = $ex->storage_disk;
                    $path = $ex->storage_path;

                    $this->line("export#{$ex->id} {$disk}:{$path}");

                    if (! $dryRun) {
                        if ($path !== null && Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                            $exportFileDeleted++;
                        }
                        $ex->delete();
                    }
                }
            });

        unset($oldExports);

        $this->info('Done');
        $this->line('docs_matched='.$docCount.' docs_files_deleted='.$docFileDeleted);
        $this->line('exports_matched='.$exportCount.' export_files_deleted='.$exportFileDeleted);

        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => null,
            'event_type' => $dryRun ? 'retention.prune_dry_run' : 'retention.pruned',
            'entity_type' => null,
            'entity_id' => null,
            'entity_uuid' => null,
            'project_id' => null,
            'ip' => null,
            'user_agent' => null,
            'request_id' => null,
            'payload' => [
                'days' => $days,
                'cutoff' => $cutoff->toDateTimeString(),
                'docs_matched' => $docCount,
                'docs_files_deleted' => $docFileDeleted,
                'exports_matched' => $exportCount,
                'export_files_deleted' => $exportFileDeleted,
            ],
        ]);

        return self::SUCCESS;
    }
}
