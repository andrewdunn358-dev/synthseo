@extends('layouts.app')
@section('title', $tracked->keyword . ' — ' . $tracked->site->name)

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; max-width:820px; }
  a.back{ color:var(--grey); text-decoration:none; font-size:var(--fs-sm); }
  a.back:hover{ color:var(--paper); }

  .headline{ display:flex; align-items:flex-end; gap:var(--sp-6); flex-wrap:wrap; margin-top:var(--sp-5); }
  .headline-num{ font-size:56px; font-weight:650; line-height:1; }
  .headline-num.good{ color:var(--good); }
  .headline-num.fair{ color:var(--fair); }
  .headline-num.poor{ color:var(--poor); }
  .headline-num.unknown{ color:var(--grey-dim); }
  .headline-meta{ font-size:var(--fs-sm); color:var(--grey); padding-bottom:6px; }

  table.history{ width:100%; border-collapse:collapse; margin-top:var(--sp-4); }
  table.history th{ text-align:left; font-size:var(--fs-xs); font-weight:600; color:var(--grey-dim);
                     text-transform:uppercase; letter-spacing:.04em; padding:0 2px 10px; border-bottom:1px solid var(--border); }
  table.history td{ padding:13px 2px; border-bottom:1px solid var(--border); font-size:var(--fs-sm); }
  table.history tr:last-child td{ border-bottom:0; }
  .pos{ font-weight:650; font-size:var(--fs-md); }
  .pos.good{ color:var(--good); }
  .pos.fair{ color:var(--fair); }
  .pos.poor{ color:var(--poor); }
  .pos.unknown{ color:var(--grey-dim); }
  .move.up{ color:var(--good); }
  .move.down{ color:var(--poor); }
  .move.flat{ color:var(--grey-dim); }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <a class="back" href="/sites/{{ $tracked->site_id }}">← {{ $tracked->site->name }}</a>

  <h1 style="margin-top:12px">{{ $tracked->keyword }}</h1>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  @php
    // Oldest-first for the table and the movement maths; only checks
    // that actually found a position count toward a trend, since a
    // failed check or a not-found result isn't a position that moved.
    $ranked = $tracked->rankings->whereNotNull('position')->values();
    $latest = $tracked->rankings->last();
    $band = fn ($p) => $p === null ? 'unknown' : ($p <= 10 ? 'good' : ($p <= 30 ? 'fair' : 'poor'));
    $best = $ranked->min('position');
  @endphp

  <div class="headline">
    <div>
      <div class="headline-num {{ $band($latest?->position) }}">{{ $latest?->position ?? '—' }}</div>
      <div class="headline-meta" style="padding-bottom:0; margin-top:6px">
        @if ($latest?->position)
          Currently page {{ (int) ceil($latest->position / 10) }} of Google
        @else
          Not found in the first 100 results
        @endif
      </div>
    </div>
    @if ($best)
      <div class="headline-meta">Best so far: position {{ $best }}</div>
    @endif
    <div class="headline-meta">{{ $tracked->rankings->count() }} {{ \Illuminate\Support\Str::plural('check', $tracked->rankings->count()) }} recorded</div>
  </div>

  <div style="margin-top:var(--sp-5); display:flex; gap:12px; flex-wrap:wrap">
    <form method="POST" action="/keywords/{{ $tracked->id }}/check">
      @csrf
      <button class="btn btn-primary" type="submit">Check now</button>
    </form>
    <form method="POST" action="/keywords/{{ $tracked->id }}"
      onsubmit="return confirm('Stop tracking &quot;{{ $tracked->keyword }}&quot;? This deletes its history too.')">
      @csrf @method('DELETE')
      <button class="btn" type="submit" style="color:var(--poor); border-color:var(--poor)">Stop tracking</button>
    </form>
  </div>

  <div class="section">
    <p class="subhead">Check history</p>
    <table class="history">
      <thead>
        <tr><th>Date</th><th>Position</th><th>Change</th><th>Page</th></tr>
      </thead>
      <tbody>
        {{-- Newest first here, unlike the maths above - reading a
             history, the most recent check is what you want at the
             top. --}}
        @foreach ($tracked->rankings->sortByDesc('checked_at') as $ranking)
          @php
            // Movement against the previous check in chronological
            // order, not the previous row in this reversed table.
            $index = $ranked->search(fn ($r) => $r->id === $ranking->id);
            $prior = ($index !== false && $index > 0) ? $ranked[$index - 1] : null;
            $change = ($prior && $ranking->position !== null) ? $prior->position - $ranking->position : null;
          @endphp
          <tr>
            <td class="muted">{{ $ranking->checked_at->format('j M Y, H:i') }}</td>
            <td>
              @if ($ranking->error)
                <span class="muted">Check failed</span>
              @else
                <span class="pos {{ $band($ranking->position) }}">{{ $ranking->position ?? 'Not found' }}</span>
              @endif
            </td>
            <td>
              @if ($change === null)
                <span class="move flat">—</span>
              @elseif ($change > 0)
                <span class="move up">▲ {{ $change }}</span>
              @elseif ($change < 0)
                <span class="move down">▼ {{ abs($change) }}</span>
              @else
                <span class="move flat">No change</span>
              @endif
            </td>
            <td class="muted">
              {{ $ranking->position ? 'Page ' . (int) ceil($ranking->position / 10) : '—' }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    @if ($tracked->rankings->isEmpty())
      <div class="muted" style="margin-top:10px">No checks recorded yet — the first one runs within a minute of adding a keyword.</div>
    @endif
  </div>
</div>
@endsection
