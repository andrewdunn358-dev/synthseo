@extends('layouts.app')
@section('title', ($newsletter->subject ?? $newsletter->topic) . ' — ' . $newsletter->site->name)

@if ($newsletter->isPending())
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:640px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }
  .stats{ display:flex; gap:20px; flex-wrap:wrap; margin-top:var(--sp-3); font-size:var(--fs-sm); }

  .body-text{ font-size:var(--fs-base); line-height:1.7; white-space:pre-wrap; }
  .send-row{ margin-top:var(--sp-4); display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .sent-badge{ display:inline-flex; align-items:center; gap:6px; font-size:var(--fs-sm); color:var(--good); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $newsletter->site_id }}">← {{ $newsletter->site->name }}</a>

  <div style="margin-top:12px">
    <h1>{{ $newsletter->subject ?? $newsletter->topic }}</h1>
    <div class="stats muted">
      <span>{{ $newsletter->created_at->format('j M Y, H:i') }}</span>
      <span>{{ ucfirst($newsletter->status) }}</span>
    </div>
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  @if ($newsletter->isPending())
    <div class="notice waiting">
      <span class="spinner" aria-hidden="true"></span>
      <span class="muted">Writing the newsletter — this updates itself, no need to refresh.</span>
    </div>
  @endif

  @if ($newsletter->error)
    <div class="notice">{{ $newsletter->error }}</div>
  @endif

  @if ($newsletter->body)
    <div class="card">
      <div class="body-text">{{ $newsletter->body }}</div>
    </div>

    <div class="send-row">
      @if ($newsletter->isSent())
        <span class="sent-badge">✓ Sent {{ $newsletter->sent_at->diffForHumans() }}</span>
      @else
        <form method="POST" action="/newsletters/{{ $newsletter->id }}/send" onsubmit="return confirm('Send this to every subscriber on {{ $newsletter->site->name }}\'s list? This cannot be undone.')">
          @csrf
          <button class="btn btn-primary" type="submit">Send to subscribers</button>
        </form>
      @endif
      <button class="btn" type="button" onclick="navigator.clipboard.writeText(document.querySelector('.body-text').innerText)">
        Copy text
      </button>
    </div>
  @endif
</div>
@endsection
