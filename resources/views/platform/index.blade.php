@extends('layouts.app')
@section('title', 'Platform — SynthSEO')

@section('styles')
  .wrap-pad{ padding:var(--sp-7) 32px 80px; }

  .account-block{ margin-top:var(--sp-6); padding-top:var(--sp-5); border-top:1px solid var(--border); }
  .account-block:first-child{ margin-top:var(--sp-5); padding-top:0; border-top:0; }
  .account-head{ display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  .account-name{ font-weight:650; font-size:var(--fs-md); }
  .account-meta{ font-size:var(--fs-xs); color:var(--grey-dim); margin-top:2px; }

  table.users{ width:100%; border-collapse:collapse; margin-top:var(--sp-3); }
  table.users td{ padding:10px 2px; border-bottom:1px solid var(--border); vertical-align:middle; font-size:var(--fs-sm); }
  table.users tr:last-child td{ border-bottom:0; }
  table.users input[type=text],table.users input[type=email]{ background:var(--ink); border:1px solid var(--border-strong);
    color:var(--paper); padding:6px 9px; border-radius:var(--radius-sm); font:inherit; font-size:var(--fs-sm); width:100%; }
  table.users select{ background:var(--ink); border:1px solid var(--border-strong); color:var(--paper);
    padding:6px 9px; border-radius:var(--radius-sm); font:inherit; font-size:var(--fs-sm); }
  .user-row-form{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .mini-btn{ font-size:var(--fs-2xs); padding:5px 10px; }

  .log{ margin-top:var(--sp-3); }
  .log-line{ padding:9px 0; border-bottom:1px solid var(--border); font-size:var(--fs-sm); }
  .log-line:last-child{ border-bottom:0; }
  .log-meta{ font-size:var(--fs-2xs); color:var(--grey-dim); margin-top:2px; }
@endsection

@section('content')
<div class="wrap wrap-pad">
  <h1>Platform</h1>
  <p class="muted" style="margin-top:8px; font-size:var(--fs-base)">
    Every account on SynthSEO, staff-only. Everything on this page bypasses normal account isolation, and every
    edit or deletion here is logged below with who did it and when.
  </p>

  @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

  @foreach ($accounts as $account)
    <div class="account-block">
      <div class="account-head">
        <div>
          <div class="account-name">{{ $account->name }}</div>
          <div class="account-meta">{{ $account->sites_count }} {{ \Illuminate\Support\Str::plural('site', $account->sites_count) }} · Account #{{ $account->id }}</div>
        </div>
        <form method="POST" action="/platform/accounts/{{ $account->id }}"
          onsubmit="return confirm('Delete {{ $account->name }} entirely? This removes every site, audit, and user in this account. This cannot be undone.')">
          @csrf @method('DELETE')
          <button class="btn mini-btn" type="submit" style="color:var(--poor); border-color:var(--poor)">Delete account</button>
        </form>
      </div>

      <table class="users">
        @foreach ($account->users as $user)
          <tr>
            <td colspan="4">
              <form method="POST" action="/platform/users/{{ $user->id }}" class="user-row-form">
                @csrf
                <input type="text" name="name" value="{{ $user->name }}" style="max-width:160px">
                <input type="email" name="email" value="{{ $user->email }}" style="max-width:220px">
                <select name="role">
                  <option value="admin" @selected($user->role === 'admin')>Admin</option>
                  <option value="member" @selected($user->role === 'member')>Member</option>
                  @if ($user->role === 'staff')
                    <option value="staff" selected disabled>Staff (change via tinker only)</option>
                  @endif
                </select>
                <button class="btn mini-btn" type="submit">Save</button>
              </form>
            </td>
            <td style="width:1%">
              @if ($user->id !== auth()->id())
                <form method="POST" action="/platform/users/{{ $user->id }}"
                  onsubmit="return confirm('Delete {{ $user->name }}? This deletes their login entirely.')">
                  @csrf @method('DELETE')
                  <button class="linklike" type="submit" style="color:var(--poor); font-size:var(--fs-xs)">Delete</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </table>
    </div>
  @endforeach

  <div class="section">
    <p class="subhead">Recent admin activity</p>
    <div class="log">
      @forelse ($recentActions as $action)
        <div class="log-line">
          {{ $action->staff?->name ?? 'Unknown staff member' }} — {{ str_replace('_', ' ', $action->action) }}
          ({{ $action->target_type }} #{{ $action->target_id }})
          @if ($action->details)<div class="log-meta">{{ $action->details }}</div>@endif
          <div class="log-meta">{{ $action->created_at->format('j M Y, H:i') }}</div>
        </div>
      @empty
        <div class="muted">No admin actions recorded yet.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
