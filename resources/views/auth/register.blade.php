@extends('layouts.app')

@section('title', 'Register — SynthSEO')

@section('styles')
  .auth-wrap{ max-width:400px; margin:80px auto; padding:0 24px; }
  .auth-card h1{ font-size:var(--fs-xl); margin-bottom:24px; }
  label{ display:block; font-size:var(--fs-sm); color:var(--grey); margin-bottom:6px; }
  input{ width:100%; padding:11px 12px; margin-bottom:18px; background:var(--ink); border:1px solid var(--border-strong);
         border-radius:var(--radius-sm); color:var(--paper); font-size:var(--fs-sm); }
  button{ width:100%; }
  .error{ background:rgba(240,100,90,.12); color:var(--poor); padding:10px 14px; border-radius:var(--radius-sm);
          font-size:var(--fs-sm); margin-bottom:18px; }
  .alt-link{ text-align:center; margin-top:18px; font-size:var(--fs-sm); color:var(--grey); }
  .alt-link a{ color:var(--brand); text-decoration:none; }
@endsection

@section('content')
<div class="auth-wrap">
  <div class="card auth-card" style="margin-top:0">
    <h1>Create account</h1>
    @if ($errors->any())
      <div class="error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/register">
      @csrf
      <label>Name</label>
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
      <label>Email</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      <label>Password</label>
      <input type="password" name="password" required minlength="8">
      <label>Invite code</label>
      <input type="text" name="invite_code" required autocomplete="off">
      <button class="btn btn-primary" type="submit">Create account</button>
    </form>
    <div class="alt-link">Already have an account? <a href="/login">Log in</a></div>
  </div>
</div>
@endsection
