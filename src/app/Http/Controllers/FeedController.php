<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedRequest;
use App\Models\Feed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FeedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $feeds = Auth::user()->feeds()->latest()->get();

        if (request()->expectsJson()) {
            return response()->json($feeds);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FeedRequest $request)
    {
        $validated = $request->validated();

        $feed = Auth::user()->feeds()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => Str::slug($validated['title']),
            'user_guid' => Str::uuid(),
            'token' => Str::random(32),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Feed created successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Feed $feed)
    {
        Gate::authorize('update', $feed);

        $feed->load(['items.libraryItem', 'items.libraryItem.mediaFile']);

        $userLibraryItems = Auth::user()->libraryItems()->with('mediaFile')->get();

        return Inertia::render('feeds/edit', [
            'feed' => $feed,
            'userLibraryItems' => $userLibraryItems,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FeedRequest $request, Feed $feed)
    {
        Gate::authorize('update', $feed);

        $validated = $request->validated();

        $feed->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => Str::slug($validated['title']),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        if (isset($validated['items'])) {
            $feed->items()->delete();

            foreach ($validated['items'] as $index => $item) {
                $feed->items()->create([
                    'library_item_id' => $item['library_item_id'],
                    'sequence' => $index,
                ]);
            }
        }

        return redirect()->route('dashboard')->with('success', 'Feed updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Feed $feed)
    {
        Gate::authorize('delete', $feed);

        $feed->delete();

        if (request()->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('dashboard')->with('success', 'Feed deleted successfully!');
    }
}
