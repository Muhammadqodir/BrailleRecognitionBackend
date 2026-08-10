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

        .plan-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .plan-table th,
        .plan-table td {
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e5e5;
            color: #444;
            line-height: 1.7;
        }

        .plan-table th {
            color: #1a1a1a;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    <section class="section hero-section">
        <div class="container">
            <div data-w-id="653d6031-aa80-5f0c-561b-f7d572d05b1e" class="hero-wrapper"
                style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d; opacity: 1;">
                <img src="./images/logo_new.png" loading="lazy" width="102" alt="" class="hero-logo">
                <h1>Terms of Use</h1>
                <p class="hero-description" style="max-width: 700px;">
                    These terms govern your use of BrailleRecognition, including the Braille Premium
                    auto-renewable subscription.
                </p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container" style="max-width: 900px;">
            <div class="custom-card" style="padding: 48px; margin-bottom: 40px;">
                <div class="effective-date">
                    These terms are effective as of August 10, 2026
                </div>

                <div class="privacy-content">
                    <p>
                        <strong>Braille Recognition</strong> ("the App") is provided by
                        <strong>AL-FOCUS TECH LLC</strong>. By downloading or using the App you agree to these Terms
                        of Use. If you do not agree, please do not use the App.
                    </p>

                    <h2>License</h2>
                    <p>
                        The App is licensed to you, not sold. You are granted a non-exclusive, non-transferable
                        license to use the App on devices that you own or control. You may not resell, redistribute,
                        reverse-engineer, or attempt to extract the source code of the App.
                    </p>
                    <p>
                        Copies obtained from the Apple App Store are additionally governed by Apple's Licensed
                        Application End User License Agreement, which is incorporated here by reference and available
                        at
                        <a href="https://www.apple.com/legal/internet-services/itunes/dev/stdeula/" target="_blank"
                            rel="noopener noreferrer">https://www.apple.com/legal/internet-services/itunes/dev/stdeula/</a>.
                        Copies obtained from Google Play are additionally governed by the Google Play Terms of
                        Service.
                    </p>

                    <h2>Accounts</h2>
                    <p>
                        The App requires an account. You are responsible for keeping your credentials secure and for
                        activity that occurs under your account. We may suspend accounts used for abuse, automated
                        scraping, or attempts to circumvent usage limits.
                    </p>

                    <h2>Free Translations</h2>
                    <p>
                        Every account receives <strong>3 free translations</strong>, counted once per account for the
                        lifetime of that account. Once they are used, continued translation requires an active
                        subscription.
                    </p>

                    <h2>Braille Premium Subscription</h2>
                    <p>
                        Braille Premium is offered as an auto-renewable subscription. It unlocks unlimited
                        translations, removes advertising, and restores access to your full translation history.
                    </p>

                    <table class="plan-table">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Length</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Braille Premium Weekly</td>
                                <td>1 week</td>
                                <td>US$6.99</td>
                            </tr>
                            <tr>
                                <td>Braille Premium Yearly</td>
                                <td>1 year</td>
                                <td>US$49.99</td>
                            </tr>
                        </tbody>
                    </table>

                    <p>
                        Prices are shown in US dollars and may vary by territory. The exact price and billing period
                        that apply to you are always displayed on the purchase screen inside the App before you
                        confirm.
                    </p>
                    <ul>
                        <li>Payment is charged to your Apple ID or Google Play account at confirmation of purchase.</li>
                        <li>
                            The subscription <strong>renews automatically</strong> unless auto-renew is turned off at
                            least <strong>24 hours before the end of the current period</strong>.
                        </li>
                        <li>
                            Your account is charged for renewal within 24 hours prior to the end of the current
                            period, at the price of the plan you selected.
                        </li>
                        <li>
                            You can manage or cancel your subscription in your account settings after purchase — on
                            iOS: <em>Settings → your name → Subscriptions</em>; on Android:
                            <em>Google Play → Payments and subscriptions</em>. Deleting the App does not cancel a
                            subscription.
                        </li>
                        <li>
                            Where a free trial is offered, any unused portion of it is forfeited when a subscription
                            is purchased.
                        </li>
                    </ul>

                    <h2>Refunds</h2>
                    <p>
                        Purchases are processed by Apple or Google, not by us, and refunds are handled under the
                        respective store's policy. For the App Store, submit requests at
                        <a href="https://reportaproblem.apple.com" target="_blank"
                            rel="noopener noreferrer">reportaproblem.apple.com</a>.
                    </p>

                    <h2>Acceptable Use</h2>
                    <p>
                        You agree not to use the App to upload unlawful content, to infringe the rights of others, or
                        to interfere with the operation of the service. Images you submit are processed to produce a
                        translation and are handled as described in our
                        <a href="{{ route('privacy.policy') }}">Privacy Policy</a>.
                    </p>

                    <h2>Accuracy Disclaimer</h2>
                    <p>
                        Braille recognition is automated and may be inaccurate or incomplete. The App is provided "as
                        is", without warranties of any kind. Please do not rely on its output where an error could
                        cause harm — including medical, legal, financial, or safety-critical contexts — without
                        independent verification by a qualified person.
                    </p>

                    <h2>Limitation of Liability</h2>
                    <p>
                        To the maximum extent permitted by applicable law, AL-FOCUS TECH LLC is not liable for
                        indirect, incidental, or consequential damages arising from your use of the App. Nothing in
                        these Terms limits liability that cannot be limited by law.
                    </p>

                    <h2>Changes and Termination</h2>
                    <p>
                        We may update these Terms from time to time. Material changes will be reflected in the
                        effective date above, and continued use of the App after that date constitutes acceptance. We
                        may discontinue the service or terminate accounts that breach these Terms.
                    </p>

                    <h2>Privacy</h2>
                    <p>
                        Our handling of personal data is described in our
                        <a href="{{ route('privacy.policy') }}">Privacy Policy</a>.
                    </p>

                    <h2>Contact Us</h2>
                    <p>
                        If you have any questions about these Terms of Use, do not hesitate to contact us at
                        <a href="mailto:info@alfocus.uz">info@alfocus.uz</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
