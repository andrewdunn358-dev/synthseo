<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSocialPost;
use App\Models\SocialPost;
use App\Models\Site;
use Illuminate\Http\Request;

class SocialPostController extends Controller
{
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:general,instagram,facebook,linkedin'],
        ]);

        $post = $site->socialPosts()->create([
            'account_id' => $site->account_id,
            'topic' => $data['topic'],
            'platform' => $data['platform'],
            'status' => 'queued',
        ]);

        GenerateSocialPost::dispatch($post->id);

        return redirect('/social/' . $post->id)
            ->with('status', 'Post queued. It will be ready within a minute.');
    }

    public function show(SocialPost $post)
    {
        $post->load('site');

        return view('social.show', ['post' => $post]);
    }
}
