<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact details
    |--------------------------------------------------------------------------
    | Values lifted from the current hypersonic.id front-end so the markup has a
    | single source of truth. Override any of them from .env per environment.
    */

    'email' => env('SITE_EMAIL', 'hypersonicspeedtech@gmail.com'),

    'phone' => env('SITE_PHONE', '62895337161221'),

    'whatsapp' => [
        'primary' => env('SITE_WA_PRIMARY', '62895337161221'),

        // customer_support=1|2|3 on the product pages maps onto this list.
        'sales' => [
            env('SITE_WA_SALES_1', '6281234567890'),
            env('SITE_WA_SALES_2', '6282345678901'),
            env('SITE_WA_SALES_3', '6283456789012'),
        ],
    ],

    /*
     * The support desks offered by the "Buy Now" button on a product page.
     * `desk` selects which number in whatsapp.sales the redirect uses.
     */
    'checkout_handlers' => [
        ['label' => 'Admin 1', 'desk' => 1],
        ['label' => 'Admin 2', 'desk' => 2],
    ],

    'social' => [
        'instagram' => 'https://www.instagram.com/hypersonic_speedtech?igsh=ZGwyZDh0azVpcm00',
        'facebook' => 'https://www.facebook.com/share/17kUNcDAyq/',
    ],

];
