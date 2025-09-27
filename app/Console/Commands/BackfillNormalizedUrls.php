<?php

namespace App\Console\Commands;

use App\Models\IntentEvent;
use App\Services\UrlNormalizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillNormalizedUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'urls:backfill {--tenant= : Backfill for specific tenant ID} {--limit=1000 : Number of events to process per batch} {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill existing events with normalized URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info("Starting URL normalization backfill...");
        $this->info("Tenant: " . ($tenantId ?: 'All'));
        $this->info("Batch size: {$limit}");
        $this->info("Dry run: " . ($dryRun ? 'Yes' : 'No'));

        try {
            $urlNormalizer = new UrlNormalizerService();
            $processed = 0;
            $updated = 0;
            $errors = 0;

            do {
                // Get events that need URL normalization
                $query = IntentEvent::whereRaw('
                    JSON_EXTRACT(event_data, "$.page_url_normalized") IS NULL 
                    AND JSON_EXTRACT(event_data, "$.page_url") IS NOT NULL
                ');

                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }

                $events = $query->limit($limit)->get();

                if ($events->isEmpty()) {
                    break;
                }

                $this->info("Processing batch of {$events->count()} events...");

                foreach ($events as $event) {
                    try {
                        $eventData = json_decode($event->event_data, true);
                        $originalUrl = $eventData['page_url'] ?? null;

                        if (!$originalUrl) {
                            continue;
                        }

                        $normalizedUrl = $urlNormalizer->normalize($originalUrl);
                        $pageCategory = $urlNormalizer->getPageCategory($normalizedUrl);
                        $isHighValue = $urlNormalizer->isHighValuePage($normalizedUrl);

                        if (!$dryRun) {
                            // Update event_data with normalized URL
                            $eventData['page_url_normalized'] = $normalizedUrl;
                            $eventData['page_category'] = $pageCategory;
                            $eventData['is_high_value_page'] = $isHighValue;

                            // Update metadata with additional info
                            $metadata = json_decode($event->metadata, true) ?? [];
                            $metadata['page_url_normalized'] = $normalizedUrl;
                            $metadata['page_category'] = $pageCategory;
                            $metadata['is_high_value_page'] = $isHighValue;

                            $event->update([
                                'event_data' => json_encode($eventData),
                                'metadata' => json_encode($metadata),
                            ]);
                        }

                        $updated++;
                        $this->line("  ✓ Event {$event->id}: {$originalUrl} → {$normalizedUrl}");

                    } catch (\Exception $e) {
                        $errors++;
                        $this->error("  ✗ Event {$event->id}: {$e->getMessage()}");
                    }

                    $processed++;
                }

                $this->info("Batch completed. Processed: {$processed}, Updated: {$updated}, Errors: {$errors}");

            } while ($events->count() === $limit);

            $this->info("✅ URL normalization backfill completed!");
            $this->info("Total processed: {$processed}");
            $this->info("Total updated: {$updated}");
            $this->info("Total errors: {$errors}");

            if ($dryRun) {
                $this->warn("This was a dry run. No changes were made.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to backfill normalized URLs: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}