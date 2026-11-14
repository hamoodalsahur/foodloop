@include('errors.layout', [
    'code' => 429,
    'title' => 'Slow down a little.',
    'message' => 'Too many tries in a short time. Wait a minute, then try again.',
    'retry' => true,
])
