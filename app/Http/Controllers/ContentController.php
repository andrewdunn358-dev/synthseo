<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateContentPiece;
use App\Models\ContentPiece;
use App\Models\Site;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
        ]);

        $piece = $site->content()->create([
            'account_id' => $site->account_id,
            'topic' => $data['topic'],
            'status' => 'queued',
        ]);

        GenerateContentPiece::dispatch($piece->id);

        return redirect('/content/' . $piece->id)
            ->with('status', 'Content queued. It will generate within a minute.');
    }

    public function show(ContentPiece $contentPiece)
    {
        $contentPiece->load('site');

        return view('content.show', ['piece' => $contentPiece]);
    }
}
