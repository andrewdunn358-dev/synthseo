@extends('layouts.app')
@section('title', $comparison->site->name . ' vs ' . $comparison->competitor_domain . ' — SynthSEO')

@if ($comparison->isPending())
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:760px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }

  .vs-row{ display:flex; align-items:center; gap:var(--sp-4); margin-top:var(--sp-3); flex-wrap:wrap; }
  .vs-domain{ font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:var(--fs-lg); }
  .vs-sep{ color:var(--grey-dim); font-size:var(--fs-base); }

  .metrics{ display:grid; grid-template-columns:1fr 1fr; gap:var(--sp-4); margin-top:var(--sp-2); }
  .metric-card{ text-align:center; padding:var(--sp-5) var(--sp-3); }
  .metric-card.leader{ border-color:var(--brand); }
  .metric-num{ font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:32px; }
  .metric-label{ font-size:var(--fs-sm); color:var(--grey); margin-top:4px; }
  .metric-sub{ font-size:var(--fs-xs); color:var(--grey-dim); margin-top:10px; }
  .lead-tag{ display:inline-block; background:rgba(225,105,31,.14); color:var(--brand); font-size:var(--fs-2xs);
             font-weight:700; padding:3px 9px; border-radius:12px; margin-top:8px; }
  .metric-explain{ font-size:var(--fs-base); color:var(--grey); margin-top:14px; padding-top:14px;
                    border-top:1px solid var(--border); text-align:left; line-height:1.65; }
  .verdict{ font-size:var(--fs-md); font-weight:600; font-family:'Space Grotesk',sans-serif; margin:0 0 8px; }
  .verdict.good{ color:var(--good); }
  .verdict.fair{ color:var(--fair); }
  .finding-line{ padding:7px 0; font-size:var(--fs-sm); }
  .tag{ font-size:11px; font-weight:600; padding:3px 8px; border-radius:4px; margin-right:10px; }
  .t-fail{ background:rgba(240,100,90,.16); color:var(--poor); }
  .t-warn{ background:rgba(240,166,62,.16); color:var(--fair); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $comparison->site_id }}">← {{ $comparison->site->name }}</a>

  <div class="vs-row">
    <span class="vs-domain">{{ parse_url($comparison->site->url, PHP_URL_HOST) ?? $comparison->site->url }}</span>
    <span class="vs-sep">vs</span>
    <span class="vs-domain muted">{{ $comparison->competitor_domain }}</span>
  </div>
  <div class="muted" style="margin-top:6px; font-size:var(--fs-sm)">{{ $comparison->created_at->format('j M Y, H:i') }} &middot; {{ ucfirst($comparison->status) }}</div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  @if ($comparison->isPending())
    <div class="notice waiting">
      <span class="spinner" aria-hidden="true"></span>
      <span class="muted">Fetching search data for both domains — this updates itself, no need to refresh.</span>
    </div>
  @endif

  @if ($comparison->error)
    <div class="notice">{{ $comparison->error }}</div>
  @endif

  @if ($comparison->status === 'completed')
    @php
      $leader = $comparison->leader();
    @endphp

    <div class="metrics">
      <div class="card metric-card @if($leader === 'us') leader @endif">
        <div class="metric-num">{{ $comparison->our_traffic !== null ? number_format($comparison->our_traffic) : '—' }}</div>
        <div class="metric-label">Estimated monthly organic visits</div>
        <div class="metric-sub">{{ $comparison->our_keywords !== null ? number_format($comparison->our_keywords) . ' ranking keywords' : '' }}</div>
        @if ($leader === 'us')<div class="lead-tag">Ahead</div>@endif
      </div>
      <div class="card metric-card @if($leader === 'them') leader @endif">
        <div class="metric-num">{{ $comparison->competitor_traffic !== null ? number_format($comparison->competitor_traffic) : '—' }}</div>
        <div class="metric-label">Estimated monthly organic visits</div>
        <div class="metric-sub">{{ $comparison->competitor_keywords !== null ? number_format($comparison->competitor_keywords) . ' ranking keywords' : '' }}</div>
        @if ($leader === 'them')<div class="lead-tag">Ahead</div>@endif
      </div>
    </div>

    <div class="card" style="margin-top:var(--sp-5)">
      @if ($leader === 'us')
        <p class="verdict good">{{ $comparison->site->name }} is ahead right now</p>
        <p class="muted" style="margin:0; font-size:var(--fs-base); line-height:1.65">
          More estimated visits and more ranking keywords means Google is sending this site more free traffic, and
          for a wider range of searches, than {{ $comparison->competitor_domain }}. Worth protecting that lead -
          publishing content regularly (see the drafts panel above) is the main way to keep it.
        </p>
      @elseif ($leader === 'them')
        <p class="verdict fair">{{ $comparison->competitor_domain }} is ahead right now</p>
        <p class="muted" style="margin:0; font-size:var(--fs-base); line-height:1.65">
          {{ $comparison->competitor_domain }} is estimated to get more free traffic from Google, and shows up for
          more different searches, than {{ $comparison->site->name }} does. That usually comes down to having more
          content published, or content that matches more of what customers actually search for - the audit and
          content drafts above are the two levers for closing that gap.
        </p>
      @else
        <p class="muted" style="margin:0; font-size:var(--fs-base); line-height:1.65">
          Not enough data was returned to say which site is ahead.
        </p>
      @endif

      <div class="metric-explain">
        <strong>Estimated monthly organic visits</strong> — roughly how many people per month are likely to land on
        the site by clicking an unpaid Google result, based on the search terms it ranks for and how popular each
        one is. It is not a reading from either site's real analytics, since neither site has shared that.<br><br>
        <strong>Ranking keywords</strong> — the number of different search terms Google shows this site for anywhere
        in its results, not just page one. A higher number means the site is visible for a broader range of what
        customers actually search for.<br><br>
        Both figures come from DataForSEO's own search index, updated weekly - not a live crawl of either site, so a
        change made today will not show here until the index refreshes.
      </div>
    </div>

    {{-- The two features lived side by side without connecting - a
         traffic gap with no link to the audit that might explain it
         left "now what?" unanswered. This is the cheap half of that
         fix: the other half is the competitor context now folded
         into the AI recommendations prompt itself, see
         GenerateAuditRecommendations. --}}
    @if ($comparison->site->latestAudit)
      @php
        $latestAudit = $comparison->site->latestAudit;
        $auditCounts = $latestAudit->status === 'completed' ? $latestAudit->issueCounts() : null;
      @endphp
      <div class="card">
        <p class="subhead">{{ $comparison->site->name }}'s own audit</p>
        @if ($auditCounts)
          <p class="muted" style="margin:0 0 12px; font-size:var(--fs-base)">
            Last checked {{ $latestAudit->created_at->diffForHumans() }} —
            @if ($auditCounts['fail'] > 0)
              <strong style="color:var(--poor)">{{ $auditCounts['fail'] }} issue{{ $auditCounts['fail'] === 1 ? '' : 's' }}</strong> still need fixing.
            @else
              nothing currently failing.
            @endif
          </p>
        @else
          <p class="muted" style="margin:0 0 12px; font-size:var(--fs-base)">The most recent audit is still {{ $latestAudit->status }}.</p>
        @endif

        @if ($topFindings->isNotEmpty())
          <div style="margin-bottom:16px">
            @foreach ($topFindings as $finding)
              <div class="finding-line">
                <span class="tag t-{{ $finding->status }}">{{ $finding->status }}</span>
                <span>{{ $finding->title }}</span>
              </div>
            @endforeach
          </div>
        @endif

        <a class="btn" href="/audits/{{ $latestAudit->id }}">View audit</a>
      </div>
    @endif
  @endif
</div>
@endsection
