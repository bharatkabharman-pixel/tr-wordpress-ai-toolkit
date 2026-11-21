# Contributing

Thank you for considering a contribution to TR WordPress AI Toolkit.

## Getting Started

```bash
git clone https://github.com/trsoftech/tr-wordpress-ai-toolkit.git
cd tr-wordpress-ai-toolkit
composer install
```

## Running Tests

```bash
# Install WordPress test suite (first time only)
bash tests/bin/install-wp-tests.sh wordpress_test root root localhost latest

# Run all tests
composer test

# Check code standards
composer lint
```

## Code Standards

This project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).

- Run `composer lint` before submitting a pull request.
- Use `composer lint-fix` to auto-fix fixable issues.

## Submitting Changes

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Write tests for your changes.
4. Ensure all tests pass and code standards are met.
5. Submit a pull request against `main`.

## Adding New Abilities

1. Add the ability registration in the appropriate module class (e.g., `modules/content/class-content-module.php`).
2. Register the ability on the `wp_abilities_api_init` hook.
3. Set `'meta' => ['mcp' => ['public' => true]]` to expose it via MCP.
4. Add a `permission_callback` using `$this->permissions->can_read()` or `can_write()` as appropriate.
5. Add the ability to the README.md abilities table.
6. Write a PHPUnit test.

## Security Issues

Report security vulnerabilities to security@trsoftech.com. Do not create public issues.
