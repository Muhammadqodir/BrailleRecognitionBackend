# Flutter — Google & Apple Sign-In Integration Guide

---

## 1. Required Packages

Add to your Flutter app's `pubspec.yaml`:

```yaml
dependencies:
  google_sign_in: ^6.2.1
  sign_in_with_apple: ^6.1.0
  flutter_secure_storage: ^9.0.0 # to store the bearer token
```

Run:
```bash
flutter pub get
```

---

## 2. Android — Google Sign-In Setup

### a) Get SHA-1 fingerprint
```bash
cd android
./gradlew signingReport
```

### b) Firebase / Google Cloud Console
1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Create / select your project → **APIs & Services → Credentials**
3. Create an **OAuth 2.0 Client ID** → Android
4. Package name: your `applicationId` from `android/app/build.gradle`
5. SHA-1: paste from step a
6. Also create a **Web application** client ID — this is the `serverClientId` you need below
7. Copy the **Web client ID** (used as `serverClientId` in Flutter and `GOOGLE_CLIENT_ID` in `.env`)

### c) `android/app/google-services.json`
Download from Firebase Console and place it in `android/app/`.

### d) `android/build.gradle`
```groovy
buildscript {
    dependencies {
        classpath 'com.google.gms:google-services:4.4.1'
    }
}
```

### e) `android/app/build.gradle`
```groovy
apply plugin: 'com.google.gms.google-services'
```

---

## 3. iOS — Google Sign-In Setup

### a) `ios/Runner/Info.plist`
Add the reversed client ID (from GoogleService-Info.plist):
```xml
<key>CFBundleURLTypes</key>
<array>
  <dict>
    <key>CFBundleURLSchemes</key>
    <array>
      <string>com.googleusercontent.apps.YOUR_CLIENT_ID</string>
    </array>
  </dict>
</array>
```

### b) Download `GoogleService-Info.plist` from Firebase and add it to `ios/Runner/`.

---

## 4. iOS — Apple Sign-In Setup

### a) Apple Developer Console
1. Go to [developer.apple.com](https://developer.apple.com) → Certificates, IDs & Profiles
2. Select your **App ID** → Enable **Sign In with Apple**
3. Edit the capability and save

### b) Xcode
- Open `ios/Runner.xcworkspace`
- Select Runner target → **Signing & Capabilities** → **+ Capability** → **Sign In with Apple**

### c) `ios/Runner/Info.plist` — no extra changes needed for sign_in_with_apple

---

## 5. Flutter Code

### `lib/services/auth_service.dart`

```dart
import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

class AuthService {
  static const _baseUrl = 'https://your-domain.com/api';
  static const _storage = FlutterSecureStorage();

  static final _googleSignIn = GoogleSignIn(
    // Use the WEB client ID (not Android/iOS client ID) so Google returns an id_token
    serverClientId: 'YOUR_WEB_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
  );

  // -------------------------------------------------------------------
  // Token helpers
  // -------------------------------------------------------------------
  static Future<void> saveToken(String token) =>
      _storage.write(key: 'auth_token', value: token);

  static Future<String?> getToken() => _storage.read(key: 'auth_token');

  static Future<void> clearToken() => _storage.delete(key: 'auth_token');

  // -------------------------------------------------------------------
  // Register
  // -------------------------------------------------------------------
  static Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
  }) async {
    final res = await http.post(
      Uri.parse('$_baseUrl/auth/register'),
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: jsonEncode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      }),
    );
    final data = jsonDecode(res.body);
    if (res.statusCode == 201) {
      await saveToken(data['token']);
    }
    return data;
  }

  // -------------------------------------------------------------------
  // Login
  // -------------------------------------------------------------------
  static Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final res = await http.post(
      Uri.parse('$_baseUrl/auth/login'),
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: jsonEncode({'email': email, 'password': password}),
    );
    final data = jsonDecode(res.body);
    if (res.statusCode == 200) {
      await saveToken(data['token']);
    }
    return data;
  }

  // -------------------------------------------------------------------
  // Google Sign-In
  // -------------------------------------------------------------------
  static Future<Map<String, dynamic>> signInWithGoogle() async {
    final googleUser = await _googleSignIn.signIn();
    if (googleUser == null) throw Exception('Google sign-in cancelled.');

    final googleAuth = await googleUser.authentication;
    final idToken = googleAuth.idToken;
    if (idToken == null) throw Exception('Failed to get Google ID token.');

    final res = await http.post(
      Uri.parse('$_baseUrl/auth/google'),
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: jsonEncode({'id_token': idToken}),
    );
    final data = jsonDecode(res.body);
    if (res.statusCode == 200) {
      await saveToken(data['token']);
    }
    return data;
  }

  // -------------------------------------------------------------------
  // Apple Sign-In
  // -------------------------------------------------------------------
  static Future<Map<String, dynamic>> signInWithApple() async {
    final appleCredential = await SignInWithApple.getAppleIDCredential(
      scopes: [
        AppleIDAuthorizationScopes.email,
        AppleIDAuthorizationScopes.fullName,
      ],
    );

    final identityToken = appleCredential.identityToken;
    if (identityToken == null) throw Exception('Failed to get Apple identity token.');

    final fullName = [
      appleCredential.givenName,
      appleCredential.familyName,
    ].where((s) => s != null && s.isNotEmpty).join(' ');

    final res = await http.post(
      Uri.parse('$_baseUrl/auth/apple'),
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: jsonEncode({
        'identity_token': identityToken,
        'user_id': appleCredential.userIdentifier,
        'name': fullName.isEmpty ? null : fullName,
        'email': appleCredential.email,
      }),
    );
    final data = jsonDecode(res.body);
    if (res.statusCode == 200) {
      await saveToken(data['token']);
    }
    return data;
  }

  // -------------------------------------------------------------------
  // Logout
  // -------------------------------------------------------------------
  static Future<void> logout() async {
    final token = await getToken();
    if (token != null) {
      await http.post(
        Uri.parse('$_baseUrl/auth/logout'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );
    }
    await clearToken();
    await _googleSignIn.signOut();
  }

  // -------------------------------------------------------------------
  // Get current user
  // -------------------------------------------------------------------
  static Future<Map<String, dynamic>> me() async {
    final token = await getToken();
    final res = await http.get(
      Uri.parse('$_baseUrl/auth/me'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );
    return jsonDecode(res.body);
  }
}
```

---

## 6. Example Usage in a Widget

```dart
// Google
ElevatedButton(
  onPressed: () async {
    try {
      final data = await AuthService.signInWithGoogle();
      // data['token'] and data['user'] are available
      Navigator.pushReplacementNamed(context, '/home');
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    }
  },
  child: const Text('Sign in with Google'),
),

// Apple (show only on iOS)
if (Theme.of(context).platform == TargetPlatform.iOS)
  SignInWithAppleButton(
    onPressed: () async {
      try {
        final data = await AuthService.signInWithApple();
        Navigator.pushReplacementNamed(context, '/home');
      } catch (e) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    },
  ),
```

---

## 7. Backend `.env` Keys to Fill In

| Key | Where to get it |
|-----|-----------------|
| `GOOGLE_CLIENT_ID` | Google Cloud Console → OAuth 2.0 Web Client ID |
| `GOOGLE_CLIENT_SECRET` | Same credential |
| `APPLE_CLIENT_ID` | Apple Developer → Services ID (e.g. `com.yourapp.signin`) |
| `APPLE_CLIENT_SECRET` | JWT you generate with your Apple private key (p8 file) |

---

## 8. Important Notes

- **Apple Sign-In is mandatory on iOS** when any third-party social login is offered (App Store guideline 4.8).
- Apple only sends `email` and `name` on the **first** sign-in. The backend handles this — store them on first use.
- For production, replace the simple JWT decode in `verifyAppleToken()` with proper RS256 signature verification using Apple's public keys (`https://appleid.apple.com/auth/keys`). Use `firebase/php-jwt` or `lcobucci/jwt` package.
- The Google token verification via `tokeninfo` endpoint is fine for development; for production consider using `google/apiclient` to verify locally.
