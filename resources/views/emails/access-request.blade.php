<x-mail::message>
# New Access Request

You have received a new request to access the platform.

**Name:** {{ $data['first_name'] }} {{ $data['last_name'] }}<br>
**Email:** {{ $data['email'] }}<br>
**Company:** {{ $data['company'] }}<br>
**Job Title:** {{ $data['job_title'] }}<br>
**Phone:** {{ $data['phone'] ?? 'N/A' }}

**Why they need access:**
> {{ $data['details'] }}

<x-mail::button :url="'mailto:' . $data['email']">
Reply to {{ $data['first_name'] }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>