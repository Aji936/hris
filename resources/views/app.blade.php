<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @php
            $componentName = (string) ($page['component'] ?? '');
            $segments = explode('/', $componentName);
            $vitePageEntry = "resources/js/pages/{$componentName}.tsx";

            if (count($segments) >= 2) {
                $moduleName = array_shift($segments);
                $moduleCandidate = 'resources/js/Modules/'.$moduleName.'/Pages/'.implode('/', $segments).'.tsx';
                if (file_exists(base_path($moduleCandidate))) {
                    $vitePageEntry = $moduleCandidate;
                }
            }
        @endphp

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', $vitePageEntry])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
