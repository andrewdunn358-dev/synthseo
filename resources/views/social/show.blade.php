@extends('layouts.app')
@section('title', ucfirst($post->platform) . ' post — ' . $post->site->name)

@if ($post->isPending())
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:640px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }
  .stats{ display:flex; gap:20px; flex-wrap:wrap; margin-top:var(--sp-3); font-size:var(--fs-sm); }
  .platform-badge{ text-transform:capitalize; }

  .post-image{ width:100%; border-radius:var(--radius); display:block; margin-top:var(--sp-5); }
  .caption{ font-size:var(--fs-base); line-height:1.7; white-space:pre-wrap; }
  .copy-btn{ margin-top:var(--sp-3); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $post->site_id }}">← {{ $post->site->name }}</a>

  <div style="margin-top:12px">
    <h1 class="platform-badge">{{ $post->platform }} post</h1>
    <div class="stats muted">
      <span>{{ $post->topic }}</span>
      <span>{{ $post->created_at->format('j M Y, H:i') }}</span>
      <span>{{ ucfirst($post->status) }}</span>
    </div>
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  @if ($post->isPending())
    <div class="notice waiting">
      <span class="spinner" aria-hidden="true"></span>
      <span class="muted">Writing the caption and generating an image — this updates itself, no need to refresh.</span>
    </div>
  @endif

  {{-- Caption and image are independent outcomes - see the migration's
       doc comment. Each shown or explained on its own rather than one
       combined pass/fail, so a working caption is never hidden behind
       an image that happened to fail, or the reverse. --}}
  @if ($post->image_path)
    <img class="post-image" src="{{ $post->imageUrl() }}" alt="Generated image for: {{ $post->topic }}">
  @elseif ($post->image_error)
    <div class="notice">Image: {{ $post->image_error }}</div>
  @endif

  @if ($post->caption)
    <div class="card">
      <div class="caption">{{ $post->caption }}</div>
    </div>
    {{-- v1 is recommendation-only by design, same as content drafts -
         a copy button, nothing that posts anywhere on its own. --}}
    <button class="btn copy-btn" type="button" onclick="navigator.clipboard.writeText(document.querySelector('.caption').innerText)">
      Copy caption text
    </button>
  @elseif ($post->caption_error)
    <div class="notice">Caption: {{ $post->caption_error }}</div>
  @endif
</div>
@endsection
