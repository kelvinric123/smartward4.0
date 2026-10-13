{{--
    What a view-only link to the AI Nurse Schedule shows once it has expired or
    been revoked, and for a link that never existed: the same page for all
    three (sent as a 404), so trying links tells nobody which ones are real.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Link not available &middot; AI Nurse Schedule</title>
    @vite(['resources/css/app.css'])
</head>

<body class="flex min-h-screen items-center justify-center bg-gray-100 p-4 text-gray-900">
    <main class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-lg">
        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h1 class="mt-3 text-lg font-bold text-gray-900">This link is not available</h1>
        <p class="mt-2 text-sm text-gray-600">This link has expired or was turned off. Ask the nurse manager for a new one.</p>
        <p class="mt-6 text-xs text-gray-400">SmartWard AI Nurse Schedule</p>
    </main>
</body>

</html>
