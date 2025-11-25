# API Symfony avec Authentification JWT

## 📚 Vue d'ensemble de l'architecture d'authentification

Ce projet implémente une **API RESTful sécurisée** utilisant les **tokens JWT (JSON Web Tokens)** pour l'authentification.

### Pourquoi JWT?
- ✅ Stateless: pas besoin de session côté serveur
- ✅ Sécurisé: tokens signés cryptographiquement
- ✅ Scalable: fonctionne bien avec les microservices
- ✅ Mobile-friendly: parfait pour les apps mobiles
- ✅ Compatible API: retour JSON au lieu de redirection

---

## 🔐 Flux d'authentification

```
1. Client envoie uuid + password au /api/user/login
   ↓
2. Serveur vérifie le password et génère un JWT
   ↓
3. Client reçoit le JWT et le stocke localement
   ↓
4. Client envoie le JWT dans header Authorization: Bearer <token>
   ↓
5. TokenAuthenticator valide le JWT et charge l'utilisateur
   ↓
6. Utilisateur authentifié accède aux ressources protégées
```

---

## 📁 Structure des fichiers importants

### 1. `src/Security/TokenAuthenticator.php`
**Responsabilité**: Valider les JWT et authentifier les utilisateurs

**Méthodes clés**:
- `supports()` - Décide si ce contrôleur doit s'exécuter (cherche Authorization header)
- `authenticate()` - Décode le JWT, charge l'utilisateur depuis la DB
- `start()` - Retourne une erreur JSON si authentification requise

**Flux**:
```
Request avec Authorization: Bearer eyJhbGc... → supports() → authenticate() → User authentifié
Request sans Authorization → supports() retourne false → start() → Erreur 401 JSON
```

### 2. `src/Service/JwtService.php`
**Responsabilité**: Générer et persister les JWT

**Ce qu'il fait**:
1. Prend un User
2. Crée un payload avec: iat (création), exp (expiration), uuid, id, roles
3. Encode le payload avec HS256 et la clé APP_SECRET
4. Sauvegarde le token dans la base de données (colonne jwtToken)
5. Retourne le token au client

**Format du payload**:
```json
{
  "iat": 1764027459,
  "exp": 1764031059,
  "uuid": "testuser",
  "id": 3,
  "roles": ["ROLE_USER"]
}
```

### 3. `src/Controller/UserController.php`
**Responsabilité**: Gérer les endpoints d'authentification et d'utilisateurs

**Endpoints**:

| Endpoint | Méthode | Authentification | Description |
|----------|---------|------------------|-------------|
| /api/user/register | POST | ROLE_ADMIN | Créer un nouvel utilisateur |
| /api/user/login | POST | Public | S'authentifier, recevoir JWT |
| /api/user/me | GET | ROLE_USER | Infos de l'utilisateur connecté |
| /api/user/{userId} | GET | ROLE_ADMIN | Infos d'un utilisateur spécifique |

### 4. `config/packages/security.yaml`
**Responsabilité**: Configuration Symfony de sécurité

**Concepts clés**:
- **password_hashers**: Configure bcrypt pour hasher les mots de passe
- **providers**: Dit à Symfony comment charger les users (par uuid ici)
- **firewalls**: Définit les règles de protection
  - `dev`: Désactive sécurité pour les outils dev
  - `main`: Firewall principal
    - `stateless: true`: Pas de sessions (important pour JWT!)
    - `custom_authenticator`: Utilise notre TokenAuthenticator
    - `entry_point`: Point d'entrée pour ressources protégées non authentifiées

---

## 🔒 Sécurité: Hasher les mots de passe

**Jamais stocker un mot de passe en clair!** Nous utilisons bcrypt:

```php
// En stockant:
$hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
$user->setPassword($hashedPassword);

// En vérifiant:
if ($this->passwordHasher->isPasswordValid($user, $data['password'])) {
    // Correct!
}
```

bcrypt = algorithme unidirectionnel secure:
- On ne peut pas retrouver le password depuis le hash
- Même hash identique de 2 fois différent (salt aléatoire)
- Résistant aux attaques par force brute

---

## 📝 Exemple d'utilisation complet

### 1. Créer un utilisateur (admin seulement)
```bash
curl -X POST "http://localhost/site_symfony/public/api/user/register" \
  -H "Authorization: Bearer <admin_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "uuid": "john_doe",
    "password": "super_secret_password",
    "roles": ["ROLE_USER"]
  }'
```

Réponse (201 Created):
```json
{
  "message": "Utilisateur créé avec succès",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expiresIn": 3600
}
```

### 2. Se connecter
```bash
curl -X POST "http://localhost/site_symfony/public/api/user/login" \
  -H "Content-Type: application/json" \
  -d '{
    "uuid": "john_doe",
    "password": "super_secret_password"
  }'
```

Réponse (200 OK):
```json
{
  "message": "Connexion réussie",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expiresIn": 3600
}
```

### 3. Utiliser le token pour accéder aux ressources protégées
```bash
curl -X GET "http://localhost/site_symfony/public/api/user/me" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

Réponse (200 OK):
```json
{
  "id": 3,
  "uuid": "john_doe",
  "roles": ["ROLE_USER"]
}
```

### 4. Sans token ou token invalide
```bash
curl -X GET "http://localhost/site_symfony/public/api/user/me"
```

Réponse (401 Unauthorized):
```json
{
  "error": "Authentication required"
}
```

---

## 🔑 Concepts importants pour vos élèves

### #1: Claims
Les **claims** sont les données stockées dans le token:
- `iat` (issued at): timestamp de création
- `exp` (expiration): timestamp d'expiration
- `uuid`, `id`, `roles`: données personnalisées

### #2: Signature
Le token JWT est signé avec HS256:
- Utilise la clé secrète (APP_SECRET)
- Si quelqu'un modifie le payload, la signature devient invalide
- Personne ne peut falsifier un token sans connaître la clé secrète

### #3: Expiration
Un token expire après 3600 secondes (1 heure):
- Après expiration, le client doit se reconnecter pour obtenir un nouveau token
- C'est une mesure de sécurité si un token est volé

### #4: Stateless
L'API ne stocke PAS de sessions:
- Chaque requête est indépendante
- Le token contient toutes les infos nécessaires
- Scalable: pas besoin de synchroniser sessions entre serveurs

### #5: Bearer Token
Format d'envoi du token:
```
Authorization: Bearer <token>
```
- `Bearer` = type d'authentification
- `<token>` = le JWT complet

---

## 🚀 Points clés du code

### Validation du JWT dans TokenAuthenticator
```php
// 1. Extraire le token du header
$matches = preg_match('/Bearer\s+(.+)/i', $authHeader, $matches);
$token = $matches[1];

// 2. Décoder et vérifier la signature
$decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
// Si la signature est mauvaise ou le token expiré: exception!

// 3. Charger l'utilisateur de la DB
$user = $this->userRepository->findOneBy(['uuid' => $decoded->uuid]);

// 4. Retourner un Passport avec l'utilisateur authentifié
return new SelfValidatingPassport(new UserBadge($decoded->uuid));
```

### Annotation de sécurité
```php
#[IsGranted('ROLE_USER')]
public function getCurrentUser(): JsonResponse
{
    // Cette méthode ne s'exécute que si l'utilisateur a ROLE_USER
    // Sinon: Symfony appelle automatiquement le entry_point (retourne 401)
}
```

---

## 📚 Pour aller plus loin

- **Rafraîchir un token**: Implémenter un endpoint /token/refresh
- **Blacklister un token**: Si l'utilisateur se déconnecte
- **Multitenancy**: Ajouter une colonne tenant_id
- **Permissions granulaires**: Plus que des rôles (ex: can_edit_post)
- **OAuth2**: Utiliser des providers externes (Google, GitHub)

---

## ✅ Checklist pour les élèves

- [ ] Comprendre ce qu'est un JWT
- [ ] Pouvoir expliquer le flux login → token → authenticated request
- [ ] Savoir pourquoi stateless est important
- [ ] Comprendre la différence entre ROLE_USER et ROLE_ADMIN
- [ ] Pouvoir encoder/décoder manuellement un JWT
- [ ] Tester les endpoints avec curl ou Postman
- [ ] Modifier les claims dans le JWT et voir pourquoi c'est rejeté
