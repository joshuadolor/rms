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

    /*
    |--------------------------------------------------------------------------
    | Locale code mapping (app code => service code)
    |--------------------------------------------------------------------------
    |
    | LibreTranslate uses ISO 639-1 codes: en, es, ar, zh, de, fr, ru, ja, etc.
    | The app uses the same codes (see config/locales.php). Only add a mapping
    | when your LibreTranslate instance expects a different code (e.g. zh-CN).
    | If not in the map, the app code is sent as-is.
    |
    */
    'locale_map' => [
        'zh' => env('TRANSLATION_LOCALE_ZH', 'zh'),
        // App uses fil (Filipino); LibreTranslate uses tl (Tagalog).
        'fil' => 'tl',
    ],

];
