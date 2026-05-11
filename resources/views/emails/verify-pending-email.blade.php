<x-mail::message>
# Verify your new email address

You recently requested to change the email address on your account. 

Please click the button below to verify this inbox and finalize the update. This link will expire in 60 minutes.

<x-mail::button :url="$url">
Verify Email Address
</x-mail::button>

If you did not request this change, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>