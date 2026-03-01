# Auth API Documentation

Base URL: `https://your-domain.com/api`

All responses are JSON. Protected routes require the header:
```
Authorization: Bearer {token}
```

---

## Endpoints

### POST `/api/auth/register`
Register with email & password.

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**Response 201:**
```json
{
  "message": "Registration successful.",
  "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
  "token": "1|abc123..."
}
```

---

### POST `/api/auth/login`
Login with email & password.

**Request:**
```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

**Response 200:**
```json
{
  "message": "Login successful.",
  "user": { ... },
  "token": "2|xyz789..."
}
```

---

### POST `/api/auth/google`
Sign in with Google (Flutter sends the Google ID token).

**Request:**
```json
{
  "id_token": "eyJhbGci..."
}
```

**Response 200:**
```json
{
  "message": "Google sign-in successful.",
  "user": { ... },
  "token": "3|..."
}
```

---

### POST `/api/auth/apple`
Sign in with Apple (Flutter sends Apple identity token).

**Request:**
```json
{
  "identity_token": "eyJhbGci...",
  "user_id": "001234.abc...",
  "name": "John",          // only on first sign-in, nullable after
  "email": "john@privaterelay.appleid.com"  // only on first sign-in
}
```

**Response 200:**
```json
{
  "message": "Apple sign-in successful.",
  "user": { ... },
  "token": "4|..."
}
```

---

### GET `/api/auth/me` *(protected)*
Returns the current user.

**Response 200:**
```json
{
  "user": { "id": 1, "name": "...", "email": "...", ... }
}
```

---

### POST `/api/auth/logout` *(protected)*
Revokes the current bearer token.

**Response 200:**
```json
{ "message": "Logged out successfully." }
```
