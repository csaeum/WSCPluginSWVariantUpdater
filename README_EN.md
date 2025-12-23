# WSCPluginSWVariantUpdater

[Deutsche Version](README.md)

## Description

A Shopware 6 plugin that automatically updates product variant names and product numbers based on the parent product and variant options.

## Features

- **Automatic Naming**: Creates variant names following the pattern `{Parent Product Name} {Option Name(s)}`
- **Automatic Product Numbers**: Generates product numbers following the pattern `{Parent Number}-{option(s)-in-lowercase}`
- **Flexible Options**:
  - Update names only (`--name-only`)
  - Update product numbers only (`--number-only`)
  - Preview mode without saving (`--dry-run`)
- **Batch Processing**: Process multiple parent products at once
- **Safe Processing**: Explicit product selection required, no automatic mass processing

## Installation

### 1. Install Plugin

```bash
# Create plugin directory (if not already present)
mkdir -p custom/plugins

# Place plugin in custom/plugins
cd custom/plugins
git clone <repository-url> WSCPluginSWVariantUpdater

# Return to Shopware root directory
cd ../../

# Install and activate plugin
bin/console plugin:refresh
bin/console plugin:install --activate WSCPluginSWVariantUpdater
bin/console cache:clear
```

### 2. Install Dependencies (for development)

```bash
cd custom/plugins/WSCPluginSWVariantUpdater
composer install
```

## Usage

### Basic Usage

```bash
# Update single parent product
bin/console wsc:variant:update --product-numbers="productnumber123"

# Multiple parent products at once
bin/console wsc:variant:update --product-numbers="product1,product2,product3"
```

### Options

#### --product-numbers (REQUIRED)
A single product number or comma-separated list of parent product numbers.

```bash
bin/console wsc:variant:update --product-numbers="greatleather"
bin/console wsc:variant:update --product-numbers="leather1,leather2,leather3"
```

#### --dry-run (optional)
Shows what would be changed without updating the database.

```bash
bin/console wsc:variant:update --product-numbers="greatleather" --dry-run
```

#### --name-only (optional)
Updates only product names, leaves product numbers unchanged.

```bash
bin/console wsc:variant:update --product-numbers="greatleather" --name-only
```

#### --number-only (optional)
Updates only product numbers, leaves product names unchanged.

```bash
bin/console wsc:variant:update --product-numbers="greatleather" --number-only
```

## Examples

### Example 1: Simple Variant

**Parent Product:**
- Name: "Great Leather | Cowhide"
- Product Number: "greatleather"

**Variant with Option:**
- Option: "red"

**Result after Update:**
- Name: "Great Leather | Cowhide red"
- Product Number: "greatleather-red"

### Example 2: Variant with Multiple Options

**Parent Product:**
- Name: "Premium Leather Jacket"
- Product Number: "jacket-001"

**Variant with Options:**
- Size: "XL"
- Color: "Black Metallic"

**Result after Update:**
- Name: "Premium Leather Jacket XL Black Metallic"
- Product Number: "jacket-001-xl-black-metallic"

### Example 3: Umlauts and Special Characters

**Parent Product:**
- Product Number: "shoes"

**Variant with Options:**
- Color: "Grün" (Green)
- Size: "42"

**Result after Update:**
- Product Number: "shoes-gruen-42"

## Naming Conventions

### Product Names
```
{Parent Product Name} {Option 1} {Option 2} ...
```

**Rules:**
- Parent product name is used as-is
- Option names are appended separated by spaces
- Multiple options are concatenated in the order of option groups

### Product Numbers
```
{Parent Product Number}-{option-1}-{option-2}-...
```

**Rules:**
- Parent product number is used as-is (case-sensitive)
- Option names are converted to lowercase
- Spaces are replaced with hyphens (`-`)
- Umlauts are converted:
  - ä → ae
  - ö → oe
  - ü → ue
  - ß → ss
  - Ä → ae
  - Ö → oe
  - Ü → ue

## Technical Details

### System Requirements

- **Shopware:** 6.5.0 or higher
- **PHP:** 8.1, 8.2 or 8.3
- **Extensions:** mbstring, json

### Technology Stack

- **Data Abstraction Layer (DAL)**: Uses exclusively Shopware's DAL, no direct SQL
- **Repository Pattern**: Uses `product.repository` for all database operations
- **Criteria & Filter**: Uses Criteria API for safe and performant database queries
- **Associations**: Loads variant options via DAL associations

### File Structure

```
WSCPluginSWVariantUpdater/
├── .github/
│   ├── workflows/
│   │   └── ci.yml                          # CI/CD Pipeline
│   └── dependabot.yml                      # Dependency Updates
├── src/
│   ├── Command/
│   │   └── UpdateVariantCommand.php        # Console Command
│   ├── Resources/
│   │   └── config/
│   │       └── services.xml                # Service Configuration
│   └── WSCPluginSWVariantUpdater.php       # Plugin Base Class
├── .php-cs-fixer.dist.php                  # PHP-CS-Fixer Config
├── phpstan.neon                            # PHPStan Config
├── composer.json                           # Composer Config
└── README.md                               # German Documentation
└── README_EN.md                            # This File
```

## CI/CD & Quality Assurance

This plugin uses a comprehensive CI/CD pipeline with the following automated tests:

### Code Quality

- **PHP Syntax Check**: Checks for syntax errors in all PHP files
- **PHPStan (Level 8)**: Static code analysis for type safety and best practices
- **PHP-CS-Fixer**: Code style verification (PSR-12 + Symfony standards)
- **Composer Validation**: Validates composer.json structure

### Security & Compatibility

- **Security Audit**: Checks for known security vulnerabilities in dependencies
- **JSON Validation**: Syntax validation of all JSON configuration files
- **Plugin Structure Validation**: Validates plugin structure and services.xml
- **Multi-PHP Testing**: Tests against PHP 8.1, 8.2, and 8.3

### Automation

- **Dependabot**: Automatic dependency updates (weekly)
- **GitHub Actions**: Automatic tests on every push and pull request
- **Automated Releases**: Automatic plugin ZIP generation on Git tags

### Test Overview

| Test Category | What is Checked | Tool | Run Locally |
|---------------|----------------|------|-------------|
| **PHP Syntax** | Syntax errors in all PHP files | `php -l` | Automatic with `composer test` |
| **Composer Validation** | composer.json structure and correctness | `composer validate` | `composer validate --strict` |
| **Code Style (PSR-12)** | PHP code style standards | PHP-CS-Fixer | `composer cs-check` |
| **Code Style Fix** | Automatic code style correction | PHP-CS-Fixer | `composer cs-fix` |
| **Static Code Analysis** | Type errors, logic errors, best practices | PHPStan (Level 8) | `composer phpstan` |
| **Multi-Version Testing** | Compatibility with PHP 8.1, 8.2, 8.3 | GitHub Actions Matrix | CI only |
| **JSON Validation** | JSON syntax (composer.json, services.xml) | `jq`, `xmllint` | `jq empty composer.json` |
| **Dependency Security** | Known security vulnerabilities | `composer audit` | `composer audit` |
| **Outdated Dependencies** | Outdated packages | `composer outdated` | `composer outdated` |
| **Plugin Structure** | Plugin structure and files | Custom Validation | CI only |

### CI/CD Pipeline (GitHub Actions)

The pipeline executes 5 parallel jobs:

#### 1. PHP Quality Checks
- ✅ PHP Syntax Check (all .php files)
- ✅ Composer Validation (`--strict --no-check-lock`)
- ✅ PHPStan Level 8 (finds type errors, undefined variables, dead code)
- ✅ PHP-CS-Fixer Dry-Run (PSR-12 + Symfony standards)
- ✅ Matrix Testing: PHP 8.1, 8.2 and 8.3

#### 2. JSON Validation
- ✅ Validates composer.json
- ✅ Checks all JSON files in the project

#### 3. Security Audit
- ✅ Composer Dependency Security Check
- ✅ Warning for outdated packages

#### 4. Plugin Validation
- ✅ Plugin structure validation
- ✅ Checks for required files
- ✅ services.xml XML syntax validation

#### 5. Code Style Check
- ✅ PHP-CS-Fixer in check mode
- ✅ Verifies PSR-12 and Symfony code style standards

### Run Tests Locally

```bash
# Run all tests
composer test

# PHPStan only
composer phpstan

# Code style check only
composer cs-check

# Auto-fix code style
composer cs-fix

# Validate composer
composer validate --strict

# Security audit
composer audit

# Check for outdated dependencies
composer outdated
```

## Release Management & Package Generation

### Automated Releases

The plugin uses GitHub Actions for automatic release generation. When creating a Git tag, the following happens automatically:

1. An optimized plugin ZIP archive is created
2. A GitHub release with the ZIP as download is created
3. Development files are automatically excluded (via `.gitattributes`)

### Creating a Release

```bash
# Create version tag (e.g. v1.0.0)
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0

# GitHub Actions automatically creates:
# - WSCPluginSWVariantUpdater-1.0.0.zip
# - GitHub Release with changelog
```

### .gitattributes for Export

The `.gitattributes` file defines which files are excluded during `git archive` or ZIP generation:

**Excluded Files/Folders:**
- `.github/` - CI/CD workflows
- `.php-cs-fixer.dist.php` - Code style configuration
- `phpstan.neon` - PHPStan configuration
- `README*.md` - Documentation (optional)
- `tests/` - Test files
- `var/` - Cache directories
- `.gitignore`, `.gitattributes` - Git configuration
- IDE configurations (`.idea/`, `.vscode/`)
- OS files (`.DS_Store`, `Thumbs.db`)

**Included in Final ZIP:**
- `src/` - All PHP source files
- `composer.json` - Composer configuration
- `vendor/` - Production dependencies (automatically added)

### Manual ZIP Creation

If you want to create a ZIP manually:

```bash
# Via git archive (respects .gitattributes)
git archive --format=zip --prefix=WSCPluginSWVariantUpdater/ HEAD -o WSCPluginSWVariantUpdater.zip

# Install production dependencies
composer install --no-dev --optimize-autoloader

# Add vendor manually
zip -r WSCPluginSWVariantUpdater.zip vendor/
```

## Development

### Code Style

The plugin follows PSR-12 and Symfony Coding Standards. Use PHP-CS-Fixer to check code style:

```bash
# Check code style
composer cs-check

# Auto-correct code style
composer cs-fix
```

### Static Code Analysis

PHPStan is executed at level 8:

```bash
composer phpstan
```

### Run All Tests

```bash
composer test
```

## Troubleshooting

### Plugin not found
```bash
bin/console plugin:refresh
```

### Cache issues
```bash
bin/console cache:clear
```

### Product not found
Make sure that:
- The product number is correct
- It's a parent product (not a variant)
- The product exists in the system

### No variants found
The parent product must have variants. Check in the admin panel if variants are created for the product.

## Support & Contributions

### Issues
Please report bugs and feature requests via GitHub Issues.

### Pull Requests
Contributions are welcome! Please ensure that:
- All tests pass (`composer test`)
- Code style is followed (`composer cs-fix`)
- PHPStan passes without errors (`composer phpstan`)

## License

MIT License - See LICENSE file for details.

## Changelog

### Version 1.0.0
- Initial release
- Console command for variant updates
- Support for names and product numbers
- Dry-run mode
- Selective updates (--name-only, --number-only)
- Batch processing of multiple products
- Comprehensive CI/CD pipeline
- PHPStan Level 8
- PHP-CS-Fixer integration
- Multi-PHP version support (8.1, 8.2, 8.3)
