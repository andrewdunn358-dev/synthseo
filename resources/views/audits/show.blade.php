@extends('layouts.app')
@section('title', $audit->site->name . ' — audit — SynthSEO')

{{-- Auto-refresh ONLY while there is something to wait for. The tag is
     absent entirely once the audit is finished, so a completed report
     never reloads under someone who is reading it - which is worse
     than making them refresh in the first place.

     A meta refresh rather than JS polling: it needs no script, works
     with JS disabled, and cannot leave a timer running after the user
     navigates away. The queue is cron-driven and fires once a minute,
     so 5s is frequent enough to feel responsive without hammering
     the server. --}}
@if (in_array($audit->status, ['queued', 'running']) || $audit->isRecommendationsPending())
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }

  /* Headline leads with what was actually tested, the way a report
     should - "Audit" on its own said nothing a URL doesn't say better. */
  .report-head{ margin-top:var(--sp-3); }
  .report-head .kicker{ font-size:var(--fs-sm); color:var(--grey); margin-bottom:2px; }
  .report-head h1{ font-size:var(--fs-2xl); line-height:1.08; word-break:break-word; }
  .report-meta{ display:flex; gap:var(--sp-5); flex-wrap:wrap; margin-top:var(--sp-3);
                font-size:var(--fs-sm); color:var(--grey); }
  .counts{ display:flex; gap:10px; flex-wrap:wrap; margin-top:var(--sp-4); }

  {{-- Plain-English labels, not bare acronyms - "LCP 4.4s" means
       nothing to someone who's never heard of Core Web Vitals. Colour
       banded the same way the four gauges are, so a reader can tell
       good from bad at a glance even without understanding what the
       number itself means. Regular text, not the monospace treatment
       the old acronym+number pairs used - these are sentences now,
       not terse data values. --}}
  .vitals{ display:flex; gap:26px; flex-wrap:wrap; margin-top:var(--sp-5); font-size:var(--fs-sm); }
  .vitals .vital{ font-weight:500; color:var(--grey); }
  .vitals .vital.good{ color:var(--good); }
  .vitals .vital.fair{ color:var(--fair); }
  .vitals .vital.poor{ color:var(--poor); }

  .article{ font-size:var(--fs-base); line-height:1.7; white-space:pre-wrap; }

  /*
   | All Checks, restructured from a wall of colourful pills into
   | grouped table-like rows - a status dot instead of a badge per
   | check, a group header carrying the count instead of a separate
   | quick-reference strip duplicating the same information below it.
   */
  .check-group{ display:flex; align-items:baseline; justify-content:space-between; gap:12px;
                padding:16px 2px 8px; border-top:1px solid var(--border); }
  .check-group:first-child{ border-top:0; padding-top:0; }
  .check-group-title{ font-size:var(--fs-xs); font-weight:650; color:var(--grey-dim);
                       text-transform:uppercase; letter-spacing:.04em; }
  .check-group-count{ font-size:var(--fs-xs); color:var(--grey); }
  .check-group-count.poor{ color:var(--poor); font-weight:600; }

  .check-row{ display:flex; align-items:flex-start; gap:10px; padding:9px 2px; }
  .check-title{ font-size:var(--fs-sm); font-weight:500; }
  .check-detail{ font-size:var(--fs-xs); color:var(--grey); margin-top:3px; line-height:1.5; }
  .check-value{ font-family:'IBM Plex Mono',monospace; font-size:var(--fs-2xs); color:var(--grey-dim);
                margin-top:5px; word-break:break-word; }

  .check-images{ display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
  .check-image{ display:block; width:84px; text-decoration:none; }
  .check-image img{ width:84px; height:64px; object-fit:cover; border-radius:var(--radius-sm);
                     border:1px solid var(--border-strong); display:block; background:var(--panel-raised); }
  .check-image-savings{ display:block; font-size:var(--fs-2xs); color:var(--grey-dim); margin-top:3px;
                         text-align:center; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $audit->site_id }}">← {{ $audit->site->name }}</a>

  <div class="report-head">
    <div class="kicker muted">Audit · {{ $audit->created_at->format('j M Y, H:i') }}</div>
    <h1>{{ $audit->url }}</h1>
    <div class="report-meta">
      <span>{{ ucfirst($audit->status) }}</span>
      @if ($audit->http_status)<span>HTTP {{ $audit->http_status }}</span>@endif
      @if ($audit->response_ms)<span>{{ $audit->response_ms }} ms response</span>@endif
    </div>

    {{-- The bare score out of 100 used to live here and told a reader
         nothing: it was our own invented weighting presented as a
         precise figure, and it moved between runs when response time
         crossed a threshold. Counts of what needs doing are honest and
         immediately actionable - still true here, deliberately not
         turned into a fifth gauge below for the same reason. --}}
    @if ($audit->status === 'completed')
      @php
        $counts = $audit->issueCounts();
      @endphp
      <div class="counts">
        <span class="badge poor">{{ $counts['fail'] }} to fix</span>
        <span class="badge fair">{{ $counts['warn'] }} to review</span>
        <span class="badge good">{{ $counts['pass'] }} passing</span>
        <a class="btn" href="/audits/{{ $audit->id }}/pdf">Download PDF</a>
      </div>
    @endif
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  {{-- Queued and running are shown honestly rather than as an empty
       results page. On shared hosting the queue is cron-driven, so
       there is a real gap between asking for an audit and getting one -
       and pretending otherwise with a fake progress bar would be
       inventing certainty we don't have about when it will start. --}}
  @if (in_array($audit->status, ['queued', 'running']))
    <div class="notice waiting">
      <span class="spinner" aria-hidden="true"></span>
      <span>
        <strong>{{ $audit->status === 'queued' ? 'Waiting to start' : 'Running the checks' }}</strong>
        <span class="muted">
          — this page updates itself, no need to refresh.
          @if ($audit->status === 'queued')
            Audits start on the next scheduled run, usually within a minute.
          @endif
        </span>
      </span>
    </div>
  @endif

  @if ($audit->error)
    <div class="notice">{{ $audit->error }}</div>
  @endif

  {{-- On request, not automatic - see the migration's doc comment.
       Shown near the top because a prioritised "fix these 3 things
       first" is worth more to a client than the raw findings list
       below it, which stays for anyone who wants the detail. --}}
  @if ($audit->status === 'completed')
    <div class="card card--raised card--accent">
      <p class="subhead">AI recommendations</p>

      @if ($audit->recommendations)
        <div class="article">{{ $audit->recommendations }}</div>
        <form method="POST" action="/audits/{{ $audit->id }}/recommendations" style="margin-top:14px">
          @csrf
          <button class="btn" type="submit">Regenerate</button>
        </form>
      @elseif ($audit->isRecommendationsPending())
        <div class="waiting">
          <span class="spinner" aria-hidden="true"></span>
          <span class="muted">Writing the recommendations — this updates itself, no need to refresh.</span>
        </div>
      @else
        <p class="muted" style="margin:0 0 16px">Turn the findings below into a step-by-step plan — written for someone who's never touched a website admin panel, not just someone who isn't an SEO specialist.</p>
        @if ($audit->recommendations_error)
          <div class="notice" style="margin-top:0">{{ $audit->recommendations_error }}</div>
        @endif
        <form method="POST" action="/audits/{{ $audit->id }}/recommendations">
          @csrf
          <button class="btn btn-primary" type="submit">Get AI recommendations</button>
        </form>
      @endif
    </div>
  @endif

  {{-- Google's own numbers, labelled as Google's, shown as real gauges -
       the same visual language PageSpeed Insights itself uses. For a
       client report "Google scores your performance 86" carries weight
       our own figure never could, which is exactly why these stay
       separate from our findings rather than blended into one score.

       Mobile and desktop give materially different numbers - the same
       tab pattern as the site page, but only shown when both actually
       ran; a site with no desktop result (PageSpeed failed, or an
       audit from before this existed) just shows mobile with no
       toggle at all rather than an empty second tab. --}}
  @if ($audit->hasLighthouse())
    <div class="card">
      <div class="section-head" style="margin-bottom:0">
        <p class="subhead" style="margin:0">Site performance</p>
        @if ($audit->hasDesktopLighthouse())
          <div class="tabs" style="margin-top:0; border-bottom:0">
            <button type="button" class="tab-btn" id="strategy-btn-mobile" onclick="showStrategy('mobile')">Mobile</button>
            <button type="button" class="tab-btn" id="strategy-btn-desktop" onclick="showStrategy('desktop')">Desktop</button>
          </div>
        @endif
      </div>

      <div id="lh-mobile" class="tab-panel" style="margin-top:var(--sp-4)">
        <div class="gauges">
          @foreach ([
            'Performance' => $audit->lh_performance,
            'SEO' => $audit->lh_seo,
            'Accessibility' => $audit->lh_accessibility,
            'Best practices' => $audit->lh_best_practices,
          ] as $label => $value)
            @php
              $band = \App\Models\Audit::lighthouseBand($value);
              $r = 46; $circumference = 2 * M_PI * $r;
              $pct = $value !== null ? max(0, min(100, $value)) : 0;
              $offset = $circumference * (1 - $pct / 100);
            @endphp
            <div class="gauge-card">
              <div class="gauge">
                <svg viewBox="0 0 104 104">
                  <circle class="gauge-track" cx="52" cy="52" r="{{ $r }}"/>
                  <circle class="gauge-fill {{ $band }}" cx="52" cy="52" r="{{ $r }}"
                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"/>
                </svg>
                <div class="gauge-value">{{ $value !== null ? $value : '—' }}</div>
              </div>
              <div class="gauge-label">{{ $label }}</div>
            </div>
          @endforeach
        </div>

        <div class="vitals">
          @if ($audit->lh_lcp_ms !== null)
            <span class="vital {{ \App\Models\Audit::lcpBand($audit->lh_lcp_ms) }}">Main content loads in {{ number_format($audit->lh_lcp_ms / 1000, 1) }}s</span>
          @endif
          @if ($audit->lh_tbt_ms !== null)
            <span class="vital {{ \App\Models\Audit::tbtBand($audit->lh_tbt_ms) }}">Page unresponsive for {{ $audit->lh_tbt_ms }}ms while loading</span>
          @endif
          @if ($audit->lh_cls !== null)
            <span class="vital {{ \App\Models\Audit::clsBand($audit->lh_cls) }}">Visual stability {{ rtrim(rtrim(number_format($audit->lh_cls, 3), '0'), '.') ?: '0' }}</span>
          @endif
        </div>

        {{-- Lighthouse follows redirects. If it measured a different URL
             from the one requested, say so - a report that quietly audits
             somewhere else is worse than no report. --}}
        @if ($audit->lighthouse_final_url && rtrim($audit->lighthouse_final_url, '/') !== rtrim($audit->url, '/'))
          <div class="notice" style="margin-top:16px">
            Redirected — the scan measured <strong>{{ $audit->lighthouse_final_url }}</strong>, not the URL as entered.
          </div>
        @endif
      </div>

      @if ($audit->hasDesktopLighthouse())
        @php
          $desktopScores = $audit->lighthouse_desktop['scores'] ?? [];
          $desktopMetrics = $audit->lighthouse_desktop['metrics'] ?? [];
        @endphp
        <div id="lh-desktop" class="tab-panel" style="margin-top:var(--sp-4); display:none">
          <div class="gauges">
            @foreach ([
              'Performance' => $desktopScores['performance'] ?? null,
              'SEO' => $desktopScores['seo'] ?? null,
              'Accessibility' => $desktopScores['accessibility'] ?? null,
              'Best practices' => $desktopScores['best-practices'] ?? null,
            ] as $label => $value)
              @php
                $band = \App\Models\Audit::lighthouseBand($value);
                $r = 46; $circumference = 2 * M_PI * $r;
                $pct = $value !== null ? max(0, min(100, $value)) : 0;
                $offset = $circumference * (1 - $pct / 100);
              @endphp
              <div class="gauge-card">
                <div class="gauge">
                  <svg viewBox="0 0 104 104">
                    <circle class="gauge-track" cx="52" cy="52" r="{{ $r }}"/>
                    <circle class="gauge-fill {{ $band }}" cx="52" cy="52" r="{{ $r }}"
                      stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"/>
                  </svg>
                  <div class="gauge-value">{{ $value !== null ? $value : '—' }}</div>
                </div>
                <div class="gauge-label">{{ $label }}</div>
              </div>
            @endforeach
          </div>

          <div class="vitals">
            @if (($desktopMetrics['lcp_ms'] ?? null) !== null)
              <span class="vital {{ \App\Models\Audit::lcpBand($desktopMetrics['lcp_ms']) }}">Main content loads in {{ number_format($desktopMetrics['lcp_ms'] / 1000, 1) }}s</span>
            @endif
            @if (($desktopMetrics['tbt_ms'] ?? null) !== null)
              <span class="vital {{ \App\Models\Audit::tbtBand($desktopMetrics['tbt_ms']) }}">Page unresponsive for {{ $desktopMetrics['tbt_ms'] }}ms while loading</span>
            @endif
            @if (($desktopMetrics['cls'] ?? null) !== null)
              <span class="vital {{ \App\Models\Audit::clsBand($desktopMetrics['cls']) }}">Visual stability {{ rtrim(rtrim(number_format($desktopMetrics['cls'], 3), '0'), '.') ?: '0' }}</span>
            @endif
          </div>

          @if (($audit->lighthouse_desktop['final_url'] ?? null) && rtrim($audit->lighthouse_desktop['final_url'], '/') !== rtrim($audit->url, '/'))
            <div class="notice" style="margin-top:16px">
              Redirected — the scan measured <strong>{{ $audit->lighthouse_desktop['final_url'] }}</strong>, not the URL as entered.
            </div>
          @endif
        </div>
      @endif
    </div>
  @elseif ($audit->lighthouse_error)
    <div class="notice">Site performance: {{ $audit->lighthouse_error }}</div>
  @endif

  {{-- Grouped like a real checklist, not a wall of colourful pills -
       a group header carrying the issue count, each check a compact
       row with a status dot. Passes get one plain line; fails/warns
       get their detail shown inline, since that detail is the actual
       advice, not something worth hiding behind a click.

       On-page findings are never strategy-specific, so they always
       show. Performance findings are split into a mobile and a desktop
       set (only one visible at a time, driven by the same toggle as
       the gauges above) - "Improve image delivery" can genuinely flag
       different images, or not flag anything at all, between the two. --}}
  @if ($findings->isNotEmpty())
    @php
      $onPageFindings = $findings->where('source', 'synthseo');
      $mobileFindings = $findings->where('source', 'lighthouse')->where('strategy', 'mobile');
      $desktopFindings = $findings->where('source', 'lighthouse')->where('strategy', 'desktop');
    @endphp
    <div class="card reveal">
      <p class="subhead">All checks</p>

      @if ($onPageFindings->isNotEmpty())
        @php $onPageIssues = $onPageFindings->whereIn('status', ['fail', 'warn'])->count(); @endphp
        <div class="check-group">
          <span class="check-group-title">On-page</span>
          <span class="check-group-count {{ $onPageIssues ? 'poor' : '' }}">
            {{ $onPageIssues ? $onPageIssues . ' ' . \Illuminate\Support\Str::plural('issue', $onPageIssues) : 'All good' }}
          </span>
        </div>
        @foreach ($onPageFindings as $finding)
          @include('audits.partials.finding-row', ['finding' => $finding])
        @endforeach
      @endif

      @if ($mobileFindings->isNotEmpty())
        @php $mobileIssues = $mobileFindings->whereIn('status', ['fail', 'warn'])->count(); @endphp
        <div id="checks-mobile" class="tab-panel">
          <div class="check-group">
            <span class="check-group-title">Performance (mobile)</span>
            <span class="check-group-count {{ $mobileIssues ? 'poor' : '' }}">
              {{ $mobileIssues ? $mobileIssues . ' ' . \Illuminate\Support\Str::plural('issue', $mobileIssues) : 'All good' }}
            </span>
          </div>
          @foreach ($mobileFindings as $finding)
            @include('audits.partials.finding-row', ['finding' => $finding])
          @endforeach
        </div>
      @endif

      @if ($desktopFindings->isNotEmpty())
        @php $desktopIssues = $desktopFindings->whereIn('status', ['fail', 'warn'])->count(); @endphp
        <div id="checks-desktop" class="tab-panel" style="display:none">
          <div class="check-group">
            <span class="check-group-title">Performance (desktop)</span>
            <span class="check-group-count {{ $desktopIssues ? 'poor' : '' }}">
              {{ $desktopIssues ? $desktopIssues . ' ' . \Illuminate\Support\Str::plural('issue', $desktopIssues) : 'All good' }}
            </span>
          </div>
          @foreach ($desktopFindings as $finding)
            @include('audits.partials.finding-row', ['finding' => $finding])
          @endforeach
        </div>
      @endif
    </div>
  @elseif (! in_array($audit->status, ['queued', 'running']))
    <div class="card muted">No findings were recorded for this audit.</div>
  @endif
</div>

<script>
function showStrategy(strategy) {
  var mobileEls = ['lh-mobile', 'checks-mobile'];
  var desktopEls = ['lh-desktop', 'checks-desktop'];
  mobileEls.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = strategy === 'mobile' ? '' : 'none';
  });
  desktopEls.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = strategy === 'desktop' ? '' : 'none';
  });
  var mobileBtn = document.getElementById('strategy-btn-mobile');
  var desktopBtn = document.getElementById('strategy-btn-desktop');
  if (mobileBtn) mobileBtn.classList.toggle('active', strategy === 'mobile');
  if (desktopBtn) desktopBtn.classList.toggle('active', strategy === 'desktop');
  sessionStorage.setItem('auditStrategy', strategy);
}
showStrategy(sessionStorage.getItem('auditStrategy') || 'mobile');
</script>
@endsection
