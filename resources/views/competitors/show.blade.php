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
      <p class="subhead">What this means</p>
      <p class="muted" style="margin:0; font-size:var(--fs-sm); line-height:1.6">
        Estimated traffic is based on ranking keywords and their search volume, not a direct analytics reading from either
        site — it is a fair like-for-like way to compare two competitors when neither has shared their real numbers.
        Data updates weekly.
      </p>
    </div>
  @endif
</div>
@endsection
