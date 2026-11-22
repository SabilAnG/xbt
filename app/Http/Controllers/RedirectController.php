<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /**
     * Hosts an outbound link is allowed to point at. The original site takes any
     * ?to= value; keeping a list here stops the endpoint being an open redirect.
     */
    private const ALLOWED_HOSTS = [
        'wa.me',
        'api.whatsapp.com',
        'www.facebook.com',
        'facebook.com',
        'www.instagram.com',
        'instagram.com',
        'www.tiktok.com',
        'tiktok.com',
        'www.youtube.com',
        'youtube.com',
    ];

    /**
     * Outbound click tracker used by the footer and overlay menus:
     * /redirect/away?to=<encoded url>&utm_source=web_footer_menu
     */
    public function away(Request $request): RedirectResponse
    {
        $target = (string) $request->query('to', '');

        if ($target === '' || ! $this->isAllowed($target)) {
            return redirect()->route('home');
        }

        // Hook for click analytics — the source is already passed by the markup.
        // logger()->info('outbound', ['to' => $target, 'src' => $request->query('utm_source')]);

        return redirect()->away($target);
    }

    /**
     * Product enquiry shortcut: /redirect/product-direct-checkout?product=<slug>&customer_support=1
     * Sends the visitor to WhatsApp with the product already named in the message.
     */
    public function productDirectCheckout(Request $request): RedirectResponse
    {
        $slug = (string) $request->query('product', '');
        $desk = (int) $request->query('customer_support', 1);

        // Nomor diatur di admin (Pengaturan Situs → WhatsApp); config hanya cadangan.
        $numbers = config('site.whatsapp.sales', []);
        $number = Setting::get("whatsapp.sales{$desk}")
            ?: ($numbers[$desk - 1] ?? null)
            ?: Setting::get('whatsapp.primary', config('site.whatsapp.primary'));

        $product = $slug !== '' ? Product::where('slug', $slug)->first() : null;

        if ($product?->currency === 'IDR') {
            $text = sprintf(
                "Halo %s, saya mau pesan:\n%s\nHarga: %s\n%s",
                Setting::get('site.name', 'Hypersonic Speed Tech'),
                $product->name,
                $product->formattedPrice(),
                route('products.show', $product->slug),
            );
        } else {
            $name = $product?->name ?? ($slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : 'your products');
            $text = sprintf('Hello Hypersonic Speed Tech, I am interested in %s.', $name);
        }

        return redirect()->away(
            'https://wa.me/'.$number.'?text='.rawurlencode($text)
        );
    }

    private function isAllowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($host) || ! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return in_array(strtolower($host), self::ALLOWED_HOSTS, true);
    }
}
