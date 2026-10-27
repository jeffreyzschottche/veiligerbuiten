{{ $copy['title'] }}

{{ $common['hello'] }} {{ $name }},

{{ $copy['intro'] }}

{{ $common['email'] }}: {{ $email }}

{{ $copy['action'] }}:
{!! $url !!}

{{ str_replace(':minutes', (string) $minutes, $common['expires']) }}

@if (!empty($copy['details']))
{{ $copy['details_title'] }}
@foreach ($copy['details'] as $detail)
- {{ $detail }}
@endforeach
@endif

{{ $copy['note'] ?? '' }}

{{ $copy['ignore'] }}

{{ $common['account'] }}: {{ $frontend }}/account
{{ $common['help'] }} hallo@veiligerbuiten.nl
{{ $common['privacy'] }}: {{ $frontend }}/privacy
{{ $common['terms'] }}: {{ $frontend }}/voorwaarden

VeiligerBuiten · Be safe.
