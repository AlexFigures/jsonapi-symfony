# JsonApiBundle - Documentation

This directory contains comprehensive documentation for JsonApiBundle.

---

## 📋 Table of Contents

1. [User Documentation](#user-documentation) - Getting started, guides, and examples
2. [API Reference](#api-reference) - Public API and upgrade guides
3. [Quality Assurance](#quality-assurance) - Conformance, architecture, and security
4. [Examples](#examples) - Code examples and implementations

---

## 📚 User Documentation

### [Developer Guide](guide/README.md)

Complete developer documentation with tutorials and guides.

**Quick Links**:
- **[Getting Started](guide/getting-started.md)** - Your first JSON:API in 5 minutes
- **[Configuration](guide/configuration.md)** - Complete configuration reference
- **[Doctrine Integration](guide/integration-doctrine.md)** - Production-ready data layer
- **[Advanced Features](guide/advanced-features.md)** - Profiles, hooks, events, caching
- **[Examples & Recipes](guide/examples.md)** - Real-world code examples
- **[Troubleshooting](guide/troubleshooting.md)** - Common issues and solutions



---

## 📖 API Reference

### [Public API Documentation](api/public-api.md)

Stable API reference with backward compatibility guarantees.

### [Backward Compatibility Policy](api/bc-policy.md)

Versioning strategy and BC guarantees.

### [Upgrade Guide](api/upgrade-guide.md)

Migration guide for version upgrades.

---

## 🔍 Examples

### [Code Examples](examples/README.md)

Real-world implementations and patterns.

**Available Examples**:
- Custom handlers and filters
- Geospatial distance filtering
- Full-text search implementation
- Relevance sorting
- Sortable fields configuration

---

## 🔧 Quality Assurance

### Specification Conformance

#### [Specification Coverage Matrix](conformance/spec-coverage.md)

Comprehensive mapping of JSON:API 1.1 requirements to test cases.

**Key Metrics**:
- ✅ **97.8% Coverage** (132/135 requirements)
- ✅ **100% MUST** requirements covered
- ✅ **98.5% SHOULD** requirements covered

#### [Test Gap Analysis](conformance/gaps.md)

Identified gaps and remediation plan.

**Status**: ✅ **Excellent** - Only 3 minor gaps

### Architecture & Design

#### [Architecture Review](architecture/review.md)

Layered architecture, extensibility, and BC policy.

**Status**: ✅ **9.5/10** - Excellent architecture

**Key Features**:
- ✅ Clean layering (Deptrac: 0 violations)
- ✅ Well-defined public API (`Contract\*` namespace)
- ✅ Powerful extensibility (profiles, operators, adapters)
- ✅ Type-safe (PHPStan Level 8)

### Security

#### [Security Checklist](security/checklist.md)

Comprehensive security audit and best practices.

**Status**: ✅ **9/10** - Excellent security posture

**Key Protections**:
- ✅ SQL injection protected (DQL parameterization)
- ✅ DoS protection (complexity limits)
- ✅ Strict input validation
- ✅ Safe error handling (no info leakage)

### Performance & Reliability

#### [Memory & Performance Report](reliability/memory-perf-report.md)

Memory leak detection and performance profiling.

**Status**: ⚠️ **Partial** - Infrastructure exists, needs real controller integration

---

## 🚀 Getting Started

Ready to build your first JSON:API? Start with our [Getting Started Guide](guide/getting-started.md)!

### Quick Links

- **[Installation & Setup](guide/getting-started.md#installation)** - Get up and running in 5 minutes
- **[Configuration](guide/configuration.md)** - Configure for your needs
- **[Doctrine Integration](guide/integration-doctrine.md)** - Production-ready data layer
- **[Troubleshooting](guide/troubleshooting.md)** - Common issues and solutions

---

## 📚 Additional Resources

- **[JSON:API 1.1 Specification](https://jsonapi.org/format/1.1/)** - Official specification
- **[RFC 6906 - Profile Parameter](https://www.rfc-editor.org/rfc/rfc6906)** - Profile extension
- **[Atomic Operations Extension](https://jsonapi.org/ext/atomic/)** - Batch operations

---

## 🤝 Contributing

See [CONTRIBUTING.md](../CONTRIBUTING.md) for contribution guidelines.

For security issues, see [SECURITY.md](../SECURITY.md).

---

## 📄 License

MIT License - See [LICENSE](../LICENSE) for details.

---

**Last Updated**: 2025-10-07
**Status**: ✅ Complete

