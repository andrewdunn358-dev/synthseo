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
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:760px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }
  .stats{ display:flex; gap:28px; flex-wrap:wrap; margin-top:var(--sp-3); font-size:var(--fs-sm); }

  .article{ font-size:var(--fs-base); line-height:1.7; white-space:pre-wrap; }
  .article p{ margin:0 0 1.2em; }
  .copy-btn{ margin-top:var(--sp-4); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $piece->site_id }}">← {{ $piece->site->name }}</a>

  <div style="margin-top:12px">
    <h1>{{ $piece->title ?? $piece->topic }}</h1>
    <div class="stats muted">
      <span>{{ $piece->created_at->format('j M Y, H:i') }}</span>
      <span>{{ ucfirst($piece->status) }}</span>
      @if ($piece->word_count)<span>{{ $piece->word_count }} words</span>@endif
      @if ($piece->model)<span>{{ $piece->model }}</span>@endif
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
    <div class="card">
      <div class="article">{{ $piece->body }}</div>
    </div>

    {{-- v1 is recommendation-only by design - see the content_pieces
         migration. This is where that shows up: a copy button and
         nothing that publishes anywhere on its own. --}}
    <button class="btn copy-btn" type="button" onclick="navigator.clipboard.writeText(document.querySelector('.article').innerText)">
      Copy article text
    </button>
  @endif
</div>
@endsection
