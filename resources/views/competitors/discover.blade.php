@extends('layouts.app')
@section('title', 'Find competitors — ' . $site->name . ' — SynthSEO')

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:760px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }

  .suggest-row{ display:flex; align-items:center; justify-content:space-between; gap:16px;
                padding:16px 0; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .suggest-row:last-child{ border-bottom:0; }
  .suggest-domain{ font-weight:600; font-size:var(--fs-md); }
  .suggest-meta{ font-size:var(--fs-sm); color:var(--grey); margin-top:3px; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $site->id }}">← {{ $site->name }}</a>

  <div style="margin-top:12px">
    <h1>Who's competing with {{ $site->name }}?</h1>
    <p class="muted" style="margin-top:8px; font-size:var(--fs-base)">
      Domains found ranking for the same organic search terms as {{ $site->url }}, sorted by how much overlap they
      have. Pick one to run a full comparison against.
    </p>
  </div>

  @if ($error)
    <div class="notice">{{ $error }}</div>
  @elseif (empty($suggestions))
    <div class="card muted">
      No competing domains were found. This usually means the site itself doesn't have enough organic search
      history yet for DataForSEO to find overlap - the same reason a brand-new site shows "no data available"
      on a direct comparison too.
    </div>
  @else
    <div class="card">
      @foreach ($suggestions as $suggestion)
        <div class="suggest-row">
          <div>
            <div class="suggest-domain">{{ $suggestion['domain'] }}</div>
            <div class="suggest-meta">
              {{ $suggestion['intersections'] !== null ? number_format($suggestion['intersections']) . ' shared keywords' : '' }}
              @if ($suggestion['traffic'] !== null)
                &middot; {{ number_format($suggestion['traffic']) }} estimated visits/mo
              @endif
              @if ($suggestion['keywords'] !== null)
                &middot; {{ number_format($suggestion['keywords']) }} keywords
              @endif
            </div>
          </div>
          <form method="POST" action="/sites/{{ $site->id }}/competitors">
            @csrf
            <input type="hidden" name="competitor_domain" value="{{ $suggestion['domain'] }}">
            <button class="btn btn-primary" type="submit">Compare</button>
          </form>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
