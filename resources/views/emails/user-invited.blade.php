<x-mail::message>
# Welcome to {{ config('app.name') }}, {{ $user->first_name }}!

An administrator has created an account for you. To activate your account and access the platform, please set your password below.

@php
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:8000');
    $setupUrl = $frontendUrl . '/set-password?token=' . $token . '&email=' . urlencode($user->email);
@endphp

<x-mail::button :url="$setupUrl" color="success">
Set Password
</x-mail::button>

This password setup link will expire in 60 minutes.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>