<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body class="text-sm ">
    <div class="
        flex flex-col
        items-center justify-center gap-4 p-4
        min-h-dvh max-w-sm mx-auto">
        {{-- Logo --}}
        <img src="{{ Vite::asset('resources/images/icon.png') }}" class="size-28" />

        {{-- Heading --}}
        <h1 class="text-3xl text-center text-orange-500 font-turret-road">{{ config('app.name') }}</h1>

        {{-- Slot --}}
        {{ $slot }}
    </div>
  </body>
</html>
