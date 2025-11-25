# API Documentation

## 📚 OpenAPI/Swagger Documentation

This API uses **Nelmio API Doc Bundle** to automatically generate OpenAPI (Swagger) documentation.

### 🌐 Access the Documentation

#### Swagger UI (Interactive)
Visit: **http://localhost/api/doc**

This provides an interactive interface where you can:
- View all API endpoints
- See request/response schemas
- Try out API calls directly from the browser
- View error codes and examples

#### Raw OpenAPI JSON
Visit: **http://localhost/api/doc.json**

This is the raw OpenAPI specification in JSON format.

---

## 🔐 Authentication

All protected endpoints require a **Bearer JWT token** in the `Authorization` header:

```
Authorization: Bearer <your_jwt_token>
```

### How to get a JWT token:

1. **Login** to get a token:
   ```bash
   curl -X POST http://localhost/api/user/login \
     -H "Content-Type: application/json" \
     -d '{"uuid":"user@example.com","password":"password123"}'
   ```

2. **Use the token** in subsequent requests:
   ```bash
   curl -X GET http://localhost/api/user/me \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
   ```

---

## 📋 API Endpoints

### User Management

#### 1. **Register User** (Admin Only)
- **Endpoint**: `POST /api/user/register`
- **Auth**: Requires `ROLE_ADMIN`
- **Body**:
  ```json
  {
    "uuid": "john@example.com",
    "password": "securepass123",
    "roles": ["ROLE_USER"]
  }
  ```
- **Response** (201):
  ```json
  {
    "message": "Utilisateur créé avec succès",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expiresIn": 3600
  }
  ```

#### 2. **Login**
- **Endpoint**: `POST /api/user/login`
- **Auth**: None (public)
- **Body**:
  ```json
  {
    "uuid": "john@example.com",
    "password": "securepass123"
  }
  ```
- **Response** (200):
  ```json
  {
    "message": "Connexion réussie",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expiresIn": 3600
  }
  ```

#### 3. **Get Current User**
- **Endpoint**: `GET /api/user/me`
- **Auth**: Requires valid JWT (`ROLE_USER`)
- **Response** (200):
  ```json
  {
    "id": 1,
    "uuid": "john@example.com",
    "roles": ["ROLE_USER", "ROLE_ADMIN"]
  }
  ```

#### 4. **Get User by ID**
- **Endpoint**: `GET /api/user/{userId}`
- **Auth**: Requires `ROLE_ADMIN`
- **Response** (200):
  ```json
  {
    "id": 1,
    "uuid": "john@example.com",
    "roles": ["ROLE_USER", "ROLE_ADMIN"]
  }
  ```

---

## 📝 HTTP Status Codes

| Code | Meaning |
|------|---------|
| **200** | OK - Request successful |
| **201** | Created - Resource created successfully |
| **400** | Bad Request - Invalid data |
| **401** | Unauthorized - Missing or invalid JWT |
| **403** | Forbidden - Insufficient permissions (e.g., missing ROLE_ADMIN) |
| **404** | Not Found - Resource not found |
| **409** | Conflict - Resource already exists (e.g., duplicate UUID) |
| **500** | Server Error |

---

## 🔑 JWT Token Claims

The JWT tokens contain the following claims (data):

```json
{
  "iat": 1700000000,           // Issued at (Unix timestamp)
  "exp": 1700003600,           // Expiration (Unix timestamp)
  "uuid": "john@example.com",  // User identifier
  "id": 1,                     // Database ID
  "roles": ["ROLE_USER"]       // User roles
}
```

**Token lifetime**: 3600 seconds (1 hour)
**Algorithm**: HS256 (HMAC with SHA-256)
**Secret**: Stored in `.env` as `APP_SECRET`

---

## 🧪 Testing with cURL

### Create a user (as admin)
```bash
curl -X POST http://localhost/api/user/register \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <admin_token>" \
  -d '{
    "uuid": "newuser@example.com",
    "password": "password123"
  }'
```

### Login
```bash
curl -X POST http://localhost/api/user/login \
  -H "Content-Type: application/json" \
  -d '{
    "uuid": "newuser@example.com",
    "password": "password123"
  }'
```

### Access protected endpoint
```bash
curl -X GET http://localhost/api/user/me \
  -H "Authorization: Bearer <your_token>"
```

---

## 📚 Additional Resources

- **Nelmio API Doc**: https://github.com/nelmio/NelmioApiDocBundle
- **OpenAPI Specification**: https://spec.openapis.org/
- **JWT Introduction**: https://jwt.io/introduction
- **Symfony Security**: https://symfony.com/doc/current/security.html

---

## 🛠️ Development Notes

- OpenAPI documentation is **auto-generated** from PHP attributes
- Documentation stays **in sync** with your code
- Modify annotations in `src/Controller/UserController.php` to update docs
- No manual documentation maintenance needed

