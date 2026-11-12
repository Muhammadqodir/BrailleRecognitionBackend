<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: 'Braille Translator & Scanner – Braille to Text App';
        $seoDescription = trim($__env->yieldContent('description')) ?: 'Take a photo of braille and read it as text in seconds. Braille translator app for iPhone and Android: English Grade 1 & 2, Spanish, French, Portuguese, German, Russian, Arabic and more.';
        // Canonical host is fixed so http://, www. or IP visits never compete with the real URL.
        $seoUrl = 'https://braillerecognition.alfocus.uz' . (request()->path() === '/' ? '/' : '/' . request()->path());
        $seoImage = 'https://braillerecognition.alfocus.uz/images/banner_2.png';
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
    <link rel="canonical" href="{{ $seoUrl }}">
    <meta name="theme-color" content="#009AD1">
    <meta name="apple-itunes-app" content="app-id=1669110413">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Braille Recognition">
    <meta property="og:locale" content="en_US">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="Braille Recognition app turning a photo of braille into text">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    <style>
        .wf-force-outline-none[tabindex="-1"]:focus {
            outline: none;
        }

        /* The Webflow template starts these blocks invisible and fades them in
           with its interactions; content must be visible without that script. */
        .navigation, .hero-wrapper, .overview-top, .custom-card, .clients-wrapper,
        .benefits-card, .cta-wrapper, .footer-wrapper, .button-arrow,
        .clients-list, .clients-logo, .help-wrapper, .help-card {
            opacity: 1 !important;
            transform: none !important;
        }

        section[id] {
            scroll-margin-top: 96px;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css"
        integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <link href="/res/thrive-template.webflow.d602b637b.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous">
    <script src="/res/webfont.js" type="text/javascript"></script>
    <link rel="stylesheet" href="/res/device.css">
    <link rel="stylesheet" href="/res/css" media="all">
    <script type="text/javascript">
        WebFont.load({
            google: {
                families: ["Inter:regular,500,600,700"]
            }
        });
    </script>
    <script type="text/javascript">
        ! function(o, c) {
            var n = c.documentElement,
                t = " w-mod-";
            n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n
                .className += t + "touch")
        }(window, document);
    </script>
    <link href="/images/logo_new.png" rel="icon" type="image/png">
    <link href="/images/logo_new.png" rel="apple-touch-icon">
    <style>
        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .custom-card {
            background-color: var(--white--primary);
            box-shadow: 0 1px 3px 0 var(--gray--secondary);
            border-radius: 30px;
        }
    </style>
    @yield('head')
</head>

<body>
    <div class="page-wrapper">
        <nav aria-label="Main" data-w-id="398f822d-d7ae-bf3b-539b-fdeedbd81689" class="navigation"
            style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d; opacity: 1;">
            <div class="nav-wrapper" style="max-width: 800px;">
                <a href="https://braillerecognition.alfocus.uz/" style="text-decoration: none;" aria-current="page"
                    class="w-inline-block w--current">
                    <span class="heading-small" style="font-weight: 700;">Braille Recognition</span>
                </a>
                <div class="nav-menu">
                    <a href="/#how-it-works" class="nav-link">How it works</a>
                    <a href="/#languages" class="nav-link">Languages</a>
                    <a href="/#faq" class="nav-link">FAQ</a>
                    <a href="https://onelink.to/vjyqau" target="_blank" style="text-decoration: none;"
                        class="button-secondary w-inline-block">
                        <img src="/res/65bf8414ab3fd8f47c85e800_arrow-down-circle-1.svg" loading="lazy" alt=""
                            class="button-arrow"
                            style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d;">
                        <div>Get App</div>
                    </a>
                </div>
                <div class="nav-burger">
                    <div data-is-ix2-target="1" class="nav-lottie" data-w-id="398f822d-d7ae-bf3b-539b-fdeedbd81697"
                        data-animation-type="lottie"
                        data-src="https://cdn.prod.website-files.com/65bf7d3d6bb1c09b8e01a7d0/65bf98d3d599ba7badd96611_lottieflow-menu-nav-09-000000-easey.json"
                        data-loop="0" data-direction="1" data-autoplay="0" data-renderer="svg"
                        data-default-duration="2.5" data-duration="0">
                    </div>
                </div>
            </div>
        </nav>
        @yield('content')
        <section class="footer-section">
            <div class="section footer-top">
                <div class="container">
                    <div class="footer-wrapper"
                        style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d; opacity: 1;">
                        <a href="/" style="font-weight: bold; text-decoration: none; color: inherit;"
                            class="w-inline-block">
                            Braille Recognition
                        </a>
                        <div class="footer-menu">
                            <a href="https://apps.apple.com/app/id1669110413" class="footer-link" target="_blank" rel="noopener">App Store</a>
                            <a href="https://play.google.com/store/apps/details?id=uz.mq.braillerecognition" class="footer-link" target="_blank" rel="noopener">Google Play</a>
                            <a href="/privacy-policy" class="footer-link">Privacy Policy</a>
                            <a href="/terms-of-use" class="footer-link">Terms of Use</a>
                            <a href="mailto:mqodir777@gmail.com" class="footer-link">Contact us</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section footer-bottom">
                <div class="container">
                    <div class="footer-wrapper bottom"
                        style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d; opacity: 1;">
                        <div class="footer-copyright"><a href="https://alfocus.uz" target="_blank"
                                rel="noopener" class="footer-copyright-link">AL-FOCUS TECH LLC</a> © {{ date('Y') }}. All rights reserved.</div>
                        <div class="footer-menu">
                            <!-- <a href="https://thrive-template.webflow.io/utility/style-guide"
                                class="footer-secondary-link">Style Guide</a><a
                                href="https://thrive-template.webflow.io/utility/changelog"
                                class="footer-secondary-link">Changelog</a><a
                                href="https://thrive-template.webflow.io/utility/license"
                                class="footer-secondary-link">License</a><a href="https://webflow.com/" target="_blank"
                                class="footer-secondary-link">Webflow</a> -->
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.slim.js"
        integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>
    <script src="/res/webflow.142103ecd.js" type="text/javascript"></script>
</body>

</html>
