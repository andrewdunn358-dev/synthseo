<?php

namespace App\Http\Controllers;

use App\Jobs\RunSeoAudit;
use App\Models\Audit;
use App\Models\Site;

class AuditController extends Controller
{
    public function store(Site $site)
    {
        $audit = $site->audits()->create([
            'account_id' => $site->account_id,
            'url' => $site->url,
            'status' => 'queued',
        ]);

        RunSeoAudit::dispatch($audit->id);

        return redirect('/audits/' . $audit->id)
            ->with('status', 'Audit queued. It will run within a minute.');
    }

    public function show(Audit $audit)
    {
        $audit->load('site');

        // Ordered so the things worth fixing are at the top: failures
        // before warnings before passes, then by severity. A client
        // should not have to scroll past what is already fine.
        $findings = $audit->findings()
            ->orderByRaw("FIELD(status,'fail','warn','pass')")
            ->orderByRaw("FIELD(severity,'high','medium','low')")
            ->get();

        return view('audits.show', compact('audit', 'findings'));
    }
}
