<!DOCTYPE html>
<html dir="rtl" lang="fa-IR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    @routes
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>
<body class="antialiased" dir="rtl">
@inertia
</body>
</html>
