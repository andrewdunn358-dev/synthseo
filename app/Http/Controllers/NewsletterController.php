<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateNewsletter;
use App\Models\Newsletter;
use App\Models\Site;
use App\Services\ResendService;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
        ]);

        $newsletter = $site->newsletters()->create([
            'account_id' => $site->account_id,
            'topic' => $data['topic'],
            'status' => 'queued',
        ]);

        GenerateNewsletter::dispatch($newsletter->id);

        return redirect('/newsletters/' . $newsletter->id)
            ->with('status', 'Newsletter queued. It will be ready within a minute.');
    }

    public function show(Newsletter $newsletter)
    {
        $newsletter->load('site');

        return view('newsletters.show', ['newsletter' => $newsletter]);
    }

    /**
     * The second, explicit step - see the migration's doc comment.
     * Ensures the site has a Resend Audience (created lazily on first
     * send, not when the site is added), then sends the reviewed draft
     * to it as a Broadcast.
     */
    public function send(Newsletter $newsletter, ResendService $resend)
    {
        $newsletter->load('site');
        $site = $newsletter->site;

        if ($newsletter->isSent()) {
            return redirect('/newsletters/' . $newsletter->id);
        }

        if (! $site->resend_audience_id) {
            $audience = $resend->createAudience($site->name);

            if ($audience['error']) {
                $newsletter->update(['error' => $audience['error']]);

                return redirect('/newsletters/' . $newsletter->id)->with('status', 'Could not send: ' . $audience['error']);
            }

            $site->update(['resend_audience_id' => $audience['audience_id']]);
        }

        // The unsubscribe placeholder is added here, not stored as part
        // of the draft body a person reviews - it would be confusing
        // clutter in something meant to read as plain newsletter copy,
        // and it only matters at the point of actually sending.
        $html = '<div style="font-family:sans-serif; font-size:15px; line-height:1.6; white-space:pre-wrap;">'
            . e($newsletter->body)
            . '</div><p style="font-size:12px; color:#888; margin-top:24px;">'
            . 'Unsubscribe: {{{RESEND_UNSUBSCRIBE_URL}}}</p>';

        $fromAddress = $site->name . ' via SynthSEO <' . config('services.resend.from_address') . '>';

        $result = $resend->sendBroadcast($site->resend_audience_id, $fromAddress, $newsletter->subject, $html);

        if ($result['error']) {
            $newsletter->update(['error' => $result['error']]);

            return redirect('/newsletters/' . $newsletter->id)->with('status', 'Could not send: ' . $result['error']);
        }

        $newsletter->update([
            'resend_broadcast_id' => $result['broadcast_id'],
            'sent_at' => now(),
            'error' => null,
        ]);

        return redirect('/newsletters/' . $newsletter->id)->with('status', 'Newsletter sent.');
    }

    /**
     * No local subscriber storage - see ResendService's class doc for
     * why. This just proxies one contact into the site's Resend
     * Audience, creating the audience first if this is the site's
     * first subscriber.
     */
    public function addSubscriber(Request $request, Site $site, ResendService $resend)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $site->resend_audience_id) {
            $audience = $resend->createAudience($site->name);

            if ($audience['error']) {
                return redirect('/sites/' . $site->id)->with('status', 'Could not add subscriber: ' . $audience['error']);
            }

            $site->update(['resend_audience_id' => $audience['audience_id']]);
        }

        $result = $resend->addSubscriber($site->resend_audience_id, $data['email'], $data['name'] ?? null);

        return redirect('/sites/' . $site->id)->with(
            'status',
            $result['error'] ? 'Could not add subscriber: ' . $result['error'] : 'Subscriber added.',
        );
    }
}
