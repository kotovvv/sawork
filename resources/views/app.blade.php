<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    {{-- @vite('resources/js/app.js') --}}
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
    {{-- <link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}" defer></script> --}}

@production
    @php $path = public_path('build\assets'); @endphp

@if (file_exists($path))
    @foreach (scandir($path) as $file)
        @if (strpos($file, '.css'))
            <link rel="stylesheet" href="{{ asset('build/assets/' . $file) }}">
        @endif
        @if (strpos($file, '.js'))
            @push('scripts')
                <script src="{{ asset('build/assets/' . $file) }}"></script>
            @endpush()
        @endif
    @endforeach
@endif
@else
@vite(['resources/css/app.css', 'resources/js/app.js'])
@endproduction
</head>
<body>
<div id="app"></div>
@stack('scripts')
</body>
</html>
