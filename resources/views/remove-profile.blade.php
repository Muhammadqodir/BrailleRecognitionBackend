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

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray--primary);
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            font-family: Inter, sans-serif;
            transition: border-color 0.3s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3FAFEA;
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .submit-button {
            width: 100%;
            padding: 14px 24px;
            background-color: #3FAFEA;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            font-family: Inter, sans-serif;
        }

        .submit-button:hover {
            background-color: #2a8cd6;
        }

        .submit-button:active {
            transform: scale(0.98);
        }
    </style>
@endsection

@section('content')

    <section class="section hero-section">
        <div class="container">
            <div data-w-id="653d6031-aa80-5f0c-561b-f7d572d05b1e" class="hero-wrapper"
                style="transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d; opacity: 1;">
                <img src="../images/logo_new.png" loading="lazy" width="102" alt="" class="hero-logo">
                <h1>Delete Your Profile</h1>
                <p class="hero-description" style="max-width: 600px;">
                    We're sorry to see you go. If you'd like to delete your BrailleRecognition account and all associated
                    data, please fill out the form below. We will process your request within 30 days.
                </p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container" style="max-width: 700px;">
            <div class="custom-card" style="padding: 40px; margin-bottom: 40px;">
                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-error">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error">
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!session('success'))
                    <form method="POST" action="{{ route('profile.deletion.submit') }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="email" style="color: #444">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input" required
                                placeholder="Enter your registered email" value="{{ old('email') }}">
                            <small style="color: #666; font-size: 14px; display: block; margin-top: 6px;">
                                Please provide the email address you used to register with BrailleRecognition.
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="reason" style="color: #444">Reason for Deletion
                                (Optional)</label>
                            <select id="reason" name="reason" class="form-select">
                                <option value="">Select a reason</option>
                                <option value="no_longer_needed"
                                    {{ old('reason') == 'no_longer_needed' ? 'selected' : '' }}>No longer need the app
                                </option>
                                <option value="privacy_concerns"
                                    {{ old('reason') == 'privacy_concerns' ? 'selected' : '' }}>Privacy concerns</option>
                                <option value="switching_apps" {{ old('reason') == 'switching_apps' ? 'selected' : '' }}>
                                    Switching to another app</option>
                                <option value="technical_issues"
                                    {{ old('reason') == 'technical_issues' ? 'selected' : '' }}>Technical issues</option>
                                <option value="other" {{ old('reason') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="additional_info" style="color: #444">Additional Information
                                (Optional)</label>
                            <textarea id="additional_info" name="additional_info" class="form-textarea"
                                placeholder="Please share any feedback or additional details...">{{ old('additional_info') }}</textarea>
                        </div>

                        <div
                            style="margin-bottom: 24px; padding: 16px; background-color: #fff3cd; border-radius: 12px; border: 1px solid #ffc107;">
                            <strong style="color: #856404;">Important Notice:</strong>
                            <ul style="margin: 8px 0 0 20px; color: #856404; font-size: 14px;">
                                <li>Deleting your account will permanently remove all your data</li>
                                <li>This action cannot be undone</li>
                                <li>We will process your request within 30 days</li>
                                <li>You will receive a confirmation email once completed</li>
                            </ul>
                        </div>

                        <button type="submit" class="submit-button">Submit Deletion Request</button>
                    </form>
                @else
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="https://braillerecognition.alfocus.uz/"
                            style="text-decoration: none; color: #3FAFEA; font-weight: 600;">
                            ← Back to Home
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
