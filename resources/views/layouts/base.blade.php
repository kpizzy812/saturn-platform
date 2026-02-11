<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>{{ config('app.name', 'Saturn') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-background text-foreground antialiased min-h-screen">
    @yield('content')
</body>
</html>
