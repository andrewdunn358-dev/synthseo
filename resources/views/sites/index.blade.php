@extends('layouts.app')
@section('title', 'Sites — SynthSEO')

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; }

  .summary{ display:flex; gap:var(--sp-6); flex-wrap:wrap; margin-top:var(--sp-3); }
  .summary-num{ font-family:'Inter',sans-serif; font-weight:650; font-size:var(--fs-metric); line-height:1; }
  .summary-num.poor{ color:var(--poor); }
  .summary-label{ font-size:var(--fs-xs); color:var(--grey); margin-top:2px; }

  .addform{ display:flex; gap:10px; flex-wrap:wrap; margin-top:var(--sp-3); }
  input[type=text],input[type=url]{ background:var(--ink); border:1px solid var(--border-strong); color:var(--paper);
    padding:10px 12px; border-radius:var(--radius-sm); font:inherit; font-size:var(--fs-base); min-width:220px; flex:1; }
  .err{ color:var(--poor); font-size:var(--fs-sm); margin-top:8px; }

  /*
   | Application data as a proper table, not a stack of link-rows
   | inside a card - a list of client sites is exactly the "this is
   | data, not content" case the redesign review called out. Whole row
   | clickable via a small onclick rather than wrapping every cell in
   | an <a>, same lightweight-JS convention already used for the tab
   | switcher elsewhere in this app.
   */
  table.sites{ width:100%; border-collapse:collapse; margin-top:var(--sp-5); }
  table.sites th{ text-align:left; font-size:var(--fs-xs); font-weight:600; color:var(--grey-dim);
                   text-transform:uppercase; letter-spacing:.04em; padding:0 2px 10px; border-bottom:1px solid var(--border); }
  table.sites td{ padding:16px 2px; border-bottom:1px solid var(--border); vertical-align:middle; }
  table.sites tr:last-child td{ border-bottom:0; }
  table.sites tbody tr{ cursor:pointer; transition:background .14s ease; }
  table.sites tbody tr:hover{ background:var(--panel); }
  .site-name{ font-weight:600; font-size:var(--fs-md); }
  .site-url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:var(--fs-2xs); word-break:break-all; margin-top:2px; }
  .site-issues{ font-weight:600; white-space:nowrap; }
  .site-issues.poor{ color:var(--poor); }
  .site-issues.good{ color:var(--good); }
  .site-issues .arrow{ color:var(--grey-dim); margin-left:4px; }
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
      <div>
        <div class="summary-num">{{ $sites->count() }}</div>
        <div class="summary-label">{{ \Illuminate\Support\Str::plural('site', $sites->count()) }} tracked</div>
      </div>
      <div>
        <div class="summary-num {{ $needsAttention > 0 ? 'poor' : '' }}">{{ $needsAttention }}</div>
        <div class="summary-label">need attention</div>
      </div>
    </div>
  @endif

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  <div class="section">
    <div class="section-head"><p class="subhead">Add a site</p></div>
    <form method="POST" action="/sites" class="addform">
      @csrf
      <input type="text" name="name" placeholder="Client or site name" value="{{ old('name') }}" required>
      <input type="url" name="url" placeholder="https://example.co.uk" value="{{ old('url') }}" required>
      <button class="btn btn-primary" type="submit">Add site</button>
    </form>
    @error('name')<div class="err">{{ $message }}</div>@enderror
    @error('url')<div class="err">{{ $message }}</div>@enderror
  </div>

  @if ($sites->isNotEmpty())
    <table class="sites">
      <thead>
        <tr><th>Site</th><th>Platform</th><th>Last audit</th><th>Issues</th></tr>
      </thead>
      <tbody>
        @foreach ($sites as $site)
          <tr onclick="location.href='/sites/{{ $site->id }}'">
            <td>
              <div class="site-name">{{ $site->name }}</div>
              <div class="site-url">{{ $site->url }}</div>
            </td>
            <td class="muted">{{ $site->cms ?? '—' }}</td>
            <td class="muted">{{ $site->latestAudit?->created_at->format('j M Y') ?? '—' }}</td>
            <td>
              @if ($site->latestAudit && $site->latestAudit->status === 'completed')
                @php
                  $c = $site->latestAudit->issueCounts();
                @endphp
                <span class="site-issues {{ $c['fail'] ? 'poor' : 'good' }}">
                  {{ $c['fail'] }} to fix<span class="arrow">→</span>
                </span>
              @elseif ($site->latestAudit)
                <span class="muted">{{ ucfirst($site->latestAudit->status) }}</span>
              @else
                <span class="muted">Not audited yet</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <div class="section muted">No sites yet. Add one above and run its first audit.</div>
  @endif
</div>
@endsection
