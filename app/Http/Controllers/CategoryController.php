<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = Auth::user();

        $categories = Category::forUser($user->id)
            ->withCount('subscriptions')
            ->orderBy('name')
            ->get();

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('categories/create', [
            'defaultColors' => Category::getDefaultColors(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($user) {
                    if (Category::forUser($user->id)->where('name', $value)->exists()) {
                        $fail('A category with this name already exists.');
                    }
                },
            ],
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], [
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000).',
        ]);

        $category = $user->categories()->create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return to_route('categories.index')
            ->with('success', "Category '{$category->name}' created successfully!");
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): Response
    {
        $user = Auth::user();

        // Ensure user owns this category
        if ($category->user_id !== $user->id) {
            abort(403, 'Unauthorized access to category.');
        }

        $category->load(['subscriptions.currency']);

        return Inertia::render('categories/show', [
            'category' => $category,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category): Response
    {
        $user = Auth::user();

        // Ensure user owns this category
        if ($category->user_id !== $user->id) {
            abort(403, 'Unauthorized access to category.');
        }

        return Inertia::render('categories/edit', [
            'category' => $category,
            'defaultColors' => Category::getDefaultColors(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $user = Auth::user();

        // Ensure user owns this category
        if ($category->user_id !== $user->id) {
            abort(403, 'Unauthorized access to category.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($user, $category) {
                    if (Category::forUser($user->id)->where('name', $value)->where('id', '!=', $category->id)->exists()) {
                        $fail('A category with this name already exists.');
                    }
                },
            ],
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], [
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF0000).',
        ]);

        $category->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return to_route('categories.index')
            ->with('success', "Category '{$category->name}' updated successfully!");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $user = Auth::user();

        // Ensure user owns this category
        if ($category->user_id !== $user->id) {
            abort(403, 'Unauthorized access to category.');
        }

        // Check if category can be deleted
        if (! $category->canBeDeleted()) {
            return back()->withErrors(['category' => $category->getDeletionBlockReason()]);
        }

        $categoryName = $category->name;
        $category->delete();

        return to_route('categories.index')
            ->with('success', "Category '{$categoryName}' deleted successfully!");
    }
}
