@include('errors.layout', [
    'code' => 500,
    'title' => 'Something broke on our side.',
    'message' => 'It’s not you. Your reservations and pickup codes are safe. Try again in a moment.',
    'retry' => true,
])
