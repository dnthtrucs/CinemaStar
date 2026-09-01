<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased" style="font-family:'Be Vietnam Pro',sans-serif; background:linear-gradient(135deg,#210b12 0%,#650d19 55%,#a51c30 100%);">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">
            <div>
                <a href="/" class="flex items-center gap-3 text-yellow-100 font-extrabold tracking-wide">
                    <span class="inline-grid place-items-center w-11 h-11 rounded-xl bg-amber-300 text-red-950 shadow-lg">★</span> CINEMASTAR
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-5 bg-white shadow-2xl overflow-hidden sm:rounded-2xl border border-amber-200">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
