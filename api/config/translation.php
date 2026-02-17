<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Translation service (e.g. LibreTranslate)
    |--------------------------------------------------------------------------
    |
    | When LIBRE_TRANSLATE_URL is set, the app can use it to translate content.
    | Leave empty to use the stub (returns original text). API key optional.
    |
    */

    'driver' => env('TRANSLATION_DRIVER', 'stub'),

    'libre_translate' => [
        'url' => rtrim(env('LIBRE_TRANSLATE_URL', ''), '/'),
        'api_key' => env('LIBRE_TRANSLATE_API_KEY'),
    ],

];
