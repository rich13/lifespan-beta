<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetObservation;
use App\Models\DatasetSeries;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DatasetsFeatureTest extends TestCase
{
    public function test_combined_points_requires_datasets_parameter(): void
    {
        $this->get(route('datasets.combined-points').'?series_key=XX')
            ->assertStatus(422);
    }

    public function test_combined_points_returns_one_line_per_dataset(): void
    {
        $seriesKey = 'ZZ';
        $datasetA = Dataset::factory()->create(['slug' => 'combined-test-a', 'name' => 'Combined A']);
        $datasetB = Dataset::factory()->create(['slug' => 'combined-test-b', 'name' => 'Combined B']);

        foreach ([$datasetA, $datasetB] as $dataset) {
            $series = DatasetSeries::query()->create([
                'dataset_id' => $dataset->id,
                'series_key' => $seriesKey,
                'label' => 'Zed',
            ]);
            DatasetObservation::query()->create([
                'series_id' => $series->id,
                't_start' => 2000.0,
                't_end' => 2001.0,
                'value' => $dataset->slug === 'combined-test-a' ? 10.0 : 20.0,
            ]);
        }

        $url = route('datasets.combined-points').'?datasets=combined-test-a,combined-test-b&series_key='.$seriesKey.'&from=1999&to=2001';
        $json = $this->get($url)->assertOk()->json();

        $this->assertSame($seriesKey, $json['series_key']);
        $this->assertCount(2, $json['lines']);
        $this->assertFalse($json['lines'][0]['missing_series']);
        $this->assertFalse($json['lines'][1]['missing_series']);
        $this->assertCount(1, $json['lines'][0]['points']);
        $this->assertCount(1, $json['lines'][1]['points']);
    }

    public function test_guest_can_view_datasets_index(): void
    {
        $this->get(route('datasets.index'))
            ->assertOk()
            ->assertViewIs('datasets.index');
    }

    public function test_datasets_index_renders_combined_chart_markup_once(): void
    {
        $dataset = Dataset::factory()->create(['slug' => 'panel-test-a', 'name' => 'Panel A']);
        DatasetSeries::query()->create([
            'dataset_id' => $dataset->id,
            'series_key' => 'GBR',
            'label' => 'United Kingdom',
        ]);

        $html = $this->get(route('datasets.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'js-datasets-index-root'));
        $this->assertSame(1, substr_count($html, 'js-datasets-index-form'));
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'js-dataset-panel"'));
        $this->assertStringContainsString('data-slug="panel-test-a"', $html);
        $this->assertStringContainsString('value="GBR"', $html);
        $this->assertSame(0, substr_count($html, 'js-index-series-key'));
        $this->assertSame(0, substr_count($html, 'js-datasets-combined-root'));
        $this->assertSame(0, substr_count($html, 'id="datasets-combined-controls"'));
    }

    public function test_datasets_index_defaults_year_range_from_data(): void
    {
        $dataset = Dataset::factory()->create();
        $series = DatasetSeries::query()->create([
            'dataset_id' => $dataset->id,
            'series_key' => 'AA',
            'label' => 'A',
        ]);
        DatasetObservation::query()->create([
            'series_id' => $series->id,
            't_start' => 1988.0,
            't_end' => 1989.0,
            'value' => 1.0,
        ]);

        $calendarYear = (int) now()->year;

        $this->get(route('datasets.index'))
            ->assertOk()
            ->assertViewHas('chartYearMin', 1988)
            ->assertViewHas('chartYearMax', $calendarYear);
    }

    public function test_admin_can_view_admin_datasets_index(): void
    {
        $admin = User::factory()->admin()->create();
        Dataset::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('admin.datasets.index'))
            ->assertOk()
            ->assertViewIs('admin.datasets.index');
    }

    public function test_admin_can_delete_dataset_and_cascades_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $dataset = Dataset::factory()->create();
        $series = DatasetSeries::query()->create([
            'dataset_id' => $dataset->id,
            'series_key' => 'ZZ',
            'label' => 'Zed',
        ]);
        DatasetObservation::query()->create([
            'series_id' => $series->id,
            't_start' => 2000.0,
            't_end' => 2001.0,
            'value' => 1.5,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.datasets.destroy', $dataset))
            ->assertRedirect(route('admin.datasets.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('datasets', ['id' => $dataset->id]);
        $this->assertDatabaseMissing('dataset_series', ['id' => $series->id]);
    }

    public function test_non_admin_cannot_delete_dataset(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $dataset = Dataset::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.datasets.destroy', $dataset))
            ->assertForbidden();

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id]);
    }

    public function test_guest_cannot_delete_dataset(): void
    {
        $dataset = Dataset::factory()->create();

        $this->delete(route('admin.datasets.destroy', $dataset))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id]);
    }

    public function test_guest_is_redirected_from_admin_datasets(): void
    {
        $this->get(route('admin.datasets.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_import_dataset(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $csv = "Entity,Code,Year,Metric\nTestland,TL,2000,1\n";

        $this->actingAs($user)->post(route('admin.datasets.store'), [
            'name' => 'Example',
            'value_label' => 'Metric',
            'attribution' => 'Test source',
            'csv_file' => UploadedFile::fake()->createWithContent('x.csv', $csv),
        ])->assertForbidden();
    }

    public function test_admin_can_import_owid_style_csv_and_fetch_points(): void
    {
        $admin = User::factory()->admin()->create();
        $csv = "Entity,Code,Year,Life expectancy\nTestland,TL,2000,42.5\nTestland,TL,2001,43.1\n";

        $response = $this->actingAs($admin)->post(route('admin.datasets.store'), [
            'name' => 'Life in Testland',
            'value_label' => 'Life expectancy',
            'unit' => 'years',
            'attribution' => 'Synthetic fixture for automated tests.',
            'csv_file' => UploadedFile::fake()->createWithContent('life.csv', $csv),
        ]);

        $response->assertRedirect();
        $dataset = Dataset::query()->where('slug', 'life-in-testland')->first();
        $this->assertNotNull($dataset);

        $series = DatasetSeries::query()->where('dataset_id', $dataset->id)->where('series_key', 'TL')->first();
        $this->assertNotNull($series);
        $this->assertSame(2, DatasetObservation::query()->where('series_id', $series->id)->count());

        $json = $this->get(route('datasets.points', $dataset).'?series_key=TL&from=1999&to=2001')
            ->assertOk()
            ->json();

        $this->assertCount(2, $json['points']);
        $this->assertSame('TL', $json['series']['series_key']);
    }

    public function test_points_returns_404_for_unknown_series(): void
    {
        $dataset = Dataset::factory()->create();

        $this->get(route('datasets.points', $dataset).'?series_key=MISSING')
            ->assertNotFound();
    }

    public function test_dataset_show_defaults_year_range_from_default_series_data(): void
    {
        $dataset = Dataset::factory()->create();
        $series = DatasetSeries::query()->create([
            'dataset_id' => $dataset->id,
            'series_key' => 'AA',
            'label' => 'A',
        ]);
        DatasetObservation::query()->create([
            'series_id' => $series->id,
            't_start' => 1992.0,
            't_end' => 1993.0,
            'value' => 1.0,
        ]);

        $calendarYear = (int) now()->year;

        $this->get(route('datasets.show', $dataset))
            ->assertOk()
            ->assertViewHas('chartYearMin', 1992)
            ->assertViewHas('chartYearMax', $calendarYear);
    }
}
