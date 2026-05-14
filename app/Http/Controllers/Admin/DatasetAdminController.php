<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDatasetRequest;
use App\Models\Dataset;
use App\Services\Datasets\OwidGrapherCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class DatasetAdminController extends Controller
{
    public function index(): View
    {
        $datasets = Dataset::query()
            ->withCount('series')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('admin.datasets.index', compact('datasets'));
    }

    public function create(): View
    {
        return view('admin.datasets.create');
    }

    public function store(StoreDatasetRequest $request, OwidGrapherCsvImporter $importer): RedirectResponse
    {
        $uploaded = $request->file('csv_file');
        $path = $uploaded->getRealPath();
        if ($path === false) {
            return back()->withInput()->withErrors(['csv_file' => 'Could not read the uploaded file.']);
        }

        $baseSlug = $request->validated('slug') ?? Str::slug($request->validated('name'));
        $slug = $baseSlug;
        $n = 1;
        while (Dataset::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$n;
            $n++;
        }

        try {
            [$dataset, $stats] = DB::transaction(function () use ($request, $importer, $path, $slug) {
                $dataset = Dataset::query()->create([
                    'name' => $request->validated('name'),
                    'slug' => $slug,
                    'description' => $request->validated('description'),
                    'value_label' => $request->validated('value_label'),
                    'unit' => $request->validated('unit'),
                    'attribution' => $request->validated('attribution'),
                    'source_url' => $request->validated('source_url'),
                    'import_format' => 'owid_grapher_csv',
                ]);

                $stats = $importer->import($dataset, $path);

                return [$dataset, $stats];
            });
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['csv_file' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.datasets.show', $dataset)
            ->with('status', sprintf(
                'Imported %s observations across %s series.',
                number_format($stats['observation_count']),
                number_format($stats['series_count'])
            ));
    }

    public function show(Dataset $dataset): View
    {
        $seriesSample = $dataset->series()
            ->orderBy('label')
            ->limit(40)
            ->get(['series_key', 'label']);

        return view('admin.datasets.show', compact('dataset', 'seriesSample'));
    }

    public function destroy(Dataset $dataset): RedirectResponse
    {
        $label = $dataset->name;
        $dataset->delete();

        return redirect()
            ->route('admin.datasets.index')
            ->with('status', 'Dataset "'.$label.'" was deleted. All series and observations were removed.');
    }
}
