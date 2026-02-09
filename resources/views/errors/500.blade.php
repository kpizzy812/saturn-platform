@extends('layouts.base')

@section('content')
<div class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-3xl px-8">
        <p class="font-mono font-semibold text-red-500 text-[200px] leading-none">500</p>
        <h1 class="text-3xl font-bold tracking-tight text-white">Wait, this is not cool...</h1>
        <p class="mt-2 text-lg leading-7 text-neutral-400">There has been an error with the following error message:</p>
        @if ($exception->getMessage() !== '')
            <div class="mt-6 text-sm text-red-500">
                {!! Purify::clean($exception->getMessage()) !!}
            </div>
        @endif
        <div class="flex items-center mt-10 gap-x-2">
            <a href="{{ url()->previous() }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-neutral-800 border border-neutral-700 rounded-md hover:bg-neutral-700">Go back</a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-neutral-800 border border-neutral-700 rounded-md hover:bg-neutral-700">Dashboard</a>
        </div>
    </div>
</div>
@endsection
