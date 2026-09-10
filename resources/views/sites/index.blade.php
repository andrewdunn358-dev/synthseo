@extends('layouts.app')
@section('title', 'Sites — SynthSEO')

@section('styles')
  .wrap-pad{ padding:40px 32px 80px; }
  .row-top{ display:flex; align-items:baseline; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .muted{ color:var(--grey); }
  .panel{ background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:22px; margin-top:22px; }
  .site{ display:flex; align-items:center; justify-content:space-between; gap:16px;
         padding:16px 0; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .site:last-child{ border-bottom:0; }
  .site a.name{ font-weight:600; text-decoration:none; font-size:17px; }
  .url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:13px; word-break:break-all; }
  .score{ font-family:'IBM Plex Mono',monospace; font-weight:600; padding:4px 10px; border-radius:6px; font-size:14px; }
  .good{ background:rgba(60,180,110,.14); color:#5FD39B; }
  .fair{ background:rgba(225,105,31,.16); color:#F09150; }
  .poor{ background:rgba(220,70,70,.16); color:#F08080; }
  .unknown{ background:rgba(238,241,240,.07); color:var(--grey); }
  input[type=text],input[type=url]{ background:var(--ink); border:1px solid var(--border-strong); color:var(--paper);
    padding:10px 12px; border-radius:7px; font:inherit; font-size:15px; min-width:220px; flex:1; }
  button.primary{ background:var(--lime); color:#fff; border:0; padding:11px 20px; border-radius:7px;
    font:inherit; font-weight:600; cursor:pointer; }
  button.primary:hover{ background:var(--lime-dim); }
  .addform{ display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
  .flash{ background:rgba(60,180,110,.12); border:1px solid rgba(60,180,110,.3);
    padding:11px 15px; border-radius:7px; margin-top:20px; font-size:14px; }
  .err{ color:#F08080; font-size:13px; margin-top:8px; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <div class="row-top">
    <h1>Sites</h1>
    <form method="POST" action="/logout">@csrf<button class="primary" style="background:transparent;border:1px solid var(--border-strong)">Log out</button></form>
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  <div class="panel">
    <strong>Add a site</strong>
    <form method="POST" action="/sites" class="addform">
      @csrf
      <input type="text" name="name" placeholder="Client or site name" value="{{ old('name') }}" required>
      <input type="url" name="url" placeholder="https://example.co.uk" value="{{ old('url') }}" required>
      <button class="primary" type="submit">Add site</button>
    </form>
    @error('name')<div class="err">{{ $message }}</div>@enderror
    @error('url')<div class="err">{{ $message }}</div>@enderror
  </div>

  <div class="panel">
    @forelse ($sites as $site)
      <div class="site">
        <div>
          <a class="name" href="/sites/{{ $site->id }}">{{ $site->name }}</a>
          <div class="url">{{ $site->url }}</div>
        </div>
        @if ($site->latestAudit && $site->latestAudit->status === 'completed')
          @php
            $c = $site->latestAudit->issueCounts();
          @endphp
          <span class="score {{ $c['fail'] ? 'poor' : ($c['warn'] ? 'fair' : 'good') }}">
            {{ $c['fail'] }} to fix · {{ $c['warn'] }} to review
          </span>
        @elseif ($site->latestAudit)
          <span class="score unknown">{{ ucfirst($site->latestAudit->status) }}</span>
        @else
          <span class="score unknown">not audited</span>
        @endif
      </div>
    @empty
      <div class="muted">No sites yet. Add one above and run its first audit.</div>
    @endforelse
  </div>
</div>
@endsection
