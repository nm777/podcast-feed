<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedAddItemsRequest;
use App\Http\Requests\FeedStoreRequest;
use App\Http\Resources\FeedItemResource;
use App\Http\Resources\FeedResource;
use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    /**
     * Display a listing of resource.
     */
    public function index()
    {
        $feeds = Feed::where('user_id', Auth::user()->id)->get();

        return FeedResource::collection($feeds);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FeedStoreRequest $request)
    {
        $validated = $request->validated();

        $feed = Feed::create([
            'user_id' => Auth::user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cover_image_url' => $validated['cover_image_url'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'slug' => $validated['slug'],
            'user_guid' => (string) Str::uuid(),
            'token' => (string) Str::random(32),
        ]);

        return (new FeedResource($feed))->response()->setStatusCode(201);
    }

    public function addItems(FeedAddItemsRequest $request, Feed $feed)
    {
        $this->authorize('update', $feed);

        $validated = $request->validated();

        foreach ($validated['items'] as $item) {
            $feed->items()->create($item);
        }

        return FeedItemResource::collection($feed->items)->response()->setStatusCode(201);
    }

    public function removeItems(Request $request, Feed $feed)
    {
        $this->authorize('update', $feed);

        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|exists:feed_items,id,feed_id,'.$feed->id,
        ]);

        $feed->items()->whereIn('id', $request->item_ids)->delete();

        return response()->json(null, 204);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
