# Swagger UI & OpenAPI Documentation

The bundle provides automatic OpenAPI 3.1 specification generation and interactive documentation through Swagger UI or Redoc.

---

## Quick Start

### 1. Enable Documentation

By default, documentation is enabled. You can customize it in your configuration:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: true
                title: 'My API'
                version: '1.0.0'
                servers:
                    - 'https://api.example.com'
                    - 'http://localhost:8000'
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            spec_url: '/_jsonapi/openapi.json'
            theme: 'swagger'  # or 'redoc'
```

### 2. Access Documentation

Navigate to:
- **Swagger UI**: `http://localhost:8000/_jsonapi/docs`
- **OpenAPI Spec**: `http://localhost:8000/_jsonapi/openapi.json`

---

## Configuration Options

### OpenAPI Generator

```yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: true                    # Enable/disable OpenAPI generation
                route: '/_jsonapi/openapi.json'  # Route for OpenAPI spec
                title: 'My API'                  # API title
                version: '1.0.0'                 # API version
                servers:                         # List of server URLs
                    - 'https://api.example.com'
                    - 'https://staging.example.com'
```

### UI Configuration

```yaml
jsonapi:
    docs:
        ui:
            enabled: true                        # Enable/disable UI
            route: '/_jsonapi/docs'              # Route for documentation UI
            spec_url: '/_jsonapi/openapi.json'   # URL to OpenAPI spec
            theme: 'swagger'                     # 'swagger' or 'redoc'
```

---

## Themes

### Swagger UI (Default)

Interactive API documentation with "Try it out" functionality:

```yaml
jsonapi:
    docs:
        ui:
            theme: 'swagger'
```

Features:
- ✅ Interactive request testing
- ✅ Request/response examples
- ✅ Schema visualization
- ✅ Authentication support
- ✅ Download OpenAPI spec

### Redoc

Clean, three-panel documentation:

```yaml
jsonapi:
    docs:
        ui:
            theme: 'redoc'
```

Features:
- ✅ Clean, modern design
- ✅ Three-panel layout
- ✅ Search functionality
- ✅ Code samples
- ✅ Responsive design

---

## OpenAPI Specification

The bundle generates a fully compliant OpenAPI 3.1 specification that includes:

### Resource Endpoints

For each resource, the following endpoints are documented:

```
GET    /api/{type}           - List resources
POST   /api/{type}           - Create resource
GET    /api/{type}/{id}      - Fetch resource
PATCH  /api/{type}/{id}      - Update resource
DELETE /api/{type}/{id}      - Delete resource
```

### Relationship Endpoints

```
GET    /api/{type}/{id}/relationships/{rel}  - Fetch relationship
PATCH  /api/{type}/{id}/relationships/{rel}  - Update relationship
POST   /api/{type}/{id}/relationships/{rel}  - Add to relationship (to-many)
DELETE /api/{type}/{id}/relationships/{rel}  - Remove from relationship (to-many)
```

### Related Resource Endpoints

```
GET    /api/{type}/{id}/{rel}  - Fetch related resources
```

### Schemas

The specification includes JSON:API compliant schemas:

- **Resource Objects**: Type, ID, attributes, relationships
- **Resource Identifiers**: Type and ID
- **Document Structures**: Top-level document with data, included, meta, links
- **Relationship Objects**: Data, links, meta
- **Error Objects**: JSON:API error format

---

## Content Negotiation

The documentation routes (`/_jsonapi/docs` and `/_jsonapi/openapi.json`) are **excluded** from JSON:API content negotiation.

This means:
- ✅ You can access them with a browser (sends `Accept: text/html`)
- ✅ No need to set `Accept: application/vnd.api+json` header
- ✅ OpenAPI spec returns `application/vnd.oai.openapi+json`
- ✅ Swagger UI returns `text/html`

For all other API endpoints, JSON:API content negotiation still applies.

---

## Production Considerations

### Disable in Production

It's recommended to disable documentation in production:

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

### Security

If you need documentation in production:

1. **Restrict Access**: Use Symfony security to limit access

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_jsonapi/docs, roles: ROLE_ADMIN }
        - { path: ^/_jsonapi/openapi.json, roles: ROLE_ADMIN }
```

2. **Use Firewall**: Configure your firewall to block external access

3. **Custom Route**: Use a non-obvious route name

```yaml
jsonapi:
    docs:
        ui:
            route: '/internal/api-docs'
```

---

## Customization

### Custom Servers

Add multiple server URLs for different environments:

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

### Custom Spec URL

If you host the OpenAPI spec externally:

```yaml
jsonapi:
    docs:
        ui:
            spec_url: 'https://cdn.example.com/openapi.json'
```

---

## Validation

### Validate OpenAPI Spec

You can validate the generated specification using online tools:

1. **Swagger Editor**: https://editor.swagger.io/
   - Copy the spec from `/_jsonapi/openapi.json`
   - Paste into the editor
   - Check for validation errors

2. **OpenAPI CLI**:
   ```bash
   npm install -g @redocly/cli
   redocly lint http://localhost:8000/_jsonapi/openapi.json
   ```

3. **Spectral**:
   ```bash
   npm install -g @stoplight/spectral-cli
   spectral lint http://localhost:8000/_jsonapi/openapi.json
   ```

---

## Troubleshooting

### 406 Not Acceptable Error

**Problem**: Accessing `/_jsonapi/docs` returns a 406 error.

**Solution**: Make sure you're using the latest version of the bundle. The documentation routes should be excluded from content negotiation.

If the issue persists, check your configuration:

```yaml
jsonapi:
    strict_content_negotiation: true  # This is fine
    docs:
        ui:
            enabled: true  # Make sure this is true
```

### 404 Not Found

**Problem**: Documentation routes return 404.

**Solution**: 
1. Clear cache: `php bin/console cache:clear`
2. Check routes: `php bin/console debug:router | grep jsonapi.docs`
3. Ensure documentation is enabled in config

### Spec Not Loading in Swagger UI

**Problem**: Swagger UI shows "Failed to load API definition"

**Solution**:
1. Check that `spec_url` is accessible
2. Verify CORS settings if spec is on different domain
3. Check browser console for errors
4. Ensure OpenAPI spec is valid JSON

---

## Examples

### Complete Configuration

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    route_prefix: '/api'
    
    docs:
        generator:
            openapi:
                enabled: true
                route: '/_jsonapi/openapi.json'
                title: 'My JSON:API'
                version: '1.0.0'
                servers:
                    - 'https://api.example.com'
                    - 'http://localhost:8000'
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            spec_url: '/_jsonapi/openapi.json'
            theme: 'swagger'
```

### Development vs Production

```yaml
# config/packages/dev/jsonapi.yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: true
                servers:
                    - 'http://localhost:8000'
        ui:
            enabled: true
```

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

---

## See Also

- [Configuration Guide](configuration.md)
- [Getting Started](getting-started.md)
- [OpenAPI 3.1 Specification](https://spec.openapis.org/oas/v3.1.0.html)
- [JSON:API Specification](https://jsonapi.org/format/1.1/)

