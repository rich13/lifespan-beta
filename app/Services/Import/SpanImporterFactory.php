<?php

namespace App\Services\Import;

use App\Models\User;
use App\Services\Import\Types\PersonImporter;
use App\Services\Import\Types\BandImporter;
use App\Services\Import\Types\OrganisationImporter;
use Symfony\Component\Yaml\Yaml;

class SpanImporterFactory
{
    public static function create(string $yamlPath, User $user): SpanImporter
    {
        $data = Yaml::parseFile($yamlPath);
        
        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('YAML file must specify a type');
        }

        return match ($data['type']) {
            'person' => new PersonImporter($user),
            'band' => new BandImporter($user),
            'organisation' => new OrganisationImporter($user),
            default => throw new \InvalidArgumentException("Unsupported span type: {$data['type']}")
        };
    }
} 