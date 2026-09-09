<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            return redirect('/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => 'Those credentials don\'t match our records.',
        ]);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'invite_code' => ['required', 'string'],
        ]);

        $expected = config('services.registration.code');

        // No code configured means no code can match - fails closed
        // rather than accepting anything when someone forgets to set
        // REGISTRATION_CODE. hash_equals rather than === so this isn't
        // a timing side-channel for guessing the code.
        if (! $expected || ! hash_equals((string) $expected, $data['invite_code'])) {
            throw ValidationException::withMessages([
                'invite_code' => 'That invite code is not valid.',
            ]);
        }

        // Every signup gets its own tenant. Doing this here rather
        // than leaving account_id null matters: the global scope fails
        // closed, so a user without an account would log in to a
        // permanently empty dashboard with no error to explain it.
        $account = Account::create(['name' => $data['name']]);

        $user = User::create([
            'account_id' => $account->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            // Never 'staff' from a public form - see User::isStaff().
            'role' => 'client',
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
