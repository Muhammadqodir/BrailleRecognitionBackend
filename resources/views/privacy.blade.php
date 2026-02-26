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
                    This policy is effective as of February 6, 2026
                </div>

                <div class="privacy-content">
                    <p>
                        <strong>Braille Recognition</strong> is provided by <strong>AL-FOCUS TECH LLC</strong> at no cost
                        and is intended for use as is.
                        This page explains how we collect, use, and disclose information when you use the service.
                    </p>
                    <p>
                        If you choose to use our Service, then you agree to the collection and use of information in
                        relation to this
                        policy. The Personal Information that we collect is used for providing and improving the Service. We
                        will not use or share your information with
                        anyone except as described in this Privacy Policy.
                    </p>
                    <p>
                        The terms used in this Privacy Policy have the same meanings as in our Terms and Conditions, which
                        are accessible at
                        BrailleRecognition unless otherwise defined in this Privacy Policy.
                    </p>

                    <h2>Information Collection and Use</h2>
                    <p>
                        For a better experience, while using our Service, we may require you to provide us with certain
                        personally
                        identifiable information. The information that we request will be retained on your device and is not
                        collected by us in any way.
                    </p>
                    <p>
                        The app does use third-party services that may collect information used to identify you.
                    </p>
                    <p>
                        Links to the privacy policy of third-party service providers used by the app:
                    </p>
                    <ul>
                        <li><a href="https://www.google.com/policies/privacy/" target="_blank"
                                rel="noopener noreferrer">Google Play
                                Services</a></li>
                        <li><a href="https://firebase.google.com/policies/analytics" target="_blank"
                                rel="noopener noreferrer">Google
                                Analytics for Firebase</a></li>
                        <li><a href="https://firebase.google.com/support/privacy/" target="_blank"
                                rel="noopener noreferrer">Firebase
                                Crashlytics</a></li>
                    </ul>

                    <h2>Log Data</h2>
                    <p>
                        We want to inform you that whenever you use our Service, in a case of an error in the app
                        we collect data and information (through third-party products) on your phone called Log Data. This
                        Log Data may
                        include information such as your device Internet Protocol ("IP") address, device name, operating
                        system version, the
                        configuration of the app when utilizing our Service, the time and date of your use of the Service,
                        and other
                        statistics.
                    </p>

                    <h2>Cookies</h2>
                    <p>
                        Cookies are files with a small amount of data that are commonly used as anonymous unique
                        identifiers. These are sent
                        to your browser from the websites that you visit and are stored on your device's internal memory.
                    </p>
                    <p>
                        This Service does not use these "cookies" explicitly. However, the app may use third-party code and
                        libraries that use
                        "cookies" to collect information and improve their services. You have the option to either accept or
                        refuse these cookies
                        and know when a cookie is being sent to your device. If you choose to refuse our cookies, you may
                        not be able to use some
                        portions of this Service.
                    </p>

                    <h2>Service Providers</h2>
                    <p>
                        We may employ third-party companies and individuals due to the following reasons:
                    </p>
                    <ul>
                        <li>To facilitate our Service;</li>
                        <li>To provide the Service on our behalf;</li>
                        <li>To perform Service-related services; or</li>
                        <li>To assist us in analyzing how our Service is used.</li>
                    </ul>
                    <p>
                        We want to inform users of this Service that these third parties have access to their Personal
                        Information. The reason is to perform the tasks assigned to them on our behalf. However, they are
                        obligated not to
                        disclose or use the information for any other purpose.
                    </p>

                    <h2>Security</h2>
                    <p>
                        We value your trust in providing us your Personal Information, thus we are striving to use
                        commercially
                        acceptable means of protecting it. But remember that no method of transmission over the internet, or
                        method of electronic
                        storage is 100% secure and reliable, and we cannot guarantee its absolute security.
                    </p>

                    <h2>Links to Other Sites</h2>
                    <p>
                        This Service may contain links to other sites. If you click on a third-party link, you will be
                        directed to that site. Note
                        that these external sites are not operated by us. Therefore, we strongly advise you to review the
                        Privacy Policy of these websites. We have no control over and assume no responsibility for the
                        content,
                        privacy policies, or practices of any third-party sites or services.
                    </p>

                    <h2>Children's Privacy</h2>
                    <p>
                        These Services do not address anyone under the age of 13. We do not knowingly collect personally
                        identifiable information from children under 13 years of age. In the case we discover that a child
                        under 13 has provided
                        us with personal information, we immediately delete this from our servers. If you are a parent or
                        guardian
                        and you are aware that your child has provided us with personal information, please contact us so
                        that
                        we will be able to take the necessary actions.
                    </p>

                    <h2>Changes to This Privacy Policy</h2>
                    <p>
                        We may update our Privacy Policy from time to time. Thus, you are advised to review this page
                        periodically for any changes. We will notify you of any changes by posting the new Privacy Policy on
                        this page.
                    </p>

                    <h2>Contact Us</h2>
                    <p>
                        If you have any questions or suggestions about our Privacy Policy, do not hesitate to contact us at
                        <a href="mailto:info@alfocus.uz">info@alfocus.uz</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
