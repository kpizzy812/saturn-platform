<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title inertia>{{ config('app.name', 'Saturn') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @viteReactRefresh
    @vite(['resources/js/app.tsx', 'resources/css/app.css'])
    @inertiaHead
</head>
<body class="bg-background text-foreground antialiased">
    @inertia
</body>
</html>
