@extends('layouts.app')
@section('title', 'Sites — SynthSEO')

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; }

  .summary{ display:flex; gap:var(--sp-6); flex-wrap:wrap; margin-top:var(--sp-3); }
  .summary-item{ }
  .summary-num{ font-family:'Inter',sans-serif; font-weight:650; font-size:var(--fs-metric); line-height:1; }
  .summary-num.poor{ color:var(--poor); }
  .summary-label{ font-size:var(--fs-xs); color:var(--grey); margin-top:2px; }

  .addform{ display:flex; gap:10px; flex-wrap:wrap; margin-top:var(--sp-3); }
  input[type=text],input[type=url]{ background:var(--ink); border:1px solid var(--border-strong); color:var(--paper);
    padding:10px 12px; border-radius:var(--radius-sm); font:inherit; font-size:var(--fs-base); min-width:220px; flex:1; }
  .err{ color:var(--poor); font-size:var(--fs-sm); margin-top:8px; }

  .site-row{ display:flex; align-items:center; justify-content:space-between; gap:16px;
             padding:18px 0; border-bottom:1px solid var(--border); flex-wrap:wrap; text-decoration:none; color:inherit; }
  .site-row:last-child{ border-bottom:0; }
  .site-row .name{ font-weight:600; font-size:var(--fs-md); }
  .site-row .url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:var(--fs-xs); word-break:break-all; margin-top:2px; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <h1>Sites</h1>

  @if ($sites->isNotEmpty())
    @php
      $completed = $sites->filter(fn ($s) => $s->latestAudit?->status === 'completed');
      $needsAttention = $completed->filter(fn ($s) => $s->latestAudit->issueCounts()['fail'] > 0)->count();
    @endphp
    <div class="summary">
      <div class="summary-item">
        <div class="summary-num">{{ $sites->count() }}</div>
        <div class="summary-label">{{ \Illuminate\Support\Str::plural('site', $sites->count()) }} tracked</div>
      </div>
      <div class="summary-item">
        <div class="summary-num {{ $needsAttention > 0 ? 'poor' : '' }}">{{ $needsAttention }}</div>
        <div class="summary-label">need attention</div>
      </div>
    </div>
  @endif

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  <div class="card">
    <p class="subhead">Add a site</p>
    <form method="POST" action="/sites" class="addform">
      @csrf
      <input type="text" name="name" placeholder="Client or site name" value="{{ old('name') }}" required>
      <input type="url" name="url" placeholder="https://example.co.uk" value="{{ old('url') }}" required>
      <button class="btn btn-primary" type="submit">Add site</button>
    </form>
    @error('name')<div class="err">{{ $message }}</div>@enderror
    @error('url')<div class="err">{{ $message }}</div>@enderror
  </div>

  <div class="card">
    @forelse ($sites as $site)
      <a class="site-row" href="/sites/{{ $site->id }}">
        <div>
          <div class="name">{{ $site->name }}</div>
          <div class="url">{{ $site->url }}</div>
        </div>
        @if ($site->latestAudit && $site->latestAudit->status === 'completed')
          @php
            $c = $site->latestAudit->issueCounts();
          @endphp
          <span class="badge {{ $c['fail'] ? 'poor' : ($c['warn'] ? 'fair' : 'good') }}">
            {{ $c['fail'] }} to fix · {{ $c['warn'] }} to review
          </span>
        @elseif ($site->latestAudit)
          <span class="badge unknown">{{ ucfirst($site->latestAudit->status) }}</span>
        @else
          <span class="badge unknown">not audited</span>
        @endif
      </a>
    @empty
      <div class="muted">No sites yet. Add one above and run its first audit.</div>
    @endforelse
  </div>
</div>
@endsection
