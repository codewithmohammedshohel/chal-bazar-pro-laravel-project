<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp click-to-chat order confirmation
    |--------------------------------------------------------------------------
    |
    | The destination must be the shop's own number in canonical international
    | format (E.164 digits, no plus sign, no separators), for example:
    |
    |     WHATSAPP_BUSINESS_NUMBER=8801712345678
    |
    | When the value is missing or is not a valid international number the
    | feature is hidden from the storefront instead of failing at click time.
    |
    | This integration only builds a wa.me link. It never sends a message and
    | it does not use the WhatsApp Cloud API.
    |
    */

    'enabled' => (bool) env('WHATSAPP_ENABLED', true),

    'business_number' => env('WHATSAPP_BUSINESS_NUMBER'),

    'business_name' => env('WHATSAPP_BUSINESS_NAME', env('APP_NAME', 'Chal Bazar Pro')),

];
