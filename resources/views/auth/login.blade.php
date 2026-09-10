@extends('layouts.app')

@section('title', 'Log in — SynthSEO')

@section('styles')
  .auth-wrap{ max-width:400px; margin:80px auto; padding:0 24px; }
  .auth-card h1{ font-size:26px; margin-bottom:24px; }
  label{ display:block; font-size:var(--fs-sm); color:var(--grey); margin-bottom:6px; }
  input{ width:100%; padding:11px 12px; margin-bottom:18px; background:var(--ink); border:1px solid var(--border-strong);
         border-radius:var(--radius-sm); color:var(--paper); font-size:var(--fs-sm); }
  button{ width:100%; }
  .error{ background:rgba(240,100,90,.12); color:var(--poor); padding:10px 14px; border-radius:var(--radius-sm);
          font-size:13.5px; margin-bottom:18px; }
  .alt-link{ text-align:center; margin-top:18px; font-size:13.5px; color:var(--grey); }
  .alt-link a{ color:var(--brand); text-decoration:none; }
@endsection

@section('content')
<div class="auth-wrap">
  <div class="card auth-card" style="margin-top:0">
    <h1>Log in</h1>
    @if ($errors->any())
      <div class="error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/login">
      @csrf
      <label>Email</label>
      <input type="email" name="email" value="{{ old('email') }}" required autofocus>
      <label>Password</label>
      <input type="password" name="password" required>
      <button class="btn btn-primary" type="submit">Log in</button>
    </form>
    <div class="alt-link">No account yet? <a href="/register">Register</a></div>
  </div>
</div>
@endsection
