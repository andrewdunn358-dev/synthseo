@extends('layouts.app')
@section('title', 'Audit — ' . $audit->site->name)

@section('styles')
  .wrap-pad{ padding:40px 32px 80px; }
  .row-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .muted{ color:var(--grey); }
  .url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:13px; word-break:break-all; }
  .panel{ background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:22px; margin-top:22px; }
  .bigscore{ font-family:'IBM Plex Mono',monospace; font-weight:600; font-size:40px;
             padding:14px 24px; border-radius:10px; line-height:1; }
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
    <div class="bigscore {{ $audit->scoreBand() }}">{{ $audit->score !== null ? $audit->score : '—' }}</div>
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  {{-- Queued and running are shown honestly rather than as an empty
       results page. On shared hosting the queue is cron-driven, so
       there is a real gap between asking for an audit and getting one. --}}
  @if (in_array($audit->status, ['queued', 'running']))
    <div class="notice">
      This audit is {{ $audit->status }}. Audits run from a scheduled task, so it may take up to a minute to start.
      Refresh the page to check.
    </div>
  @endif

  @if ($audit->error)
    <div class="notice">{{ $audit->error }}</div>
  @endif

  @if ($findings->isNotEmpty())
    <div class="panel">
      @foreach ($findings as $finding)
        <div class="find">
          <span class="tag t-{{ $finding->status }}">{{ $finding->status }}</span>
          <span class="ftitle">{{ $finding->title }}</span>
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
