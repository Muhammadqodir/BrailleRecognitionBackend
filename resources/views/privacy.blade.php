@extends('layouts.base')

@section('head')
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

        .privacy-content h2 {
            font-size: 24px;
            font-weight: 700;
            margin-top: 32px;
            margin-bottom: 16px;
            color: #1a1a1a;
        }

        .privacy-content p {
            margin-bottom: 16px;
            line-height: 1.7;
            color: #444;
        }

        .privacy-content ul {
            margin-bottom: 16px;
            padding-left: 24px;
        }

        .privacy-content li {
            margin-bottom: 8px;
            line-height: 1.7;
            color: #444;
        }

        .privacy-content a {
            color: #3FAFEA;
            text-decoration: none;
        }

        .privacy-content a:hover {
            text-decoration: underline;
        }

        .effective-date {
            background-color: #f5f5f5;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            color: #666;
            font-style: italic;
        }
    </style>
@endsection

@section('content')
    <section class="section hero-section">
        <div class="container">
            <div data-w-id="653d6031-aa80-5f0c-561b-f7d572d05b1e" class="hero-wrapper"
                style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d; opacity: 1;">
                <img src="./images/logo_new.png" loading="lazy" width="102" alt="" class="hero-logo">
                <h1>Privacy Policy</h1>
                <p class="hero-description" style="max-width: 700px;">
                    Your privacy is important to us. This policy explains how we collect, use, and protect your
                    information when you use BrailleRecognition.
                </p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container" style="max-width: 900px;">
            <div class="custom-card" style="padding: 48px; margin-bottom: 40px;">
                <div class="effective-date">
                    This policy is effective as of October 26, 2026
                </div>

                <div class="privacy-content">
                    <p>
                        <strong>Braille Recognition</strong> (in the stores: Braille Translator &amp; Scanner) is provided by
                        <strong>AL-FOCUS TECH LLC</strong>. This page explains what information the app and this website
                        collect, how we use it, and the choices you have.
                    </p>

                    <h2>What we collect</h2>
                    <ul>
                        <li>
                            <strong>Your account.</strong> If you sign in with Apple or Google, we receive your name (if
                            you share it), your email address (or Apple's private relay address) and an account identifier.
                            Without signing in, the app uses an anonymous account tied to this installation.
                        </li>
                        <li>
                            <strong>Photos you scan and their results.</strong> Braille is recognised on your device. The
                            photo, the recognised braille and the translated text are then saved to your account on our
                            server, so they appear in your history. Photos of scans that could not be read may also be saved,
                            to find out why.
                        </li>
                        <li>
                            <strong>App usage.</strong> Events such as opening the app, starting a scan, how long it took and
                            whether it succeeded, with the app version, platform, language setting and subscription status.
                            They are linked to a random installation identifier and to your account if you have one.
                        </li>
                        <li>
                            <strong>Purchases.</strong> Subscriptions are paid through the App Store or Google Play. We do
                            not see your payment details; we receive the subscription status through RevenueCat.
                        </li>
                        <li>
                            <strong>Messages you send us</strong>, for example through the contact or account deletion forms.
                        </li>
                    </ul>
                    <p>
                        The camera is used only to take the photo you scan. While the in-app camera is open, the preview is
                        checked on your device for braille; preview frames are not stored or sent anywhere.
                    </p>

                    <h2>How we use it</h2>
                    <ul>
                        <li>To provide the service: recognise braille, keep your history, and manage your account and
                            subscription.</li>
                        <li>To understand how the app is used and fix problems.</li>
                        <li>
                            <strong>To improve braille recognition.</strong> Our team may look at photos of braille pages
                            scanned with the app and mark the braille cells on them by hand. These marked photos are used to
                            train and test our recognition models, which then ship in the app. Training runs on our servers
                            and on cloud computing services (currently Kaggle, a Google service), to which photos are copied
                            only for the duration of the training. The trained models do not contain your photos, and we do
                            not use the photos for anything else.
                        </li>
                    </ul>
                    <p>
                        We do not sell your information, we do not show advertising, and we do not track you across other
                        companies' apps or websites.
                    </p>

                    <h2>Who we share it with</h2>
                    <p>We share information only with services that work for us and only as needed for the purposes above:</p>
                    <ul>
                        <li><a href="https://www.apple.com/legal/privacy/" target="_blank" rel="noopener noreferrer">Apple</a>
                            and <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Google</a>,
                            for sign-in, app distribution and in-app purchases;</li>
                        <li><a href="https://www.revenuecat.com/privacy/" target="_blank" rel="noopener noreferrer">RevenueCat</a>,
                            to manage subscriptions;</li>
                        <li><a href="https://www.kaggle.com/privacy" target="_blank" rel="noopener noreferrer">Kaggle (Google)</a>,
                            for model training as described above;</li>
                        <li>the companies that host our servers.</li>
                    </ul>
                    <p>We may also disclose information if the law requires it.</p>

                    <h2>How long we keep it</h2>
                    <p>
                        We keep your account, photos and history until you delete them or your account. Usage events are kept
                        for analysis and are no longer linked to you once your account is deleted.
                    </p>

                    <h2>Your choices</h2>
                    <ul>
                        <li><strong>Delete a scan:</strong> remove it from your history in the app. Its photo is deleted from
                            our server and is no longer used for training.</li>
                        <li><strong>Delete your account:</strong> in the app, go to Profile &gt; Account &gt; Delete Account,
                            or use the <a href="{{ route('profile.deletion.form') }}">account deletion form</a>. Your account,
                            history and photos are deleted from our server.</li>
                        <li><strong>Opt out of training:</strong> email us at <a href="mailto:info@alfocus.uz">info@alfocus.uz</a>
                            and we will exclude your photos from future training while keeping your history.</li>
                    </ul>
                    <p>
                        Models that were already trained before a deletion or opt-out are not retrained because of it; they
                        do not contain the photos themselves. Depending on where you live, you may also have the right to
                        access or correct your information or to object to how we use it. Write to us to do so.
                    </p>

                    <h2>Security</h2>
                    <p>
                        Information is sent over encrypted connections, and access to photos and to the tools used to mark
                        them is limited to our team. No method of transmission or storage is completely secure, so we cannot
                        guarantee absolute security.
                    </p>

                    <h2>Children's Privacy</h2>
                    <p>
                        The service is not directed at children under 13, and we do not knowingly collect personal
                        information from them. If you are a parent or guardian and believe your child has given us personal
                        information, contact us and we will delete it.
                    </p>

                    <h2>Changes to This Privacy Policy</h2>
                    <p>
                        We may update this policy. The new version is posted on this page with its effective date.
                    </p>

                    <h2>Contact Us</h2>
                    <p>
                        If you have any questions about this policy, contact us at
                        <a href="mailto:info@alfocus.uz">info@alfocus.uz</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
