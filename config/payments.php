<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mock Payments
    |--------------------------------------------------------------------------
    |
    | `POST /api/v1/orders/{order}/pay` marks orders as paid without charging anyone. It is for development
    | and staging only, so it is switched off in production unless MOCK_PAYMENTS_ENABLED says otherwise.
    |
    */

    'mock_enabled' => (bool) env('MOCK_PAYMENTS_ENABLED', env('APP_ENV') !== 'production'),

];
