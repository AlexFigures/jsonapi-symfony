# Swagger UI / Redoc Integration

**Status**: ✅ Implemented (2025-10-06)  
**Version**: 0.1.0

---

## Overview

JsonApiBundle provides automatic OpenAPI 3.1 documentation generation with interactive UI support via Swagger UI or Redoc.

---

## Features

### 🎨 Two UI Themes
- **Swagger UI** (default) - Interactive API explorer with "Try it out" functionality
- **Redoc** - Clean, responsive three-panel documentation

### 📖 Auto-Generated Documentation
- Reflects all registered JSON:API resources
- Includes attributes, relationships, and metadata
- Supports sparse fieldsets, includes, sorting, pagination
- Documents atomic operations extension
- Documents profile support

### 🔒 Environment-Aware
- Enable/disable per environment
- Configurable routes
- Production-safe (disable in prod)

### 🚀 Zero Dependencies
- Uses CDN-hosted UI libraries
- No additional Composer packages required
- Lightweight implementation

---

## Configuration

### Basic Configuration

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
        ui:
            enabled: true
            route: '/_jsonapi/docs'
            spec_url: '/_jsonapi/openapi.json'
            theme: 'swagger'  # or 'redoc'
```

### Production Configuration

Disable UI in production:

```yaml
# config/packages/prod/jsonapi.yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: false  # Disable OpenAPI generation
        ui:
            enabled: false      # Disable UI
```

### Development Configuration

Enable with custom settings:

```yaml
# config/packages/dev/jsonapi.yaml
jsonapi:
    docs:
        generator:
            openapi:
                enabled: true
                title: 'My API (Development)'
                version: '1.0.0-dev'
                servers:
                    - 'http://localhost:8000'
        ui:
            enabled: true
            route: '/api/docs'  # Custom route
            theme: 'redoc'      # Use Redoc instead of Swagger
```

---

## Usage

### Accessing the UI

**Swagger UI** (default):
```
GET /_jsonapi/docs
```

**Redoc** (if configured):
```
GET /_jsonapi/docs
```

### Accessing the OpenAPI Spec

Machine-readable OpenAPI 3.1 specification:
```
GET /_jsonapi/openapi.json
```

Response:
```json
{
  "openapi": "3.1.0",
  "info": {
    "title": "My API",
    "version": "1.0.0"
  },
  "servers": [
    {
      "url": "https://api.example.com"
    }
  ],
  "paths": {
    "/api/articles": {
      "get": {
        "summary": "List articles",
        "parameters": [
          {
            "name": "include",
            "in": "query",
            "schema": { "type": "string" }
          },
          {
            "name": "fields[articles]",
            "in": "query",
            "schema": { "type": "string" }
          }
        ]
      }
    }
  }
}
```

---

## Swagger UI Features

### Interactive Testing
- **Try it out**: Execute API requests directly from the browser
- **Request/Response examples**: See sample payloads
- **Authentication**: Configure API keys, OAuth, etc.

### Navigation
- **Deep linking**: Share links to specific endpoints
- **Filtering**: Search for specific operations
- **Expand/Collapse**: Control documentation detail level

### Customization
The Swagger UI is configured with:
- `tryItOutEnabled: true` - Enable interactive testing
- `deepLinking: true` - Enable URL-based navigation
- `filter: true` - Enable search/filter
- `docExpansion: "list"` - Show operations list by default

---

## Redoc Features

### Clean Design
- **Three-panel layout**: Navigation, content, code samples
- **Responsive**: Works on mobile and desktop
- **Print-friendly**: Generate PDF documentation

### Advanced Features
- **Search**: Full-text search across documentation
- **Code samples**: Multiple language examples
- **Schema explorer**: Interactive schema navigation

---

## Implementation Details

### Architecture

```
SwaggerUiController
├── Configuration (jsonapi.docs.ui)
├── Theme Selection (swagger | redoc)
└── HTML Rendering
    ├── Swagger UI (CDN: swagger-ui-dist@5.10.5)
    └── Redoc (CDN: redoc@2.1.3)
```

### Files

**Controller**:
- `src/Http/Controller/SwaggerUiController.php` - UI controller

**Configuration**:
- `src/Bridge/Symfony/DependencyInjection/Configuration.php` - Config schema
- `src/Bridge/Symfony/DependencyInjection/JsonApiExtension.php` - Parameter registration
- `config/services.php` - Service registration

**Tests**:
- `tests/Functional/Docs/SwaggerUiTest.php` - 5 test cases

---

## Security Considerations

### Production Deployment

**Recommendation**: Disable in production to prevent information disclosure.

```yaml
# config/packages/prod/jsonapi.yaml
jsonapi:
    docs:
        ui:
            enabled: false
```

### Access Control

If you need documentation in production, protect it with authentication:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_jsonapi/docs, roles: ROLE_ADMIN }
        - { path: ^/_jsonapi/openapi.json, roles: ROLE_ADMIN }
```

### Rate Limiting

Consider rate limiting documentation endpoints:

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        docs:
            policy: 'fixed_window'
            limit: 10
            interval: '1 minute'
```

---

## Troubleshooting

### UI Not Loading

**Problem**: Blank page or 404 error

**Solution**:
1. Verify `enabled: true` in configuration
2. Clear cache: `php bin/console cache:clear`
3. Check route exists: `php bin/console debug:router | grep jsonapi`

### OpenAPI Spec Not Found

**Problem**: UI loads but shows "Failed to load API definition"

**Solution**:
1. Verify OpenAPI generation is enabled
2. Check `spec_url` matches OpenAPI route
3. Test spec directly: `curl http://localhost/_jsonapi/openapi.json`

### CDN Resources Blocked

**Problem**: UI doesn't render due to CSP or network restrictions

**Solution**:
1. Allow CDN in Content Security Policy:
   ```
   Content-Security-Policy: script-src 'self' https://cdn.jsdelivr.net
   ```
2. Or host Swagger UI locally (requires additional setup)

---

## Comparison: Swagger UI vs Redoc

| Feature | Swagger UI | Redoc |
|---------|-----------|-------|
| **Interactive Testing** | ✅ Yes | ❌ No |
| **Try it out** | ✅ Yes | ❌ No |
| **Three-panel layout** | ❌ No | ✅ Yes |
| **Print-friendly** | ⚠️ Limited | ✅ Yes |
| **Search** | ✅ Yes | ✅ Yes |
| **Mobile-friendly** | ⚠️ Limited | ✅ Yes |
| **Code samples** | ✅ Yes | ✅ Yes |
| **File size** | ~2MB | ~500KB |
| **Load time** | Slower | Faster |

**Recommendation**:
- **Development**: Use Swagger UI for interactive testing
- **Public docs**: Use Redoc for clean, professional appearance

---

## Future Enhancements

### Planned Features
- [ ] Custom CSS themes
- [ ] Authentication configuration UI
- [ ] Multiple spec versions (v1, v2, etc.)
- [ ] Spec download button
- [ ] Postman collection export

### Community Requests
- [ ] Self-hosted UI option (no CDN)
- [ ] Custom logo/branding
- [ ] Multi-language support
- [ ] API changelog integration

---

## Related Documentation

- [OpenAPI Specification](../architecture/openapi-spec.md)
- [JSON:API Conformance](../conformance/spec-coverage.md)
- [Configuration Reference](../configuration/reference.md)

---

**Last Updated**: 2025-10-06  
**Author**: Codex QA Agent  
**Status**: ✅ Production-ready

