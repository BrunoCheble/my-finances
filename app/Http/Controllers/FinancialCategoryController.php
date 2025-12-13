<?php
namespace App\Http\Controllers;

use App\Http\Requests\FinancialCategoryRequest;
use App\Models\FinancialCategory;
use App\Services\Cache\DashboardCacheService;
use App\Services\GetFinancialMovementService;
use App\Services\GetFinancialCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Redirect;

class FinancialCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $startDate = $request->input('start_date') ?? date('Y-m-01');
        $endDate = $request->input('end_date') ?? date('Y-m-t');

        $movements = GetFinancialMovementService::execute('', null, null, null, $startDate, $endDate, null, false);
        $categories = FinancialCategory::get();
        $categories = GetFinancialCategoryService::execute($movements, $categories);
        return view('financial-categories.index', compact('categories', 'movements', 'startDate', 'endDate'));
    }

    public function create(): View
    {
        $category = new FinancialCategory();
        return view('financial-categories.create', compact('category'));
    }

    public function store(FinancialCategoryRequest $request): RedirectResponse
    {
        try {
            FinancialCategory::create($request->validated());
        } catch (\Exception $e) {
            return Redirect::route('financial-categories.index')
                ->with('error', __('Something went wrong'));
        }

        return Redirect::route('financial-categories.index')
            ->with('success', 'Financial Category created successfully');
    }

    public function show($id): View
    {
        $category = FinancialCategory::find($id);
        return view('financial-categories.show', compact('category'));
    }

    public function edit($id): View
    {
        $category = FinancialCategory::findOrFail($id);
        return view('financial-categories.edit', compact('category'));
    }

    public function update(FinancialCategoryRequest $request, FinancialCategory $financial_category): RedirectResponse
    {
        try {
            $financial_category->update($request->validated());
            DashboardCacheService::clearAllByUser();
        } catch (\Exception $e) {
            return Redirect::route('financial-categories.index')
                ->with('error', __('Something went wrong'));
        }

        return Redirect::route('financial-categories.index')
            ->with('success', 'Financial Category updated successfully');
    }

    public function import(Request $request): RedirectResponse
    {
        try {
            $file = $request->file('file');
            $lines = file($file->getRealPath());

            foreach ($lines as $line) {
                $data = str_getcsv($line);
                FinancialCategory::create([
                    'name' => $data[1],
                    'type' => $data[0],
                ]);
            }
        } catch (\Exception $e) {
            dd($e);
            return Redirect::route('financial-categories.index')
                ->with('error', __('Something went wrong during import'));
        }

        return Redirect::route('financial-categories.index')
            ->with('success', 'Financial Categories imported successfully');
    }

    public function destroy($id): RedirectResponse
    {
        try {
            $category = FinancialCategory::find($id);

            if ($category->financials()->exists()) {
                 return Redirect::route('financial-categories.index')
                     ->with('error', __('Financial Category can not be deleted'));
            }

            $category->delete();

        } catch (\Exception $e) {
            return Redirect::route('financial-categories.index')
                ->with('error', __('Something went wrong'));
        }

        return Redirect::route('financial-categories.index')->with('success', 'Financial Category deleted successfully.');
    }
}

