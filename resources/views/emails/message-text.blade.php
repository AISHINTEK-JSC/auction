{{-- Plain-text version of emails/message.blade.php, for programs that do not show HTML. --}}
@if (! empty($greeting))
{!! $greeting !!}

@endif
@foreach ($introLines as $line)
{!! $line !!}

@endforeach
@isset($actionText)
{!! $actionText !!}: {!! $actionUrl !!}

@endisset
@foreach ($outroLines as $line)
{!! $line !!}

@endforeach
@foreach ($signatureLines as $line)
{!! $line !!}
@endforeach

--
@foreach ($footerLines as $line)
{!! $line !!}
@endforeach
