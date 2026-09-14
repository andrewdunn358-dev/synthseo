<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateNewsletter;
use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
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
     * The second, explicit step - see the migration's doc comment on
     * newsletters. Sends one plain email per subscriber via
     * ResendService::sendEmail() rather than a single Broadcast call -
     * see newsletter_subscribers' own migration doc comment for why
     * (Sending-access API key, no Audiences/Broadcasts available).
     * Genuinely slower and loses Resend's built-in unsubscribe
     * handling, both acceptable trade-offs for a feature still being
     * tried out at a handful of subscribers, not thousands.
     */
    public function send(Newsletter $newsletter, ResendService $resend)
    {
        $newsletter->load('site');

        if ($newsletter->isSent()) {
            return redirect('/newsletters/' . $newsletter->id);
        }

        $subscribers = $newsletter->site->subscribers;

        if ($subscribers->isEmpty()) {
            return redirect('/newsletters/' . $newsletter->id)
                ->with('status', 'No subscribers to send to yet - add one below first.');
        }

        $html = '<div style="font-family:sans-serif; font-size:15px; line-height:1.6; white-space:pre-wrap;">'
            . e($newsletter->body) . '</div>';

        $fromAddress = $newsletter->site->name . ' via SynthSEO <' . config('services.resend.from_address') . '>';

        $sent = 0;
        $lastError = null;

        foreach ($subscribers as $subscriber) {
            $result = $resend->sendEmail($subscriber->email, $fromAddress, $newsletter->subject, $html);

            if ($result['error']) {
                $lastError = $result['error'];

                continue;
            }

            $sent++;
        }

        if ($sent === 0) {
            $newsletter->update(['error' => $lastError]);

            return redirect('/newsletters/' . $newsletter->id)->with('status', 'Could not send: ' . $lastError);
        }

        $newsletter->update(['sent_at' => now(), 'error' => null]);

        $status = $sent === $subscribers->count()
            ? "Sent to all {$sent} subscribers."
            : "Sent to {$sent} of {$subscribers->count()} subscribers - the rest failed ({$lastError}).";

        return redirect('/newsletters/' . $newsletter->id)->with('status', $status);
    }

    /**
     * Stored locally now, not in a Resend Audience - see
     * newsletter_subscribers' migration doc comment for why. firstOrCreate
     * on [site_id, email] so re-adding the same address updates the
     * name rather than erroring on the table's unique constraint.
     */
    public function addSubscriber(Request $request, Site $site)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        NewsletterSubscriber::updateOrCreate(
            ['site_id' => $site->id, 'email' => $data['email']],
            ['account_id' => $site->account_id, 'name' => $data['name'] ?? null],
        );

        return redirect('/sites/' . $site->id)->with('status', 'Subscriber added.');
    }

    public function destroySubscriber(NewsletterSubscriber $subscriber)
    {
        $siteId = $subscriber->site_id;
        $subscriber->delete();

        return redirect('/sites/' . $siteId)->with('status', 'Subscriber removed.');
    }
}
