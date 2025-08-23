<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Span;

class UpdateWikimediaImagesLicenseUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wikimedia:update-license-urls {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing Wikimedia Commons images with license URLs and attribution requirements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding Wikimedia Commons images without license URLs...');
        
        // Find all Wikimedia Commons images that don't have license_url
        $images = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereJsonContains('metadata->source', 'Wikimedia Commons')
            ->where(function ($query) {
                $query->whereJsonLength('metadata->license_url', 0)
                      ->orWhereNull('metadata->license_url');
            })
            ->get();

        $this->info("Found {$images->count()} Wikimedia Commons images without license URLs");

        if ($images->count() === 0) {
            $this->info('No images need updating!');
            return 0;
        }

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($images as $image) {
            try {
                $this->updateImageLicenseInfo($image);
                $updatedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to update image {$image->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("License URL update completed:");
        $this->info("- Successfully updated: {$updatedCount}");
        $this->info("- Failed: {$errorCount}");
        
        return 0;
    }

    /**
     * Update license information for a single image
     */
    protected function updateImageLicenseInfo(Span $image): void
    {
        $metadata = $image->metadata ?? [];
        $license = $metadata['license'] ?? '';
        
        if (empty($license)) {
            return;
        }

        // Get license URL and attribution requirements
        $licenseInfo = $this->getLicenseInfo($license);
        
        // Update metadata
        $metadata['license_url'] = $licenseInfo['url'];
        $metadata['requires_attribution'] = $licenseInfo['requires_attribution'];
        
        // Save the updated metadata
        $image->metadata = $metadata;
        $image->save();
    }

    /**
     * Get license URL and attribution requirements
     */
    protected function getLicenseInfo(string $license): array
    {
        $license = strtolower($license);
        
        $licenseMap = [
            'creative commons attribution' => [
                'url' => 'https://creativecommons.org/licenses/by/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-sharealike' => [
                'url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noderivs' => [
                'url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noncommercial' => [
                'url' => 'https://creativecommons.org/licenses/by-nc/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noncommercial-sharealike' => [
                'url' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
                'requires_attribution' => true
            ],
            'creative commons attribution-noncommercial-noderivs' => [
                'url' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
                'requires_attribution' => true
            ],
            'creative commons zero' => [
                'url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                'requires_attribution' => false
            ],
            'public domain' => [
                'url' => 'https://creativecommons.org/publicdomain/mark/1.0/',
                'requires_attribution' => false
            ],
            'fair use' => [
                'url' => 'https://en.wikipedia.org/wiki/Fair_use',
                'requires_attribution' => true
            ],
            'gnu free documentation license' => [
                'url' => 'https://www.gnu.org/licenses/fdl.html',
                'requires_attribution' => true
            ],
            'mit license' => [
                'url' => 'https://opensource.org/licenses/MIT',
                'requires_attribution' => true
            ],
            'apache license' => [
                'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
                'requires_attribution' => true
            ]
        ];

        // Try to match the license
        foreach ($licenseMap as $pattern => $info) {
            if (strpos($license, $pattern) !== false) {
                return $info;
            }
        }

        // Default for unknown licenses
        return [
            'url' => '',
            'requires_attribution' => true // Assume attribution is required for unknown licenses
        ];
    }
}
