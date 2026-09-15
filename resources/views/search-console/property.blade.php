@extends('layouts.app')
@section('title', 'Choose a Search Console property — ' . $site->name)

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:640px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }
  .prop{ display:flex; align-items:center; justify-content:space-between; gap:16px;
         padding:16px 2px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .prop:last-child{ border-bottom:0; }
  .prop-name{ font-family:'IBM Plex Mono',monospace; font-size:var(--fs-sm); word-break:break-all; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $site->id }}">← {{ $site->name }}</a>
  <h1 style="margin-top:12px">Choose a property</h1>
  <p class="muted" style="margin-top:8px; font-size:var(--fs-base)">
    These are the Search Console properties that Google login can read. Pick the one for {{ $site->url }}.
  </p>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
  @if ($error)<div class="notice">{{ $error }}</div>@endif

  @forelse ($properties as $property)
    <div class="prop">
      <span class="prop-name">{{ $property }}</span>
      <form method="POST" action="/sites/{{ $site->id }}/search-console/property">
        @csrf
        <input type="hidden" name="property" value="{{ $property }}">
        <button class="btn btn-primary" type="submit">Use this</button>
      </form>
    </div>
  @empty
    @if (! $error)
      <div class="muted" style="margin-top:var(--sp-5)">
        That Google login can't see any Search Console properties. Make sure the site is verified in Search Console
        and that this login has at least full access to it.
      </div>
    @endif
  @endforelse
</div>
@endsection
