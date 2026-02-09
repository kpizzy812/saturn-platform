@extends('layouts.base')

@section('content')
<div class="flex flex-col items-center justify-center min-h-screen">
    <div>
        <p class="font-mono font-semibold text-7xl text-warning">404</p>
        <h1 class="mt-4 font-bold tracking-tight text-white">How did you get here?</h1>
        <p class="text-base leading-7 text-neutral-400">Sorry, we couldn't find the page you're looking for.</p>
        <div class="flex items-center mt-10 gap-x-2">
            <a href="{{ url()->previous() }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-neutral-800 border border-neutral-700 rounded-md hover:bg-neutral-700">Go back</a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-neutral-800 border border-neutral-700 rounded-md hover:bg-neutral-700">Dashboard</a>
        </div>
    </div>
</div>
@endsection
