<?php

namespace Database\Factories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dataset>
 */
class DatasetFactory extends Factory
{
    protected $model = Dataset::class;

    public function definition(): array
    {
        $name = 'Dataset '.$this->faker->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => null,
            'value_label' => 'Value',
            'unit' => null,
            'attribution' => 'Test attribution',
            'source_url' => null,
            'import_format' => 'owid_grapher_csv',
        ];
    }
}
