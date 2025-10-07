# JsonApiBundle - Developer Guide

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Welcome! 👋

This is the complete developer guide for JsonApiBundle, a JSON:API 1.1 compliant bundle for Symfony 7+.

---

## 📚 Documentation Index

### Getting Started

- **[Getting Started Guide](getting-started.md)** - Your first JSON:API in 5 minutes
  - Installation
  - Quick start tutorial
  - Your first resource
  - Testing your API

### Configuration

- **[Configuration Reference](configuration.md)** - Complete configuration guide
  - All configuration options
  - Environment-specific settings
  - Advanced configuration
  - Service registration

### Integration Guides

- **[Doctrine ORM Integration](integration-doctrine.md)** - Use with Doctrine ORM
  - Entity setup
  - Repository implementation
  - Persister implementation
  - Relationship handling
  - Transaction management
  - Best practices

### Advanced Topics

- **[Advanced Features](advanced-features.md)** - Profiles, hooks, events, caching
  - Profiles (RFC 6906)
  - Hooks system
  - Event system
  - HTTP caching
  - Atomic operations
  - Custom filter operators
  - Cache invalidation

### Practical Examples

- **[Examples & Recipes](examples.md)** - Real-world code examples
  - Basic CRUD operations
  - Working with relationships
  - Advanced queries
  - Authentication & authorization
  - Validation
  - File uploads
  - Soft deletes
  - Audit trail
  - Multi-tenancy

### Troubleshooting

- **[Troubleshooting Guide](troubleshooting.md)** - Common issues and solutions
  - Common issues
  - Installation problems
  - Configuration errors
  - Runtime errors
  - Performance issues
  - Debugging tips

---

## 📖 API Documentation

### Public API

- **[Public API Reference](../api/public-api.md)** - Stable API documentation
  - Data layer contracts
  - Data transfer objects
  - Resource attributes
  - Integration examples

### Versioning & Compatibility

- **[Backward Compatibility Policy](../api/bc-policy.md)** - BC guarantees
  - Semantic versioning
  - What is public API
  - Breaking changes
  - Deprecation policy

- **[Upgrade Guide](../api/upgrade-guide.md)** - Migration between versions
  - Upgrade checklist
  - Version-specific guides
  - Breaking changes
  - Deprecations

---

## 🎯 Quality Assurance

### Specification Compliance

- **[Spec Coverage Matrix](../conformance/spec-coverage.md)** - JSON:API 1.1 compliance
- **[Test Gap Analysis](../conformance/gaps.md)** - Missing tests and remediation

### Architecture & Design

- **[Architecture Review](../architecture/review.md)** - Design, extensibility, BC policy

### Security

- **[Security Checklist](../security/checklist.md)** - Security audit and best practices

### Performance & Reliability

- **[Memory & Performance Report](../reliability/memory-perf-report.md)** - Profiling and stress tests

---

## 🚀 Quick Links

### For New Users

1. Start with [Getting Started Guide](getting-started.md)
2. Read [Configuration Reference](configuration.md)
3. Follow [Doctrine Integration](integration-doctrine.md) if using Doctrine
4. Check [Examples & Recipes](examples.md) for real-world patterns

### For Contributors

1. Read [CONTRIBUTING.md](../../CONTRIBUTING.md)
2. Review [Architecture Review](../architecture/review.md)
3. Check [Public API Reference](../api/public-api.md)
4. Follow [BC Policy](../api/bc-policy.md)

### For Troubleshooting

1. Check [Troubleshooting Guide](troubleshooting.md)
2. Search [GitHub Issues](https://github.com/AlexFigures/jsonapi-symfony/issues)
3. Ask in [GitHub Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions)

---

## 📊 Feature Overview

### Core Features

✅ **JSON:API 1.1 Compliance** - Full spec support  
✅ **Attribute-Driven** - No XML/YAML configuration  
✅ **Auto-Generated Endpoints** - No controller boilerplate  
✅ **Query Parameters** - include, fields, sort, page  
✅ **Relationships** - To-one and to-many  
✅ **Write Operations** - POST, PATCH, DELETE  
✅ **Atomic Operations** - Batch operations  
✅ **Profiles** - RFC 6906 support  
✅ **HTTP Caching** - ETag, Last-Modified  
✅ **Interactive Docs** - Swagger UI / Redoc

### Advanced Features

✅ **Hooks System** - Intercept request processing  
✅ **Event System** - React to resource changes  
✅ **Custom Operators** - Extend filtering  
✅ **Cache Invalidation** - CDN/proxy support  
✅ **Transaction Management** - Atomic writes  
✅ **Extensibility** - Profiles, hooks, operators

---

## 🎓 Learning Path

### Beginner

1. **[Getting Started](getting-started.md)** - Build your first API
2. **[Configuration](configuration.md)** - Understand configuration options
3. **[Examples](examples.md)** - Learn from real-world examples

### Intermediate

1. **[Doctrine Integration](integration-doctrine.md)** - Production-ready data layer
2. **[Advanced Features](advanced-features.md)** - Profiles, hooks, caching
3. **[Troubleshooting](troubleshooting.md)** - Debug common issues

### Advanced

1. **[Public API Reference](../api/public-api.md)** - Deep dive into contracts
2. **[Architecture Review](../architecture/review.md)** - Understand design decisions
3. **[BC Policy](../api/bc-policy.md)** - Contribute safely

---

## 🔗 External Resources

### JSON:API Specification

- [JSON:API 1.1 Specification](https://jsonapi.org/format/1.1/)
- [JSON:API Extensions](https://jsonapi.org/extensions/)
- [RFC 6906 - Profile Parameter](https://www.rfc-editor.org/rfc/rfc6906)

### Symfony Documentation

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Symfony Security](https://symfony.com/doc/current/security.html)

### Tools

- [JSON:API Validator](https://jsonapi-validator.herokuapp.com/)
- [Postman](https://www.postman.com/)
- [Insomnia](https://insomnia.rest/)

---

## 💬 Community

### Get Help

- 📖 [Documentation](getting-started.md)
- 💬 [GitHub Discussions](https://github.com/AlexFigures/jsonapi-symfony/discussions)
- 🐛 [Report Issues](https://github.com/AlexFigures/jsonapi-symfony/issues)

### Contribute

- 🤝 [Contributing Guide](../../CONTRIBUTING.md)
- 📋 [Public API](../api/public-api.md)
- 🔄 [BC Policy](../api/bc-policy.md)

---

## 📄 License

JsonApiBundle is open-source software licensed under the [MIT license](../../LICENSE).

---

## 🙏 Acknowledgments

Built with ❤️ by the JsonApiBundle team and contributors.

Special thanks to:
- The JSON:API specification authors
- The Symfony community
- All contributors and users

---

**Happy coding!** 🚀

---

**Last Updated**: 2025-10-07  
**Version**: 0.1.0

