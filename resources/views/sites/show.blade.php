@extends('layouts.app')
@section('title', $site->name . ' — SynthSEO')

{{-- Same rule as the audit page: refresh only while something is
     actually pending, and stop the moment it isn't. Content pending
     counts too, so a generating draft also keeps this page live. --}}
@if ($audits->contains(fn ($a) => in_array($a->status, ['queued', 'running'])) || $content->contains(fn ($c) => $c->isPending()))
  @section('head')
    <meta http-equiv="refresh" content="5">
  @endsection
@endif

@section('styles')
  .wrap-pad{ padding:40px 32px 80px; }
  .row-top{ display:flex; align-items:baseline; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .muted{ color:var(--grey); }
  .url{ color:var(--grey-dim); font-family:'IBM Plex Mono',monospace; font-size:13px; word-break:break-all; }
  .panel{ background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:22px; margin-top:22px; }
  .audit{ display:flex; align-items:center; justify-content:space-between; gap:16px;
          padding:14px 0; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .audit:last-child{ border-bottom:0; }
  .score{ font-family:'IBM Plex Mono',monospace; font-weight:600; padding:4px 10px; border-radius:6px; font-size:14px; }
  .good{ background:rgba(60,180,110,.14); color:#5FD39B; }
  .fair{ background:rgba(225,105,31,.16); color:#F09150; }
  .poor{ background:rgba(220,70,70,.16); color:#F08080; }
  .unknown{ background:rgba(238,241,240,.07); color:var(--grey); }
  button.primary{ background:var(--lime); color:#fff; border:0; padding:11px 20px; border-radius:7px;
    font:inherit; font-weight:600; cursor:pointer; }
  button.primary:hover{ background:var(--lime-dim); }
  a.back{ color:var(--grey); text-decoration:none; font-size:14px; }
  .flash{ background:rgba(60,180,110,.12); border:1px solid rgba(60,180,110,.3);
    padding:11px 15px; border-radius:7px; margin-top:20px; font-size:14px; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites">← All sites</a>
  <div class="row-top" style="margin-top:12px">
    <div>
      <h1>{{ $site->name }}</h1>
      <div class="url">{{ $site->url }}</div>
    </div>
    <form method="POST" action="/sites/{{ $site->id }}/audits">
      @csrf<button class="primary" type="submit">Run audit</button>
    </form>
  </div>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  <div class="panel">
    <strong>Audit history</strong>
    @forelse ($audits as $audit)
      <div class="audit">
        <div>
          <a href="/audits/{{ $audit->id }}" style="text-decoration:none;font-weight:600">
            {{ $audit->created_at->format('j M Y, H:i') }}
          </a>
          <div class="muted" style="font-size:13px">{{ ucfirst($audit->status) }}</div>
        </div>
        @if ($audit->status === 'completed')
          @php($c = $audit->issueCounts())
          <span class="score {{ $c['fail'] ? 'poor' : ($c['warn'] ? 'fair' : 'good') }}">
            {{ $c['fail'] }} to fix · {{ $c['warn'] }} to review
          </span>
        @else
          <span class="score unknown">—</span>
        @endif
      </div>
    @empty
      <div class="muted" style="margin-top:10px">No audits yet. Run the first one above.</div>
    @endforelse
    <div style="margin-top:16px">{{ $audits->links() }}</div>
  </div>

  <div class="panel">
    <strong>Content drafts</strong>
    <form method="POST" action="/sites/{{ $site->id }}/content" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
      @csrf
      <input type="text" name="topic" placeholder="Topic, e.g. &quot;why regular servicing matters&quot;"
        required maxlength="255"
        style="flex:1;min-width:240px;background:var(--ink);border:1px solid var(--border-strong);
               border-radius:7px;padding:11px 13px;color:var(--paper);font:inherit">
      <button class="primary" type="submit">Generate draft</button>
    </form>

    @forelse ($content as $piece)
      <div class="audit">
        <div>
          <a href="/content/{{ $piece->id }}" style="text-decoration:none;font-weight:600">
            {{ $piece->title ?? $piece->topic }}
          </a>
          <div class="muted" style="font-size:13px">{{ $piece->created_at->format('j M Y, H:i') }} · {{ ucfirst($piece->status) }}</div>
        </div>
        @if ($piece->status === 'completed')
          <span class="score good">{{ $piece->word_count }} words</span>
        @elseif ($piece->status === 'failed')
          <span class="score poor">Failed</span>
        @else
          <span class="score unknown">—</span>
        @endif
      </div>
    @empty
      <div class="muted" style="margin-top:10px">No drafts yet. Enter a topic above to generate the first one.</div>
    @endforelse
  </div>
</div>
@endsection
