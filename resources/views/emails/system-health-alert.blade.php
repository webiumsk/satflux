Satflux system health report ({{ now()->toDateTimeString() }} {{ config('app.timezone') }})

@if ($failed !== [])
FAILED CHECKS:
@foreach ($failed as $check)
- {{ $check }}: {{ $checks[$check]['detail'] ?? 'no detail' }}
@endforeach
@endif
@if ($recovered !== [])
RECOVERED:
@foreach ($recovered as $check)
- {{ $check }}: {{ $checks[$check]['detail'] ?? 'no detail' }}
@endforeach
@endif

All checks:
@foreach ($checks as $name => $result)
- {{ $name }}: {{ ($result['ok'] ?? false) ? 'OK' : 'FAILED' }} - {{ $result['detail'] ?? '' }}
@endforeach

Dashboard: {{ rtrim(config('app.url'), '/') }}/admin/system-health
