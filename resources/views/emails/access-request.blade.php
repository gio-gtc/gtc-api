<x-mail::message>
# New Access Request

You have received a new request to access the platform.

**Name:** {{ $data['first_name'] }} {{ $data['last_name'] }}<br>
**Email:** {{ $data['email'] }}<br>
**Organisation:** {{ $data['organisation'] }}<br>
**Job Title:** {{ $data['job_title'] }}<br>
**Phone:** {{ $data['phone'] ?? 'N/A' }}

**Why they need access:**
> {{ $data['details'] }}

@php
    // 1. Grab your proxy's URL from the .env (fallback to localhost for testing)
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:8000');
    
    // 2. Build the query string with the user's data
    $queryParams = http_build_query([
        'action' => 'create_contact',
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'email' => $data['email'],
        'organisation' => $data['organisation'],
        'job_title' => $data['job_title'],
        'phone' => $data['phone'] ?? '',
    ]);

    // 3. Combine them to make the final link
    // Change '/contacts' to whatever your actual route is for the user management page!
    $createAccountUrl = $frontendUrl . '/dashboard?' . $queryParams;
@endphp

<x-mail::button :url="$createAccountUrl" color="success">
Create Account
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>