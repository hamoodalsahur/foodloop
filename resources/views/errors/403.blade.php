@include('errors.layout', [
    'code' => 403,
    'title' => 'Not for this account.',
    'message' => $exception->getMessage() ?: 'You’re signed in, but this page belongs to a different kind of account.',
])
