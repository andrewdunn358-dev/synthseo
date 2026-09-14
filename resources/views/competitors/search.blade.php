@extends('layouts.app')
@section('title', 'Search results — ' . $site->name . ' — SynthSEO')

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:760px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }

  .result-row{ display:flex; align-items:center; justify-content:space-between; gap:16px;
               padding:16px 0; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .result-row:last-child{ border-bottom:0; }
  .result-rank{ font-family:'IBM Plex Mono',monospace; font-size:var(--fs-sm); color:var(--grey-dim);
                width:26px; flex-shrink:0; }
  .result-domain{ font-weight:600; font-size:var(--fs-md); }
  .result-title{ font-size:var(--fs-sm); color:var(--grey); margin-top:2px; }
  .result-main{ display:flex; gap:14px; align-items:flex-start; flex:1; min-width:0; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $site->id }}">← {{ $site->name }}</a>

  <div style="margin-top:12px">
    @if ($query)
      <h1>Who ranks for "{{ $query }}"?</h1>
      <p class="muted" style="margin-top:8px; font-size:var(--fs-base)">
        Live search results for that phrase right now, not a lookup based on {{ $site->name }}'s own ranking history -
        this works the same whether {{ $site->name }} has an established search presence or none at all.
      </p>
    @else
      <h1>Couldn't look up competitors</h1>
    @endif
  </div>

  @if ($error)
    <div class="notice">{{ $error }}</div>
  @elseif (empty($results))
    <div class="card muted">No organic results were returned for that search.</div>
  @else
    <div class="card">
      @foreach ($results as $result)
        <div class="result-row">
          <div class="result-main">
            <span class="result-rank">#{{ $result['rank'] }}</span>
            <div>
              <div class="result-domain">{{ $result['domain'] }}</div>
              <div class="result-title">{{ $result['title'] }}</div>
            </div>
          </div>
          <form method="POST" action="/sites/{{ $site->id }}/competitors">
            @csrf
            <input type="hidden" name="competitor_domain" value="{{ $result['domain'] }}">
            <button class="btn btn-primary" type="submit">Compare</button>
          </form>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
