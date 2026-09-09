@extends('layouts.app')
@section('title', ($piece->title ?? $piece->topic) . ' — ' . $piece->site->name)

{{-- Same rule as the audit page: refresh only while generation is
     actually pending, and stop the moment it isn't. --}}
@if ($piece->isPending())
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:40px 32px 80px; max-width:760px; }
  .row-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .muted{ color:var(--grey); }
  .panel{ background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:22px; margin-top:22px; }
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
  @media (prefers-reduced-motion: reduce){ .spinner{ animation:none; } }
  .article{ font-size:16px; line-height:1.7; white-space:pre-wrap; }
  .article p{ margin:0 0 1.2em; }
  .copy-btn{ background:none; border:1px solid var(--border-strong); color:var(--paper);
    padding:9px 16px; border-radius:7px; font:inherit; font-size:13px; cursor:pointer; }
  .copy-btn:hover{ border-color:var(--lime); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $piece->site_id }}">← {{ $piece->site->name }}</a>

  <div class="row-top" style="margin-top:12px">
    <div>
      <h1>{{ $piece->title ?? $piece->topic }}</h1>
      <div class="stats muted">
        <span>{{ $piece->created_at->format('j M Y, H:i') }}</span>
        <span>{{ ucfirst($piece->status) }}</span>
        @if ($piece->word_count)<span>{{ $piece->word_count }} words</span>@endif
        @if ($piece->model)<span>{{ $piece->model }}</span>@endif
      </div>
    </div>
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  {{-- Same honesty as the audit page: the queue is cron-driven, so
       there is a real gap between asking for a draft and getting one. --}}
  @if ($piece->isPending())
    <div class="notice waiting">
      <span class="spinner" aria-hidden="true"></span>
      <span>
        <strong>{{ $piece->status === 'queued' ? 'Waiting to start' : 'Writing the draft' }}</strong>
        <span class="muted">
          — this page updates itself, no need to refresh.
          @if ($piece->status === 'queued')
            Generation starts on the next scheduled run, usually within a minute.
          @endif
        </span>
      </span>
    </div>
  @endif

  @if ($piece->error)
    <div class="notice">{{ $piece->error }}</div>
  @endif

  @if ($piece->body)
    <div class="panel">
      <div class="article">{{ $piece->body }}</div>
    </div>

    {{-- v1 is recommendation-only by design - see the content_pieces
         migration. This is where that shows up: a copy button and
         nothing that publishes anywhere on its own. --}}
    <div style="margin-top:18px">
      <button class="copy-btn" type="button" onclick="navigator.clipboard.writeText(document.querySelector('.article').innerText)">
        Copy article text
      </button>
    </div>
  @endif
</div>
@endsection
