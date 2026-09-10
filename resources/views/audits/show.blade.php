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

  /* Sentence-case subheadings carrying weight, not tracked-out capitals
     doing the work with spacing instead of typography. */
  .subhead{ font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:var(--fs-md);
            margin:0 0 var(--sp-3); }

  .vitals{ display:flex; gap:26px; flex-wrap:wrap; margin-top:var(--sp-5);
           font-family:'IBM Plex Mono',monospace; font-size:var(--fs-sm); color:var(--grey); }
  .srctag{ font-size:var(--fs-2xs); letter-spacing:.05em; color:var(--grey-dim);
           border:1px solid var(--border-strong); padding:2px 6px; border-radius:4px; margin-left:8px; }

  .find{ padding:16px 0; border-bottom:1px solid var(--border); }
  .find:last-child{ border-bottom:0; }
  .tag{ font-size:11px; font-weight:600; padding:3px 8px; border-radius:4px; margin-right:10px; }
  .t-fail{ background:rgba(240,100,90,.16); color:var(--poor); }
  .t-warn{ background:rgba(240,166,62,.16); color:var(--fair); }
  .t-pass{ background:rgba(79,214,156,.14); color:var(--good); }
  .ftitle{ font-weight:600; }
  .fdetail{ color:var(--grey); font-size:var(--fs-sm); margin-top:5px; }
  .fvalue{ font-family:'IBM Plex Mono',monospace; font-size:12.5px; color:var(--grey-dim);
           margin-top:7px; word-break:break-word; }

  .chiprow{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
  .chiprow:last-child{ margin-bottom:0; }
  .chiplabel{ font-size:var(--fs-2xs); letter-spacing:.04em; color:var(--grey-dim);
              width:78px; flex-shrink:0; }
  .chip{ font-size:12.5px; padding:5px 10px; border-radius:14px; text-decoration:none;
         border:1px solid transparent; white-space:nowrap; }
  .chip.c-fail{ background:rgba(240,100,90,.14); color:var(--poor); border-color:rgba(240,100,90,.3); }
  .chip.c-warn{ background:rgba(240,166,62,.14); color:var(--fair); border-color:rgba(240,166,62,.3); }
  .chip.c-pass{ background:rgba(79,214,156,.10); color:var(--good); border-color:rgba(79,214,156,.2); }
  .chip:hover{ filter:brightness(1.15); }

  .article{ font-size:var(--fs-base); line-height:1.7; white-space:pre-wrap; }
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
      @elseif ($audit->isRecommendationsPending())
        <div class="waiting">
          <span class="spinner" aria-hidden="true"></span>
          <span class="muted">Writing the recommendations — this updates itself, no need to refresh.</span>
        </div>
      @else
        <p class="muted" style="margin:0 0 16px">Turn the findings below into a short, prioritised action plan — written for someone who isn't an SEO specialist.</p>
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
       separate from our findings rather than blended into one score. --}}
  @if ($audit->hasLighthouse())
    <div class="card">
      <p class="subhead">Google Lighthouse</p>
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
        @if ($audit->lh_lcp_ms !== null)<span>LCP {{ number_format($audit->lh_lcp_ms / 1000, 1) }}s</span>@endif
        @if ($audit->lh_tbt_ms !== null)<span>TBT {{ $audit->lh_tbt_ms }}ms</span>@endif
        @if ($audit->lh_cls !== null)<span>CLS {{ rtrim(rtrim(number_format($audit->lh_cls, 3), '0'), '.') }}</span>@endif
        @if ($audit->lighthouse_strategy)<span>{{ ucfirst($audit->lighthouse_strategy) }}</span>@endif
      </div>

      {{-- Lighthouse follows redirects. If it measured a different URL
           from the one requested, say so - a report that quietly audits
           somewhere else is worse than no report. --}}
      @if ($audit->lighthouse_final_url && rtrim($audit->lighthouse_final_url, '/') !== rtrim($audit->url, '/'))
        <div class="notice" style="margin-top:16px">
          Redirected — Google measured <strong>{{ $audit->lighthouse_final_url }}</strong>, not the URL as entered.
        </div>
      @endif
    </div>
  @elseif ($audit->lighthouse_error)
    <div class="notice">Google Lighthouse: {{ $audit->lighthouse_error }}</div>
  @endif

  {{-- Quick-reference strip: every check that ran, at a glance, in the
       original check order rather than fail-first - so "did the
       broken-link check even run" is answerable without scrolling
       past everything else to find one pass among fifteen. Each chip
       jumps to its full entry below. --}}
  @if ($findings->isNotEmpty())
    <div class="card">
      <p class="subhead">All checks</p>
      @foreach ($findings->groupBy('source') as $source => $group)
        <div class="chiprow">
          <span class="chiplabel">{{ $source === 'lighthouse' ? 'Lighthouse' : 'On-page' }}</span>
          @foreach ($group as $finding)
            <a class="chip c-{{ $finding->status }}" href="#check-{{ $finding->id }}">{{ $finding->title }}</a>
          @endforeach
        </div>
      @endforeach
    </div>
  @endif

  @if ($findings->isNotEmpty())
    <div class="card">
      @foreach ($findings as $finding)
        <div class="find" id="check-{{ $finding->id }}">
          <span class="tag t-{{ $finding->status }}">{{ $finding->status }}</span>
          <span class="ftitle">{{ $finding->title }}</span>
          @if ($finding->source === 'lighthouse')<span class="srctag">Lighthouse</span>@endif
          @if ($finding->detail)<div class="fdetail">{{ $finding->detail }}</div>@endif
          @if ($finding->value)<div class="fvalue">{{ $finding->value }}</div>@endif
        </div>
      @endforeach
    </div>
  @elseif (! in_array($audit->status, ['queued', 'running']))
    <div class="card muted">No findings were recorded for this audit.</div>
  @endif
</div>
@endsection
