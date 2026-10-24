<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialCategoryRequest;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Services\Cache\DashboardCacheService;
use App\Services\CategoryMonthlyService;
use App\Services\ExpectedTotalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $table = CategoryMonthlyService::build($month, 4);
        $categories = FinancialCategory::orderBy('name')->get()->keyBy('id');
        $counts = FinancialMovement::whereNotNull('category_id')->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')->pluck('total', 'category_id');

        $rows = collect($table['groups'])->map(fn ($rows) => collect($rows)->map(function ($row) use ($categories, $counts, $month) {
            $category = $categories[$row->id];
            $row->month = $row->values[$month];
            $row->active = (bool) $category->active;
            $row->movements = (int) ($counts[$row->id] ?? 0);
            return $row;
        })->sortBy([fn ($a, $b) => $b->active <=> $a->active, fn ($a, $b) => $b->month <=> $a->month])->values());

        return view('financial-categories.index', [
            'month' => $month,
            'rows' => $rows,
            'categories' => $categories,
            'openModal' => $request->input('modal'),
        ]);
    }

    // the forms live in modals on the index
    public function create(): RedirectResponse
    {
        return redirect()->route('financial-categories.index', ['modal' => 'category-new']);
    }

    public function show($id): RedirectResponse
    {
        return redirect()->route('financial-categories.index');
    }

    public function edit($id): RedirectResponse
    {
        return redirect()->route('financial-categories.index', ['modal' => 'category-edit-' . (int) $id]);
    }

    public function store(FinancialCategoryRequest $request): RedirectResponse|JsonResponse
    {
        // quick creation from the statement import preview and the movement form
        if ($request->expectsJson()) {
            return response()->json(FinancialCategory::create($request->validated()), 201);
        }

        FinancialCategory::create(self::withSignedForecast($request->validated(), $request));
        DashboardCacheService::clearAllByUser();

        return Redirect::route('financial-categories.index')->with('success', __('Category created.'));
    }

    public function update(FinancialCategoryRequest $request, FinancialCategory $financial_category): RedirectResponse
    {
        $financial_category->update(self::withSignedForecast($request->validated(), $request));
        DashboardCacheService::clearAllByUser();

        return Redirect::route('financial-categories.index')->with('success', __('Category updated.'));
    }

    /**
     * A category with movements can only be deleted by moving them to another one.
     */
    public function destroy(Request $request, FinancialCategory $financial_category): RedirectResponse
    {
        $data = $request->validate([
            'move_to' => ['nullable', Rule::exists('financial_categories', 'id')->where('user_id', auth()->id())],
        ]);
        $movements = $financial_category->financials()->count();

        if ($movements > 0 && empty($data['move_to'])) {
            return Redirect::route('financial-categories.index')
                ->with('error', __('Choose where to move the :count movements of :category.', ['count' => $movements, 'category' => $financial_category->name]));
        }
        if (($data['move_to'] ?? null) == $financial_category->id) {
            return Redirect::route('financial-categories.index')->with('error', __('Choose another category.'));
        }

        DB::transaction(function () use ($financial_category, $data) {
            if (!empty($data['move_to'])) {
                $financial_category->financials()->update(['category_id' => $data['move_to']]);
            }
            $financial_category->delete();
        });
        DashboardCacheService::clearAllByUser();

        return Redirect::route('financial-categories.index')->with('success', $movements
            ? __(':count movements moved and category deleted.', ['count' => $movements])
            : __('Category deleted.'));
    }

    /**
     * CSV lines "type,name" (type: expense or income); existing names are skipped.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:1024']);

        $created = 0;
        foreach (file($request->file('file')->getRealPath()) as $line) {
            [$type, $name] = array_pad(array_map('trim', str_getcsv($line)), 2, null);
            if (!$name || !in_array($type, ['expense', 'income'], true)) {
                continue;
            }
            $category = FinancialCategory::firstOrCreate(['name' => $name], ['type' => $type, 'active' => true]);
            $created += $category->wasRecentlyCreated ? 1 : 0;
        }

        return Redirect::route('financial-categories.index')
            ->with('success', __(':count categories imported.', ['count' => $created]));
    }

    public function updateExpectedValues(Request $request, ExpectedTotalService $expectedTotalService): JsonResponse
    {
        $data = $request->validate([
            'expected_values' => ['required', 'array'],
            'expected_values.*' => ['numeric'],
        ]);

        try {
            $expectedTotalService->bulkUpdate($data['expected_values'], auth()->id());
            DashboardCacheService::clearAllByUser();

            return response()->json([
                'status' => 'success',
                'message' => __('Expected values updated successfully.'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => __('Something went wrong')], 500);
        }
    }

    /**
     * The form takes a positive monthly forecast; expenses are stored negative.
     */
    private static function withSignedForecast(array $data, Request $request): array
    {
        $forecast = abs((float) ($data['expected_total'] ?? 0));
        $data['expected_total'] = $data['type'] === 'expense' ? -$forecast : $forecast;
        $data['active'] = $request->boolean('active');
        return $data;
    }
}
