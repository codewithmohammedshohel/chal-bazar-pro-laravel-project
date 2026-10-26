<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice PDF
    |--------------------------------------------------------------------------
    |
    | PDF invoices are rendered on demand from the issued invoice record. No
    | PDF file is written to disk, so repeated downloads can never create a
    | second invoice number, a duplicate invoice row or a duplicate file.
    |
    */

    'pdf' => [
        'enabled' => (bool) env('INVOICE_PDF_ENABLED', true),

        'paper' => 'A4',

        /*
         * Bengali font that is embedded into every generated PDF. The font is
         * shipped inside the repository (SIL Open Font License 1.1) so the
         * renderer never depends on a remote font service or on the fonts
         * installed on the host.
         */
        'font_family' => 'hindsiliguri',
        'font_dir' => resource_path('fonts/hind-siliguri'),

        /*
         * Scratch directory for mPDF's font-metric cache. It is created on
         * demand and must be writable by the web server user.
         */
        'temp_dir' => storage_path('app/mpdf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business identity printed on the invoice
    |--------------------------------------------------------------------------
    |
    | The phone number is optional and only printed when configured. It is a
    | display value; it is never used as the WhatsApp destination.
    |
    */

    'business' => [
        'name' => env('INVOICE_BUSINESS_NAME', 'Chal Bazar Pro'),
        'name_bn' => env('INVOICE_BUSINESS_NAME_BN', 'চাল বাজার প্রো'),
        'phone' => env('INVOICE_BUSINESS_PHONE'),
        'address' => env('INVOICE_BUSINESS_ADDRESS'),
    ],

];
