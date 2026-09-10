<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAuditRecommendations;
use App\Jobs\RunSeoAudit;
use App\Models\Audit;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $audit->load('site', 'findings');

        // Ordered so the things worth fixing are at the top: failures
        // before warnings before passes, then by severity. A client
        // should not have to scroll past what is already fine.
        $findings = $audit->findings()
            ->orderByRaw("FIELD(status,'fail','warn','pass')")
            ->orderByRaw("FIELD(severity,'high','medium','low')")
            ->get();

        return view('audits.show', compact('audit', 'findings'));
    }

    public function recommend(Audit $audit)
    {
        $audit->update(['recommendations_status' => 'queued']);

        GenerateAuditRecommendations::dispatch($audit->id);

        return redirect('/audits/' . $audit->id)
            ->with('status', 'Generating recommendations. It will be ready within a minute.');
    }

    /**
     * Streams a client-ready PDF of a completed audit.
     *
     * Deliberately its own simple template rather than the web view -
     * dompdf renders a fixed subset of CSS (no flexbox, no reliable
     * partial-arc SVG), so the circular gauges become plain numbers
     * here instead of quietly rendering wrong in a document a client
     * might actually print or forward.
     */
    public function pdf(Audit $audit)
    {
        abort_unless($audit->status === 'completed', 404);

        $audit->load('site');

        $findings = $audit->findings()
            ->orderByRaw("FIELD(status,'fail','warn','pass')")
            ->orderByRaw("FIELD(severity,'high','medium','low')")
            ->get();

        $pdf = Pdf::loadView('audits.pdf', compact('audit', 'findings'))
            ->setPaper('a4');

        $filename = 'synthseo-audit-' . $audit->site->name . '-' . $audit->created_at->format('Y-m-d') . '.pdf';
        $filename = preg_replace('/[^A-Za-z0-9\-]+/', '-', $filename);

        return $pdf->download($filename);
    }
}
