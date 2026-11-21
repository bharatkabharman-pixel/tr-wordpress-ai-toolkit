# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ Yes    |

## Reporting a Vulnerability

**Do not report security vulnerabilities through public GitHub issues.**

Email: security@trsoftech.com

Please include:

1. Description of the vulnerability
2. Steps to reproduce
3. WordPress and PHP versions
4. Impact assessment

We will acknowledge within 48 hours and aim to release a fix within 14 days for critical issues.

## Security Design

- Every ability enforces WordPress capability checks before execution
- Write operations require `edit_posts` or higher
- Admin-level abilities require `manage_options`
- Sensitive data (passwords, tokens, auth keys, session data) is never exposed through any ability
- Input is sanitized using WordPress APIs; output is escaped
- Audit log redacts sensitive field names automatically
- No arbitrary PHP execution, shell commands or unrestricted SQL is possible through any registered ability
- The plugin depends on the official WordPress MCP Adapter for transport security and authentication
