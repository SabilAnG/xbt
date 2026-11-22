<header class="transparent">
    <div id="topbar">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-end xs-hide">
                        <div class="d-flex">
                            <div class="topbar-widget me-5">
                                                                <a href="tel:62895337161221">
                                    <img src="{{ content_image('header.image.1', 'assets/images/misc/phone.svg') }}" class=""
                                        alt="" />62895337161221
                                </a>
                            </div>
                            <div class="topbar-widget">
                                                                <a href="mailto:hypersonicspeedtech@gmail.com">
                                    <img src="{{ content_image('header.image.2', 'assets/images/misc/envelope.svg') }}" class=""
                                        alt="" />hypersonicspeedtech@gmail.com
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="de-flex sm-pt10">
                    <div class="de-flex-col">
                        <!-- logo begin -->
                        <div id="logo">
                            <a href="/">
                                                                    <img class="logo-main" src="{{ asset(setting('site.logo', 'assets/images/logo-white.png')) }}"
                                        alt="" style="height: 50px; width: auto; object-fit: contain;" />
                                    <img class="logo-mobile" src="{{ asset(setting('site.logo', 'assets/images/logo-white.png')) }}"
                                        alt="" style="height: 40px" />
                                    {{-- Versi logo untuk tema terang --}}
                                    <img class="logo-on-light" src="{{ asset(setting('site.logo_light', 'assets/images/logo-dark.png')) }}"
                                        alt="" style="height: 50px; width: auto; object-fit: contain;" />
                                                            </a>
                        </div>
                        <!-- logo end -->
                    </div>
                    <div class="de-flex-col header-col-mid">
                        <!-- mainemenu begin -->
                        <ul id="mainmenu">
                            <li>
                                <a class="menu-item" href="/">Home</a>
                            </li>
                            <li>
                                <a class="menu-item" href="/products">Products</a>
                            </li>
                            <li>
                                <a class="menu-item" href="/workshop">Workshop</a>
                            </li>
                            <li>
                                <a class="menu-item" href="/about">About</a>
                            </li>
                            <li>
                                <a class="menu-item" href="/contact">Contact</a>
                            </li>
                            <li class="tracking-link-mobile" style="display: none">
                                <a href="/tracking"
                                    class="btn-tracking-mobile fx-slide hover-white d-inline-flex align-items-center py-3"
                                    style="font-size: 16px;">
                                    <i class="fas fa-shipping-fast me-2" style="font-size: 16px;"></i>
                                    <span>{{ content('header.text.1', 'Tracking') }}</span>
                                </a>
                            </li>
                        </ul>
                        <!-- mainmenu end -->
                    </div>
                    <div class="de-flex-col">
                        <div class="menu_side_area">
                            <a href="{{ route('cart', [], false) }}" class="header-cart" aria-label="Keranjang">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2M9 20.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Z"/></svg>
                                <span data-cart-count hidden>0</span>
                            </a>
                            <button type="button" class="theme-toggle" onclick="toggleSiteTheme()" aria-label="Ganti tema terang / gelap" title="Ganti tema">
                                <svg class="theme-toggle-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                                <svg class="theme-toggle-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                            </button>
                            <a href="/tracking"
                                class="btn-main fx-slide hover-white d-inline-flex align-items-center px-3 py-2"
                                style="font-size: 14px;">
                                <i class="fas fa-shipping-fast me-2" style="font-size: 16px;"></i>
                                <span>{{ content('header.text.2', 'Tracking') }}</span>
                            </a>
                            <span id="menu-btn"></span>
                        </div>

                        <div id="btn-extra">
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
