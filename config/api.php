<?php

return [
    'rate_limit' => env('API_RATE_LIMIT', 200),
    'token_rate_limit' => (int) env('SATURN_TOKEN_RATE_LIMIT', 60),
    'session_rate_limit' => (int) env('SATURN_SESSION_RATE_LIMIT', 120),
    'max_token_rate_limit' => (int) env('SATURN_MAX_TOKEN_RATE_LIMIT', 500),
];
