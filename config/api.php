<?php

return [
    'rate_limit' => env('API_RATE_LIMIT', 200),
    'token_rate_limit' => (int) env('SATURN_TOKEN_RATE_LIMIT', 60),
];
