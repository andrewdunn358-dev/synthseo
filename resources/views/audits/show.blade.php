@extends('layouts.app')
@section('title', 'Audit — ' . $audit->site->name)

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
  .wrap-pad{ padding:40px 32px 80px; }
  .row-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .muted{ color:var(--grey); }
  .url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:13px; word-break:break-all; }
  .panel{ background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:22px; margin-top:22px; }
  .counts{ display:flex; gap:10px; flex-wrap:wrap; }
  .count{ font-family:'IBM Plex Mono',monospace; font-weight:600; font-size:14px;
          padding:8px 14px; border-radius:8px; white-space:nowrap; }
  .lh{ display:flex; gap:14px; flex-wrap:wrap; margin-top:6px; }
  .lhcard{ flex:1 1 150px; background:var(--ink); border:1px solid var(--border);
           border-radius:9px; padding:14px 16px; }
  .lhnum{ font-family:'IBM Plex Mono',monospace; font-weight:600; font-size:26px; line-height:1.1; }
  .lhlabel{ font-size:12px; color:var(--grey); margin-top:4px; }
  .g-good{ color:#5FD39B; } .g-fair{ color:#F09150; } .g-poor{ color:#F08080; } .g-unknown{ color:var(--grey); }
  .vitals{ display:flex; gap:26px; flex-wrap:wrap; margin-top:16px;
           font-family:'IBM Plex Mono',monospace; font-size:13px; color:var(--grey); }
  .srctag{ font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:var(--grey-dim);
           border:1px solid var(--border-strong); padding:2px 6px; border-radius:4px; margin-left:8px; }
  .secthead{ font-size:12px; letter-spacing:.1em; text-transform:uppercase; color:var(--grey);
             margin:0 0 4px; }
  .good{ background:rgba(60,180,110,.14); color:#5FD39B; }
  .fair{ background:rgba(225,105,31,.16); color:#F09150; }
  .poor{ background:rgba(220,70,70,.16); color:#F08080; }
  .unknown{ background:rgba(238,241,240,.07); color:var(--grey); }
  .find{ padding:16px 0; border-bottom:1px solid var(--border); }
  .find:last-child{ border-bottom:0; }
  .tag{ font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase;
        padding:3px 8px; border-radius:4px; margin-right:10px; }
  .t-fail{ background:rgba(220,70,70,.16); color:#F08080; }
  .t-warn{ background:rgba(225,105,31,.16); color:#F09150; }
  .t-pass{ background:rgba(60,180,110,.14); color:#5FD39B; }
  .chiprow{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
  .chiprow:last-child{ margin-bottom:0; }
  .chiplabel{ font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--grey-dim);
              width:78px; flex-shrink:0; }
  .chip{ font-size:12.5px; padding:5px 10px; border-radius:14px; text-decoration:none;
         border:1px solid transparent; white-space:nowrap; }
  .chip.c-fail{ background:rgba(220,70,70,.14); color:#F08080; border-color:rgba(220,70,70,.3); }
  .chip.c-warn{ background:rgba(225,105,31,.14); color:#F09150; border-color:rgba(225,105,31,.3); }
  .chip.c-pass{ background:rgba(60,180,110,.10); color:#5FD39B; border-color:rgba(60,180,110,.2); }
  .chip:hover{ filter:brightness(1.15); }
  .ftitle{ font-weight:600; }
  .fdetail{ color:var(--grey); font-size:14px; margin-top:5px; }
  .fvalue{ font-family:'IBM Plex Mono',monospace; font-size:12.5px; color:var(--grey-dim);
           margin-top:7px; word-break:break-word; }
  .stats{ display:flex; gap:28px; flex-wrap:wrap; margin-top:6px; font-size:14px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:14px; }
  .notice{ background:rgba(225,105,31,.10); border:1px solid rgba(225,105,31,.28);
           padding:13px 16px; border-radius:8px; margin-top:20px; font-size:14px; }
  .flash{ background:rgba(60,180,110,.12); border:1px solid rgba(60,180,110,.3);
    padding:11px 15px; border-radius:7px; margin-top:20px; font-size:14px; }
  .waiting{ display:flex; align-items:center; gap:13px; }
  .spinner{ width:16px; height:16px; flex:0 0 16px; border-radius:50%;
    border:2px solid rgba(225,105,31,.25); border-top-color:var(--lime);
    animation:spin .9s linear infinite; }
  @keyframes spin{ to{ transform:rotate(360deg); } }
  /* Respect the OS setting - a permanently spinning element is a real
     problem for some people, and the text says everything the spinner
     does. */
  @media (prefers-reduced-motion: reduce){ .spinner{ animation:none; } }
  .article{ font-size:15px; line-height:1.7; white-space:pre-wrap; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $audit->site_id }}">← {{ $audit->site->name }}</a>

  <div class="row-top" style="margin-top:12px">
    <div>
      <h1>Audit</h1>
      <div class="url">{{ $audit->url }}</div>
      <div class="stats muted">
        <span>{{ $audit->created_at->format('j M Y, H:i') }}</span>
        <span>{{ ucfirst($audit->status) }}</span>
        @if ($audit->http_status)<span>HTTP {{ $audit->http_status }}</span>@endif
        @if ($audit->response_ms)<span>{{ $audit->response_ms }} ms</span>@endif
      </div>
    </div>
    {{-- The bare score out of 100 used to live here and told a reader
         nothing: it was our own invented weighting presented as a
         precise figure, and it moved between runs when response time
         crossed a threshold. Counts of what needs doing are honest and
         immediately actionable. --}}
    @if ($audit->status === 'completed')
      @php($counts = $audit->issueCounts())
      <div class="counts">
        <span class="count poor">{{ $counts['fail'] }} to fix</span>
        <span class="count fair">{{ $counts['warn'] }} to review</span>
        <span class="count good">{{ $counts['pass'] }} passing</span>
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
    <div class="panel">
      <p class="secthead">AI recommendations</p>

      @if ($audit->recommendations)
        <div class="article">{{ $audit->recommendations }}</div>
      @elseif ($audit->isRecommendationsPending())
        <div class="waiting">
          <span class="spinner" aria-hidden="true"></span>
          <span class="muted">Writing the recommendations — this updates itself, no need to refresh.</span>
        </div>
      @else
        @if ($audit->recommendations_error)
          <div class="notice" style="margin-top:0">{{ $audit->recommendations_error }}</div>
        @endif
        <form method="POST" action="/audits/{{ $audit->id }}/recommendations" style="margin-top:14px">
          @csrf
          <button class="primary" type="submit"
            style="background:var(--lime); color:#fff; border:0; padding:11px 20px; border-radius:7px; font:inherit; font-weight:600; cursor:pointer">
            Get AI recommendations
          </button>
        </form>
      @endif
    </div>
  @endif

  {{-- Google's own numbers, labelled as Google's. For a client report
       "Google scores your performance 86" carries weight that our own
       figure never could - which is exactly why these are shown
       separately from our findings rather than blended into one score. --}}
  @if ($audit->hasLighthouse())
    <div class="panel">
      <p class="secthead">Google Lighthouse</p>
      <div class="lh">
        @foreach ([
          'Performance' => $audit->lh_performance,
          'SEO' => $audit->lh_seo,
          'Accessibility' => $audit->lh_accessibility,
          'Best practices' => $audit->lh_best_practices,
        ] as $label => $value)
          <div class="lhcard">
            <div class="lhnum g-{{ \App\Models\Audit::lighthouseBand($value) }}">{{ $value !== null ? $value : '—' }}</div>
            <div class="lhlabel">{{ $label }}</div>
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
    <div class="panel">
      <p class="secthead">All checks</p>
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
    <div class="panel">
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
    <div class="panel muted">No findings were recorded for this audit.</div>
  @endif
</div>
@endsection
