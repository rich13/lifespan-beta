<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Yaml\Yaml;

class CreateSampleYaml extends Command
{
    protected $signature = 'yaml:create-samples {count=5 : Number of sample files to create}';
    protected $description = 'Create sample YAML files for testing the import feature';

    public function handle()
    {
        $count = (int) $this->argument('count');
        $this->info("Creating {$count} sample YAML files in storage/app/imports...");

        // Make sure the directory exists
        Storage::makeDirectory('imports');

        $created = 0;
        $types = ['person', 'organisation', 'band'];
        
        for ($i = 0; $i < $count; $i++) {
            $type = $types[array_rand($types)];
            $uuid = Uuid::uuid4()->toString();
            
            switch ($type) {
                case 'person':
                    $data = $this->createPersonYaml();
                    break;
                case 'band':
                    $data = $this->createBandYaml();
                    break;
                case 'organisation':
                    $data = $this->createOrganisationYaml();
                    break;
            }
            
            $yaml = Yaml::dump($data, 4);
            $filename = "{$uuid}.yaml";
            
            if (Storage::put("imports/{$filename}", $yaml)) {
                $this->info("Created {$type} YAML file: {$filename}");
                $created++;
            } else {
                $this->error("Failed to create YAML file: {$filename}");
            }
        }
        
        $this->info("Created {$created} sample YAML files.");
        return 0;
    }
    
    private function createPersonYaml(): array
    {
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'Robert', 'Emily', 'David', 'Susan'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Wilson'];
        
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $name = "{$firstName} {$lastName}";
        
        $startYear = rand(1950, 2000);
        
        return [
            'name' => $name,
            'type' => 'person',
            'start_year' => $startYear,
            'metadata' => [
                'birth_place' => 'London, UK',
                'nationality' => 'British'
            ],
            'education' => [
                [
                    'institution' => 'University of Cambridge',
                    'qualification' => 'Bachelor of Science',
                    'start' => ($startYear + 18) . '-09-01',
                    'end' => ($startYear + 21) . '-06-30',
                ]
            ],
            'work' => [
                [
                    'organisation' => 'Tech Corporation',
                    'role' => 'Software Developer',
                    'start' => ($startYear + 22) . '-01-15',
                    'end' => ($startYear + 25) . '-12-31',
                ]
            ]
        ];
    }
    
    private function createBandYaml(): array
    {
        $bandNames = ['The Rockets', 'Electric Dreams', 'Sunset Boulevard', 'Cosmic Waves', 'Urban Legends'];
        $name = $bandNames[array_rand($bandNames)];
        
        $startYear = rand(1970, 2010);
        $endYear = rand($startYear + 5, 2023);
        
        return [
            'name' => $name,
            'type' => 'band',
            'start' => "{$startYear}-01-01",
            'end' => "{$endYear}-12-31",
            'metadata' => [
                'formation_location' => 'Manchester, UK',
                'genre' => 'Rock'
            ],
            'members' => [
                [
                    'name' => 'Alex Thompson',
                    'role' => 'Lead Vocals',
                    'start' => "{$startYear}-01-01",
                    'end' => "{$endYear}-12-31"
                ],
                [
                    'name' => 'Chris Johnson',
                    'role' => 'Guitar',
                    'start' => "{$startYear}-01-01",
                    'end' => "{$endYear}-12-31"
                ]
            ]
        ];
    }
    
    private function createOrganisationYaml(): array
    {
        $orgNames = ['Global Tech', 'Future Innovations', 'Acme Corporation', 'Worldwide Media', 'Green Energy Solutions'];
        $name = $orgNames[array_rand($orgNames)];
        
        $startYear = rand(1900, 2000);
        
        return [
            'name' => $name,
            'type' => 'organisation',
            'start' => "{$startYear}-01-01",
            'metadata' => [
                'headquarters' => 'London, UK',
                'industry' => 'Technology'
            ],
            'leadership' => [
                [
                    'name' => 'Richard Parker',
                    'role' => 'CEO',
                    'start' => ($startYear + 50) . '-01-01',
                    'end' => ($startYear + 60) . '-12-31'
                ]
            ]
        ];
    }
} 