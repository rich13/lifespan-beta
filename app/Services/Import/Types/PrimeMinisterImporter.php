<?php

namespace App\Services\Import\Types;

use App\Models\Span;
use App\Services\Import\Connections\ConnectionImporter;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class PrimeMinisterImporter extends PersonImporter
{
    protected ConnectionImporter $connectionImporter;
    protected array $parliamentApiData = [];

    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->connectionImporter = new ConnectionImporter($user);
    }

    protected function getSpanType(): string
    {
        return 'person';
    }

    protected function validateTypeSpecificFields(): void
    {
        parent::validateTypeSpecificFields();

        // Validate Prime Minister specific fields
        if (isset($this->data['parliament_id'])) {
            if (!is_numeric($this->data['parliament_id'])) {
                $this->addError('validation', 'Parliament ID must be numeric');
            }
        }

        if (isset($this->data['prime_ministerships'])) {
            if (!is_array($this->data['prime_ministerships'])) {
                $this->addError('validation', 'Prime ministerships must be an array');
                return;
            }

            foreach ($this->data['prime_ministerships'] as $index => $pm) {
                if (!isset($pm['start_date'])) {
                    $this->addError('validation', "Prime ministership at index {$index} must have a start_date");
                }
                if (!isset($pm['end_date']) && !isset($pm['ongoing'])) {
                    $this->addError('validation', "Prime ministership at index {$index} must have either end_date or ongoing=true");
                }
            }
        }
    }

    protected function setTypeSpecificFields(Span $span): void
    {
        parent::setTypeSpecificFields($span);

        $metadata = $span->metadata ?? [];
        
        // Add Prime Minister specific metadata
        $metadata['is_prime_minister'] = true;
        
        if (isset($this->data['parliament_id'])) {
            $metadata['parliament_id'] = $this->data['parliament_id'];
        }

        if (isset($this->data['party'])) {
            $metadata['political_party'] = $this->data['party'];
        }

        if (isset($this->data['constituency'])) {
            $metadata['constituency'] = $this->data['constituency'];
        }

        // Add any additional data from Parliament API
        if (!empty($this->parliamentApiData)) {
            $metadata['parliament_api_data'] = $this->parliamentApiData;
        }

        $span->metadata = $metadata;
    }

    protected function importTypeSpecificRelationships(Span $span): void
    {
        parent::importTypeSpecificRelationships($span);

        // Import Prime Ministerships as work connections
        if (isset($this->data['prime_ministerships'])) {
            $this->importPrimeMinisterships($span);
        }

        // Import party membership
        if (isset($this->data['party'])) {
            $this->importPartyMembership($span);
        }

        // Import constituency
        if (isset($this->data['constituency'])) {
            $this->importConstituency($span);
        }
    }

    protected function importPrimeMinisterships(Span $span): void
    {
        foreach ($this->data['prime_ministerships'] as $pm) {
            try {
                // Create UK Government as an organisation if it doesn't exist
                $ukGovernment = $this->connectionImporter->findOrCreateConnectedSpan(
                    'UK Government',
                    'organisation',
                    null,
                    [
                        'type' => 'government',
                        'country' => 'United Kingdom',
                        'level' => 'national'
                    ]
                );

                // Parse dates
                $startDate = $this->parseDate($pm['start_date']);
                $endDate = null;
                
                if (isset($pm['end_date'])) {
                    $endDate = $this->parseDate($pm['end_date']);
                } elseif (isset($pm['ongoing']) && $pm['ongoing']) {
                    // Ongoing position - no end date
                }

                // Prepare connection dates
                $connectionDates = [];
                if ($startDate) {
                    $connectionDates['start_year'] = $startDate->year;
                    $connectionDates['start_month'] = $startDate->month;
                    $connectionDates['start_day'] = $startDate->day;
                }
                if ($endDate) {
                    $connectionDates['end_year'] = $endDate->year;
                    $connectionDates['end_month'] = $endDate->month;
                    $connectionDates['end_day'] = $endDate->day;
                }

                // Create connection metadata
                $connectionMetadata = [
                    'role' => 'Prime Minister',
                    'position' => 'Prime Minister of the United Kingdom'
                ];

                if (isset($pm['party'])) {
                    $connectionMetadata['party'] = $pm['party'];
                }

                // Create the employment connection
                $connection = $this->connectionImporter->createConnection(
                    $span,
                    $ukGovernment,
                    'employment',
                    $connectionDates,
                    $connectionMetadata
                );

                Log::info('Prime Ministership connection created', [
                    'span_id' => $span->id,
                    'organisation_id' => $ukGovernment->id,
                    'connection_id' => $connection->id,
                    'dates' => $connectionDates
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to import Prime Ministership', [
                    'error' => $e->getMessage(),
                    'pm_data' => $pm
                ]);
                $this->addWarning("Failed to import Prime Ministership: " . $e->getMessage());
            }
        }
    }

    protected function importPartyMembership(Span $span): void
    {
        try {
            $partyName = $this->data['party'];
            
            // Create political party as organisation
            $party = $this->connectionImporter->findOrCreateConnectedSpan(
                $partyName,
                'organisation',
                null,
                [
                    'type' => 'political_party',
                    'country' => 'United Kingdom'
                ]
            );

            // Create membership connection
            $connection = $this->connectionImporter->createConnection(
                $span,
                $party,
                'membership',
                null, // Party membership dates not specified in basic data
                ['role' => 'Member']
            );

            Log::info('Party membership connection created', [
                'span_id' => $span->id,
                'party_id' => $party->id,
                'connection_id' => $connection->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to import party membership', [
                'error' => $e->getMessage(),
                'party' => $this->data['party']
            ]);
            $this->addWarning("Failed to import party membership: " . $e->getMessage());
        }
    }

    protected function importConstituency(Span $span): void
    {
        try {
            $constituencyName = $this->data['constituency'];
            
            // Create constituency as place
            $constituency = $this->connectionImporter->findOrCreateConnectedSpan(
                $constituencyName,
                'place',
                null,
                [
                    'type' => 'constituency',
                    'country' => 'United Kingdom',
                    'level' => 'parliamentary'
                ]
            );

            // Create residence connection (MPs typically live in their constituency)
            $connection = $this->connectionImporter->createConnection(
                $span,
                $constituency,
                'residence',
                null, // Residence dates not specified in basic data
                ['role' => 'MP for constituency']
            );

            Log::info('Constituency connection created', [
                'span_id' => $span->id,
                'constituency_id' => $constituency->id,
                'connection_id' => $connection->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to import constituency', [
                'error' => $e->getMessage(),
                'constituency' => $this->data['constituency']
            ]);
            $this->addWarning("Failed to import constituency: " . $e->getMessage());
        }
    }

    /**
     * Fetch data from UK Parliament API for a given member ID
     */
    public function fetchParliamentData(int $parliamentId): array
    {
        try {
            $response = Http::get("https://members-api.parliament.uk/api/Members/{$parliamentId}");
            
            if ($response->successful()) {
                $this->parliamentApiData = $response->json();
                return $this->parliamentApiData;
            } else {
                Log::warning("Failed to fetch Parliament data for ID {$parliamentId}", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Exception fetching Parliament data for ID {$parliamentId}", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Search for members in the UK Parliament API
     */
    public function searchParliamentMembers(string $searchTerm, int $skip = 0, int $take = 10): array
    {
        try {
            $response = Http::get('https://members-api.parliament.uk/api/Members/Search', [
                'House' => 'Commons',
                'IsCurrentMember' => 'false',
                'skip' => $skip,
                'take' => $take
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Filter results by search term if provided
                if ($searchTerm) {
                    $data['items'] = array_filter($data['items'], function($item) use ($searchTerm) {
                        $name = $item['value']['nameDisplayAs'] ?? '';
                        return stripos($name, $searchTerm) !== false;
                    });
                }
                
                return $data;
            } else {
                Log::warning("Failed to search Parliament members", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error("Exception searching Parliament members", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Parse date string to Carbon instance
     */
    protected function parseDate(?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$dateString}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
} 