<x-mail::message>
# Sign in to {{ config('app.name') }}

Click the button below to sign in. The link works once and expires in {{ $minutes }} minutes.

<x-mail::button :url="$url">
Sign in
</x-mail::button>

If you didn't ask to sign in, you can ignore this email. Nobody can sign in without this link.

<x-mail::subcopy>
If the button doesn't work, copy this link into your browser: {{ $url }}
</x-mail::subcopy>
</x-mail::message>
