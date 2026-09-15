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
  .prop-warn{ font-size:var(--fs-xs); color:var(--fair); margin-top:4px; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $site->id }}">← {{ $site->name }}</a>
  <h1 style="margin-top:12px">Choose a property</h1>
  <p class="muted" style="margin-top:8px; font-size:var(--fs-base)">
    These are the Search Console properties that Google account can read. Pick the one for {{ $site->url }}.
  </p>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
  @if ($error)<div class="notice">{{ $error }}</div>@endif

  @php
    $anyMatch = collect($properties)->contains('matches', true);
  @endphp

  {{-- The mistake worth preventing: connecting a site while only a
       different client's property is available, and picking it anyway
       because it's the only button on screen. --}}
  @if ($properties && ! $anyMatch)
    <div class="notice">
      None of these look like they belong to {{ $site->url }}. {{ $site->name }} probably hasn't been added to
      Search Console yet — that has to be done in Search Console itself, since Google only accepts domain
      verification through its own flow.
    </div>
  @endif

  @forelse ($properties as $property)
    <div class="prop">
      <div>
        <div class="prop-name">{{ $property['name'] }}</div>
        @if (! $property['matches'])
          <div class="prop-warn">Doesn't look like {{ $site->url }}</div>
        @endif
      </div>
      <form method="POST" action="/sites/{{ $site->id }}/search-console/property"
        @if (! $property['matches'])
          onsubmit="return confirm('{{ $property['name'] }} does not look like it belongs to {{ $site->url }}. Connecting it will show that other site\'s search data here. Continue anyway?')"
        @endif
      >
        @csrf
        <input type="hidden" name="property" value="{{ $property['name'] }}">
        @if (! $property['matches'])
          <input type="hidden" name="confirm_mismatch" value="1">
        @endif
        <button class="btn {{ $property['matches'] ? 'btn-primary' : '' }}" type="submit">
          {{ $property['matches'] ? 'Use this' : 'Use anyway' }}
        </button>
      </form>
    </div>
  @empty
    @if (! $error)
      <div class="muted" style="margin-top:var(--sp-5); line-height:1.7">
        That Google account can't see any Search Console properties. Two usual reasons:
        <br><br>
        <strong style="color:var(--paper)">The wrong Google account.</strong> Google often reuses whichever account
        you're already signed into without asking.
        <br><br>
        <strong style="color:var(--paper)">{{ $site->name }} isn't in Search Console yet.</strong> The site has to be
        added and verified there first — nothing here can read data that doesn't exist yet.
      </div>
    @endif
  @endforelse

  <div style="margin-top:var(--sp-6); display:flex; gap:12px; flex-wrap:wrap">
    <a class="btn" href="https://search.google.com/search-console" target="_blank" rel="noopener">
      Add {{ $site->name }} in Search Console
    </a>
    <a class="btn" href="/sites/{{ $site->id }}/search-console/connect">Try a different account</a>
    <form method="POST" action="/sites/{{ $site->id }}/search-console">
      @csrf @method('DELETE')
      <button class="btn" type="submit">Disconnect</button>
    </form>
    <a class="btn" href="/sites/{{ $site->id }}">Back to {{ $site->name }}</a>
  </div>
</div>
@endsection
