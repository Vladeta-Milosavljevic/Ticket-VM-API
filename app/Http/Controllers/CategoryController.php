<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexCategoryRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     *
     * Supports filtering: ?active_only=1 (only active) or ?archived=1 (only archived).
     */
    public function index(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = Category::query();

        if (filter_var($validated['active_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->active();
        } elseif (filter_var($validated['archived'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_archived', true);
        }

        $categories = $query->orderBy('name')->paginate(15);

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category.
     *
     * Authorization: Admin only.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['slug'] = $this->ensureUniqueSlug(Str::slug($validated['name']));

        $category = Category::create($validated);

        Log::info('Category created', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'category_id' => $category->id,
            'category_name' => $category->name,
        ]);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    /**
     * Update the specified category.
     *
     * Authorization: Admin only.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $baseSlug = Str::slug($validated['name']);
            $validated['slug'] = $this->ensureUniqueSlug($baseSlug, $category->id);
        }

        $category->update($validated);

        Log::info('Category updated', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'category_id' => $category->id,
            'category_name' => $category->name,
        ]);

        return new CategoryResource($category->fresh());
    }

    /**
     * Archive the specified category.
     *
     * Authorization: Admin only.
     * Archived categories remain visible on existing tickets but cannot be used for new tickets.
     */
    public function archive(Request $request, Category $category): CategoryResource|JsonResponse
    {
        $this->authorize('archive', $category);

        if ($category->is_archived) {
            return response()->json(['message' => 'Category is already archived'], 400);
        }

        $category->update(['is_archived' => true]);

        Log::info('Category archived', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'category_id' => $category->id,
            'category_name' => $category->name,
        ]);

        return new CategoryResource($category->fresh());
    }

    /**
     * Reactivate the specified category.
     *
     * Authorization: Admin only.
     */
    public function reactivate(Request $request, Category $category): CategoryResource|JsonResponse
    {
        $this->authorize('reactivate', $category);

        if (! $category->is_archived) {
            return response()->json(['message' => 'Category is not archived'], 400);
        }

        $category->update(['is_archived' => false]);

        Log::info('Category reactivated', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'category_id' => $category->id,
            'category_name' => $category->name,
        ]);

        return new CategoryResource($category->fresh());
    }

    /**
     * Ensure the slug is unique. Appends a number if the base slug already exists.
     */
    private function ensureUniqueSlug(string $baseSlug, ?int $excludeId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $query = Category::where('slug', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            if (! $query->exists()) {
                break;
            }
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
