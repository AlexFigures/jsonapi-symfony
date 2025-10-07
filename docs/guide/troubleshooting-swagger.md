# Troubleshooting Swagger UI & OpenAPI

Common issues and solutions for the documentation features.

---

## 406 Not Acceptable Error

### Problem

When accessing `http://localhost:8000/_jsonapi/docs`, you get:

```json
{
  "errors": [{
    "status": "406",
    "code": "not-acceptable",
    "title": "Not acceptable",
    "detail": "The Accept header \"text/html,...\" does not allow application/vnd.api+json."
  }]
}
```

### Root Cause

The `ContentNegotiationSubscriber` was checking **all** requests, including documentation routes, for the JSON:API `Accept` header. Browsers send `Accept: text/html`, which doesn't include `application/vnd.api+json`.

### Solution

**This issue is fixed in version 0.1.x and later.**

The documentation routes are now excluded from JSON:API content negotiation:
- `/_jsonapi/docs` (Swagger UI)
- `/_jsonapi/openapi.json` (OpenAPI spec)

If you're still experiencing this issue:

1. **Update to the latest version**:
   ```bash
   composer update jsonapi/symfony-jsonapi-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify routes are registered**:
   ```bash
   php bin/console debug:router | grep jsonapi.docs
   ```
   
   You should see:
   ```
   jsonapi.docs.ui       GET    /_jsonapi/docs
   jsonapi.docs.openapi  GET    /_jsonapi/openapi.json
   ```

### How It Works

The `ContentNegotiationSubscriber` now checks if a request is for a documentation route before enforcing JSON:API content negotiation:

```php
private function isDocumentationRoute(Request $request): bool
{
    $route = $request->attributes->get('_route');
    
    // Check by route name
    if ($route !== null && str_starts_with((string) $route, 'jsonapi.docs.')) {
        return true;
    }

    // Fallback: check by path pattern
    $path = $request->getPathInfo();
    return str_starts_with($path, '/_jsonapi/docs') 
        || str_starts_with($path, '/_jsonapi/openapi');
}
```

---

## 404 Not Found

### Problem

Documentation routes return 404 Not Found.

### Solutions

1. **Check if documentation is enabled**:
   ```yaml
   # config/packages/jsonapi.yaml
   jsonapi:
       docs:
           generator:
               openapi:
                   enabled: true
           ui:
               enabled: true
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify routes**:
   ```bash
   php bin/console debug:router | grep jsonapi
   ```

4. **Check route prefix**:
   If you've customized the route, make sure you're using the correct URL:
   ```yaml
   jsonapi:
       docs:
           ui:
               route: '/custom/docs'  # Use http://localhost:8000/custom/docs
   ```

---

## Swagger UI Shows "Failed to load API definition"

### Problem

Swagger UI loads but shows an error: "Failed to load API definition"

### Solutions

1. **Check OpenAPI spec is accessible**:
   ```bash
   curl http://localhost:8000/_jsonapi/openapi.json
   ```
   
   Should return valid JSON starting with:
   ```json
   {
     "openapi": "3.1.0",
     "info": {
       "title": "My API",
       "version": "1.0.0"
     },
     ...
   }
   ```

2. **Verify spec_url configuration**:
   ```yaml
   jsonapi:
       docs:
           ui:
               spec_url: '/_jsonapi/openapi.json'  # Must be accessible
   ```

3. **Check CORS if spec is on different domain**:
   If your spec is hosted on a different domain, you need CORS headers:
   ```yaml
   # config/packages/nelmio_cors.yaml
   nelmio_cors:
       paths:
           '^/_jsonapi/openapi.json':
               allow_origin: ['*']
               allow_methods: ['GET']
   ```

4. **Check browser console**:
   Open browser DevTools (F12) and check the Console tab for errors.

---

## Invalid OpenAPI Specification

### Problem

OpenAPI validators report errors in the generated specification.

### Solutions

1. **Validate the spec**:
   ```bash
   # Using Redocly CLI
   npm install -g @redocly/cli
   redocly lint http://localhost:8000/_jsonapi/openapi.json
   
   # Using Spectral
   npm install -g @stoplight/spectral-cli
   spectral lint http://localhost:8000/_jsonapi/openapi.json
   ```

2. **Check resource metadata**:
   Make sure your resources have proper type definitions:
   ```php
   #[JsonApiResource(type: 'articles')]  // Type is required
   class Article {
       #[Id]
       public string $id;  // ID type should be defined
       
       #[Attribute]
       public string $title;  // Attribute types should be defined
   }
   ```

3. **Report an issue**:
   If the spec is invalid, please [open an issue](https://github.com/AlexFigures/jsonapi-symfony/issues) with:
   - The validation error
   - Your resource definitions
   - The generated OpenAPI spec

---

## Swagger UI Not Showing All Endpoints

### Problem

Some endpoints are missing from Swagger UI.

### Solutions

1. **Check resource registration**:
   ```bash
   php bin/console debug:container --tag=jsonapi.resource
   ```

2. **Verify resource paths**:
   ```yaml
   jsonapi:
       resource_paths:
           - '%kernel.project_dir%/src/Entity'
           - '%kernel.project_dir%/src/Model'  # Add custom paths
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

4. **Check resource attributes**:
   Make sure resources have the `#[JsonApiResource]` attribute:
   ```php
   #[JsonApiResource(type: 'articles')]
   class Article { ... }
   ```

---

## Redoc Theme Not Working

### Problem

Switching to Redoc theme doesn't work.

### Solution

Make sure the theme is set correctly:

```yaml
jsonapi:
    docs:
        ui:
            theme: 'redoc'  # Not 'Redoc' or 'REDOC'
```

Clear cache:
```bash
php bin/console cache:clear
```

---

## Documentation Shows in Production

### Problem

Documentation is accessible in production environment.

### Solution

Disable documentation in production:

```yaml
# config/packages/prod/jsonapi.yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: false
        ui:
            enabled: false
```

Or restrict access:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_jsonapi/docs, roles: ROLE_ADMIN }
        - { path: ^/_jsonapi/openapi.json, roles: ROLE_ADMIN }
```

---

## Custom Server URLs Not Showing

### Problem

Custom server URLs are not appearing in Swagger UI.

### Solution

Configure servers in the OpenAPI generator:

```yaml
jsonapi:
    docs:
        generator:
            openapi:
                servers:
                    - 'https://api.example.com'
                    - 'https://staging-api.example.com'
                    - 'http://localhost:8000'
```

Clear cache:
```bash
php bin/console cache:clear
```

---

## Getting Help

If you're still experiencing issues:

1. **Check the logs**:
   ```bash
   tail -f var/log/dev.log
   ```

2. **Enable debug mode**:
   ```yaml
   # config/packages/dev/jsonapi.yaml
   jsonapi:
       errors:
           expose_debug_meta: true
   ```

3. **Search existing issues**:
   https://github.com/AlexFigures/jsonapi-symfony/issues

4. **Open a new issue**:
   Include:
   - Bundle version
   - Symfony version
   - PHP version
   - Configuration
   - Error messages
   - Steps to reproduce

---

## See Also

- [Swagger UI Configuration](swagger-ui.md)
- [Configuration Reference](configuration.md)
- [General Troubleshooting](troubleshooting.md)

