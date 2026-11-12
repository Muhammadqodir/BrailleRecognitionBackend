@extends('layouts.base')

@php
    $appStoreUrl = 'https://apps.apple.com/app/id1669110413';
    $playUrl = 'https://play.google.com/store/apps/details?id=uz.mq.braillerecognition';

    // Braille codes the app reads (kRecognitionLanguages in the Flutter app).
    $brailleLanguages = [
        ['English braille, Grade 1 (uncontracted)', 'UEB letter-by-letter braille used in early reading and worksheets.'],
        ['English braille, Grade 2 (contracted)', 'The contractions used in most English braille books and homework.'],
        ['Spanish braille', 'Braille en español, including ñ and accented vowels.'],
        ['French braille', 'Braille français with accented letters.'],
        ['Portuguese braille', 'Braille em português, for Brazil and Portugal.'],
        ['German braille', 'Deutsche Brailleschrift with umlauts and ß.'],
        ['Russian braille', 'Шрифт Брайля for the Cyrillic alphabet.'],
        ['Arabic braille', 'Read right to left, as Arabic text is written.'],
        ['Polish braille', 'Alfabet Braille’a with Polish letters.'],
        ['Greek braille', 'Braille for the Greek alphabet.'],
        ['Latvian braille', 'Braille with Latvian letters.'],
        ['Uzbek braille', 'Cyrillic and Latin Uzbek braille.'],
    ];

    // One list feeds both the visible FAQ and the FAQPage schema, so they never disagree.
    $faqs = [
        [
            'q' => 'How do I translate braille to text with my phone?',
            'a' => 'Install Braille Recognition, open the camera in the app and take a photo of the braille page. The app finds the dots, reads every cell and shows the page as plain text that you can copy, save or share.',
        ],
        [
            'q' => 'Which braille languages does the app read?',
            'a' => 'English Grade 1 and Grade 2 (contracted) braille, Spanish, French, Portuguese, German, Russian, Arabic, Polish, Greek, Latvian and Uzbek (Cyrillic and Latin). The app can also pick the language for you.',
        ],
        [
            'q' => 'Who is the braille translator app for?',
            'a' => 'Anyone who needs to read braille but does not know it: teachers of students with visual impairments (TVIs), special education teachers, parents checking homework written in braille, and volunteers or social workers who support blind people.',
        ],
        [
            'q' => 'Is Braille Recognition free?',
            'a' => 'The app is free to download and your first scans are free. Unlimited scanning is part of Premium, which you can try with a free trial on iPhone and Android.',
        ],
        [
            'q' => 'Does it read handwritten braille from a slate or a Perkins brailler?',
            'a' => 'Yes. The recognition model is trained on real photos of embossed paper, including worksheets and homework written on a Perkins brailler or with a slate and stylus.',
        ],
        [
            'q' => 'Can blind or visually impaired people use the app directly?',
            'a' => 'The app is designed for sighted people who support braille readers, such as teachers and family members. Blind users can benefit through the translations, but the app is not yet optimized for use with a screen reader.',
        ],
        [
            'q' => 'How do I take a photo for the best accuracy?',
            'a' => 'Lay the page flat and shoot it straight from above, with the whole page in the frame. Let the light fall at an angle from the top of the page so the dots cast small shadows, and keep the photo sharp. Pages embossed on both sides are harder to read, so photograph the side you need with the light coming from the top.',
        ],
    ];
@endphp

@section('head')
    <style>
        .lead-proof { color: #5f6b7a; font-size: 15px; margin-top: 16px; }
        .store-buttons { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 8px; }
        .store-buttons .button-secondary { text-decoration: none; }
        .steps { list-style: none; padding: 0; margin: 0; counter-reset: step; }
        .steps li { margin: 24px; }
        .steps h3 { margin-bottom: 8px; }
        .lang-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-top: 32px; }
        .lang-grid li { list-style: none; padding: 20px 24px; }
        .lang-grid h3 { font-size: 18px; line-height: 1.3; margin: 0 0 6px; }
        .lang-grid p { margin: 0; color: #5f6b7a; font-size: 15px; }
        .lang-list { padding: 0; margin: 0; }
        .use-cases { padding-left: 20px; }
        .use-cases li { margin-bottom: 8px; }
        .faq-list { max-width: 760px; margin: 32px auto 0; display: grid; gap: 12px; }
        .faq-item { padding: 0; }
        .faq-item summary { list-style: none; cursor: pointer; padding: 20px 56px 20px 24px; position: relative; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary::after { content: "+"; position: absolute; right: 24px; top: 50%; transform: translateY(-50%); font-size: 24px; color: #009AD1; }
        .faq-item[open] summary::after { content: "\2212"; }
        .faq-item summary h3 { margin: 0; font-size: 18px; line-height: 1.4; font-weight: 600; }
        .faq-item p { margin: 0; padding: 0 24px 20px; color: #3d4752; }
        .news-card-fallback { background: linear-gradient(135deg, #007aa6 0%, #009AD1 100%) !important; }
        @media (max-width: 479px) {
            .steps li { margin: 16px 8px; }
        }
    </style>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/') . '/#organization',
                    'name' => 'AL-FOCUS TECH LLC',
                    'url' => 'https://alfocus.uz',
                    'logo' => asset('images/logo_new.png'),
                    'email' => 'mqodir777@gmail.com',
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/') . '/#website',
                    'url' => url('/') . '/',
                    'name' => 'Braille Recognition',
                    'publisher' => ['@id' => url('/') . '/#organization'],
                ],
                [
                    '@type' => 'MobileApplication',
                    'name' => 'Braille Recognition: Braille Translator & Scanner',
                    'alternateName' => 'Braille Recognition',
                    'operatingSystem' => 'iOS, Android',
                    'applicationCategory' => 'EducationalApplication',
                    'description' => 'Take a photo of braille and read it as text. Reads English Grade 1 and Grade 2, Spanish, French, Portuguese, German, Russian, Arabic, Polish, Greek, Latvian and Uzbek braille.',
                    'image' => asset('images/banner_2.png'),
                    'url' => url('/') . '/',
                    'downloadUrl' => [$appStoreUrl, $playUrl],
                    'installUrl' => [$appStoreUrl, $playUrl],
                    'inLanguage' => ['en', 'de', 'es', 'fr', 'it', 'pl', 'pt', 'ru'],
                    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                    'publisher' => ['@id' => url('/') . '/#organization'],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(fn ($f) => [
                        '@type' => 'Question',
                        'name' => $f['q'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                    ], $faqs),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endsection

@section('content')
    <main>
    <section class="section hero-section">
        <div class="container">
            <div class="hero-wrapper">
                <img src="/images/logo_new.png" width="102" height="102" alt="Braille Recognition app icon" class="hero-logo">
                <h1>Braille translator: turn a photo of braille into text</h1>
                <p class="hero-description" style="max-width: 640px;">
                    Point your phone's camera at a braille page and read it as plain text in seconds.
                    Braille Recognition helps teachers, parents and caregivers read homework, worksheets,
                    books and labels written in braille, in 11 languages including contracted English Grade 2.
                </p>
                <div class="store-buttons">
                    <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener" class="button-primary w-inline-block"
                        style="text-decoration: none; color: #fff;">
                        <img src="/res/65bf84149cd3b97eab44ce8c_arrow-down-circle.svg" alt="" class="button-arrow">
                        <div>Download on the App Store</div>
                    </a>
                    <a href="{{ $playUrl }}" target="_blank" rel="noopener" class="button-secondary w-inline-block">
                        <img src="/res/65bf8414ab3fd8f47c85e800_arrow-down-circle-1.svg" alt="" class="button-arrow">
                        <div>Get it on Google Play</div>
                    </a>
                </div>
                <p class="lead-proof">More than 55,000 downloads · used by teachers and families worldwide</p>
            </div>
        </div>
    </section>

    <section class="section overview-section" id="how-it-works">
        <div class="container">
            <div class="overview-wrapper" style="grid-row-gap: 20px;">
                <div class="overview-top">
                    <h2>How to translate braille to text</h2>
                    <p>No braille knowledge needed. Four steps, about a minute.</p>
                </div>
                <div>
                    <div class="custom-card">
                        <div class="row" style="padding: 24px; align-items: center;">
                            <div class="col-md-8">
                                <ol class="steps">
                                    <li>
                                        <h3 class="heading-medium">1. Take a photo of the braille page</h3>
                                        <p>Open the app and photograph the page from above. You can also pick a photo
                                            from your gallery.</p>
                                    </li>
                                    <li>
                                        <h3 class="heading-medium">2. The AI reads the dots</h3>
                                        <p>A recognition model trained on thousands of real phone photos finds every
                                            braille cell, even on worn or handwritten pages.</p>
                                    </li>
                                    <li>
                                        <h3 class="heading-medium">3. Read the text</h3>
                                        <p>The braille is converted into plain text. Each cell is marked on the photo so
                                            you can see which letter came from where.</p>
                                    </li>
                                    <li>
                                        <h3 class="heading-medium">4. Save or share</h3>
                                        <p>Every scan is kept in your history. Copy the text or share it by email,
                                            messaging apps or cloud storage.</p>
                                    </li>
                                </ol>
                            </div>
                            <div class="col-md-4" style="padding: 24px;">
                                <div class="device" data-device="iPhoneX" data-orientation="portrait" data-color="black">
                                    <div class="screen bg-black" style="box-shadow: 0px 5px 50px gray;">
                                        <video muted autoplay loop playsinline preload="metadata"
                                            aria-label="Demo: the app scans a braille page and shows the text"
                                            style="max-width: 100%; height: 100%">
                                            <source src="/video/video_demo.mp4" type="video/mp4" />
                                        </video>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4" style="margin-top: 32px;">
                            <div class="custom-card" style="padding: 24px;">
                                <img src="/images/ai.png" height="30" loading="lazy" alt="">
                                <h3 class="heading-medium">AI braille recognition</h3>
                                <p class="overview-description">
                                    No need to type braille cells by hand. Scan the page and the app reads it for you.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4" style="margin-top: 32px;">
                            <div class="custom-card" style="padding: 24px;">
                                <img src="/images/quick.png" height="30" loading="lazy" alt="">
                                <h3 class="heading-medium">Fast, on your phone</h3>
                                <p class="overview-description">
                                    Recognition runs on the phone itself, so a page comes back in seconds.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4" style="margin-top: 32px;">
                            <div class="custom-card" style="padding: 24px;">
                                <img src="/images/sofa.png" height="30" loading="lazy" alt="">
                                <h3 class="heading-medium">Made for sighted helpers</h3>
                                <p class="overview-description">
                                    A simple app for people who don't read braille: teachers, parents and caregivers.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="languages">
        <div class="container">
            <div class="overview-top" style="text-align: center;">
                <h2>Braille languages the app reads</h2>
                <p>Choose the braille code yourself, or let the app detect it from the page.</p>
            </div>
            <ul class="lang-grid lang-list">
                @foreach ($brailleLanguages as [$name, $note])
                    <li class="custom-card">
                        <h3>{{ $name }}</h3>
                        <p>{{ $note }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="section" id="who-its-for">
        <div class="container">
            <div class="custom-card" style="padding: 32px;">
                <div class="row">
                    <div class="col-md-6">
                        <h2>Who uses Braille Recognition</h2>
                        <p>Most people who scan with the app are teachers and parents of children who read and write
                            in braille.</p>
                    </div>
                    <div class="col-md-6">
                        <ul class="use-cases">
                            <li><strong>Teachers of the visually impaired (TVIs)</strong> marking braille homework and
                                tests faster.</li>
                            <li><strong>Classroom and special education teachers</strong> reading what a blind student
                                wrote.</li>
                            <li><strong>Parents</strong> checking homework written on a Perkins brailler or slate.</li>
                            <li><strong>Volunteers and social workers</strong> reading letters, notes and labels in
                                braille.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="clients-wrapper">
                <div class="overview-top">
                    <h2>Supported by</h2>
                    <p>Grants, accelerators and partners behind Braille Recognition</p>
                </div>
                <div class="clients-list">
                    <a href="https://yandex.cloud/" target="_blank" rel="noopener">
                        <img src="/images/yandex.png" loading="lazy" alt="Yandex Cloud" class="clients-logo">
                    </a>
                    <a href="https://astanahub.com/" target="_blank" rel="noopener">
                        <img src="/images/astana_hub.png" loading="lazy" alt="Astana Hub" class="clients-logo"
                            style="height: 40px;">
                    </a>
                    <a href="https://it-park.uz/" target="_blank" rel="noopener">
                        <img src="/images/it_park.png" loading="lazy" alt="IT Park Uzbekistan" class="clients-logo"
                            style="height: 40px;">
                    </a>
                    <a href="https://www.hse.ru/en/" target="_blank" rel="noopener">
                        <img src="/images/hse.png" loading="lazy" alt="HSE University" class="clients-logo"
                            style="height: 40px;">
                    </a>
                    <a href="https://verdi.uz/" target="_blank" rel="noopener">
                        <img src="/res/verdi.png" loading="lazy" alt="VERDI MEBEL" class="clients-logo"
                            style="height: 40px;">
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="overview-wrapper">
                <div class="overview-top">
                    <h2>In the news</h2>
                    <p>Awards and stories about Braille Recognition.</p>
                </div>
                <div class="benefits-grid">
                    <a style="text-decoration: none;" target="_blank" rel="noopener" href="https://awards.gov.uz/news/22/show">
                        <article class="benefits-card news-card-fallback" style="height: 300px; width: 100%;">
                            <h3 class="heading-medium">Finalist of the President Tech Award</h3>
                            <time class="benefits-caption" datetime="2024-10-04">4 October 2024</time>
                        </article>
                    </a>
                    <a style="text-decoration: none;" target="_blank" rel="noopener"
                        href="https://astanahub.com/ru/article/astana-hub-battle-bitva-tekhnologicheskikh-startapov-vpervye-sostoialas-v-almaty">
                        <article class="benefits-card"
                            style="height: 300px; width: 100%; background: linear-gradient(to bottom, rgb(0 0 0 / 82%) 0%, rgb(0 0 0 / 0%) 100%), url(https://alfocus.uz/uploads/Astana_Hub_Battle:_bitva_tehnologiceskih_startapov_vpervie_sostoyalasy_v_Almati_cover.png); background-size: cover;">
                            <h3 class="heading-medium">We won a 6 million tenge grant from Yandex</h3>
                            <time class="benefits-caption" datetime="2023-02-03">3 February 2023</time>
                        </article>
                    </a>
                    <a style="text-decoration: none;" target="_blank" rel="noopener"
                        href="https://alfocus.uz/post/nash-proekt-braille-recognition-byl-vybran-v-final-prestizhnoy-premii-mgovawards">
                        <article class="benefits-card"
                            style="height: 300px; width: 100%; background: linear-gradient(to bottom, rgb(0 0 0 / 82%) 0%, rgb(0 0 0 / 0%) 100%), url(https://it-park.uz/storage/images/news/normal/5dtooBZTKzIa3a6rwpbPhB9ln2sRHUUpaajpGpqP.png); background-size: cover;">
                            <h3 class="heading-medium">mGovAward finalist</h3>
                            <time class="benefits-caption" datetime="2022-05-20">20 May 2022</time>
                        </article>
                    </a>
                    <a style="text-decoration: none;" target="_blank" rel="noopener" href="https://fest.hse.ru/top50">
                        <article class="benefits-card"
                            style="height: 300px; width: 100%; background: linear-gradient(to bottom, rgb(0 0 0 / 82%) 0%, rgb(0 0 0 / 0%) 100%), url(https://optim.tildacdn.com/tild6138-3165-4332-a530-663165613138/-/format/webp/-50.jpg); background-size: cover;">
                            <h3 class="heading-medium">HSE Fest 2021 finalist</h3>
                            <time class="benefits-caption" datetime="2021-03-10">10 March 2021</time>
                        </article>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="faq">
        <div class="container">
            <div class="hero-wrapper">
                <h2>Frequently asked questions</h2>
                <p class="hero-description">Everything you need to know about translating braille with your phone.</p>
            </div>
            <div class="faq-list">
                @foreach ($faqs as $i => $faq)
                    <details class="faq-item custom-card" @if ($i === 0) open @endif>
                        <summary><h3>{{ $faq['q'] }}</h3></summary>
                        <p>{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section cta-section">
        <div class="container">
            <div class="cta-wrapper">
                <img src="/images/logo_new.png" loading="lazy" width="102" height="102" alt="" class="hero-logo">
                <h2>Read braille with your phone today</h2>
                <p class="hero-description">Braille Recognition is available for iPhone, iPad and Android.</p>
                <div class="store-buttons">
                    <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener" class="button-primary w-inline-block"
                        style="text-decoration: none; color: #fff;">
                        <img src="/res/65bf84149cd3b97eab44ce8c_arrow-down-circle.svg" alt="" class="button-arrow">
                        <div>App Store</div>
                    </a>
                    <a href="{{ $playUrl }}" target="_blank" rel="noopener" class="button-secondary w-inline-block">
                        <img src="/res/65bf8414ab3fd8f47c85e800_arrow-down-circle-1.svg" alt="" class="button-arrow">
                        <div>Google Play</div>
                    </a>
                </div>
            </div>
        </div>
    </section>
    </main>
@endsection
