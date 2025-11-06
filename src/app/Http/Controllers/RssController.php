<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class RssController extends Controller
{
    public function show(Request $request, $user_guid, $feed_slug)
    {
        $feed = Feed::where('user_guid', $user_guid)->where('slug', $feed_slug)->firstOrFail();

        if (!$feed->is_public && $request->token !== $feed->token) {
            abort(403);
        }

        $xml = view('rss', compact('feed'))->render();

        return Response::make($xml, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }
}
