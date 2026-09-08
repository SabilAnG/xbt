<?php

use App\Models\ContentBlock;
use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Read a site setting, falling back to the value the site shipped with.
     * Both settings and content blocks are cached, so this is cheap to call
     * repeatedly from a Blade template.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('content')) {
    /**
     * Editable copy. $default is the wording the page originally had, so a page
     * still renders correctly before anything is changed in the admin.
     */
    function content(string $key, ?string $default = null): ?string
    {
        return ContentBlock::get($key, $default);
    }
}

if (! function_exists('content_image')) {
    /**
     * Editable image. Values are paths relative to public/, matching what
     * asset() expects — e.g. "assets/images/header1.png".
     */
    function content_image(string $key, ?string $default = null): string
    {
        $path = ContentBlock::get($key, $default);

        return $path ? asset($path) : '';
    }
}
