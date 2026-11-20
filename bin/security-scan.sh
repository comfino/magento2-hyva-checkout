#!/bin/sh

# Security scanning script for comfino/magento2-hyva-checkout.
# Runs comprehensive security analysis with multiple tools.
# Exit code: 0 if all checks pass, 1 if any check fails.

set -e

# Docker volume mounts are often owned by the host user while the container runs as root.
git config --global --add safe.directory "$(pwd)" 2>/dev/null || true

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
CHECKS_PASSED=0
CHECKS_FAILED=0

# Helper functions
print_header() {
    echo ""
    printf "${BLUE}========== $1 ==========${NC}\n"
}

check_pass() {
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
    printf "${GREEN}✓ $1${NC}\n"
}

check_fail() {
    CHECKS_FAILED=$((CHECKS_FAILED + 1))
    printf "${RED}✗ $1${NC}\n"
}

check_warn() {
    printf "${YELLOW}⚠ $1${NC}\n"
}

# Parse arguments
QUIET=false
VERBOSE=false
CHECKS=""

while [ $# -gt 0 ]; do
    case $1 in
        --quiet)
            QUIET=true
            ;;
        --verbose)
            VERBOSE=true
            ;;
        --check)
            CHECKS="$CHECKS $2"
            shift
            ;;
        -*)
            echo "Unknown option: $1"
            exit 1
            ;;
        *)
            CHECKS="$CHECKS $1"
            ;;
    esac
    shift
done

# Determine which checks to run.
if [ -z "$CHECKS" ]; then
    CHECKS="phpstan psalm composer-audit phpcs psalm-security"
fi

print_header "Security Scanning for comfino/magento2-hyva-checkout"
echo "PHP Version: $(php -v | head -1)"
echo "Composer Version: $(composer --version)"
echo ""

# 1. PHPStan Static Analysis
if echo "$CHECKS" | grep -q "phpstan"; then
    print_header "1. PHPStan Static Analysis"

    if php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress --memory-limit=512M; then
        check_pass "PHPStan: No errors detected"
    else
        check_fail "PHPStan: Found issues"
    fi
fi

# 2. Psalm Type Safety & Taint Analysis
if echo "$CHECKS" | grep -q "psalm"; then
    print_header "2. Psalm Type Safety Analysis"

    PSALM_BIN=""
    if [ -f vendor/bin/psalm ]; then
        PSALM_BIN="php vendor/bin/psalm"
    elif command -v psalm >/dev/null 2>&1; then
        PSALM_BIN="psalm"
    fi

    if [ -z "$PSALM_BIN" ]; then
        check_warn "Psalm: not installed, skipping"
    else
        if [ ! -f psalm.xml ]; then
            if [ "$QUIET" = false ]; then
                check_warn "Psalm config not found, initializing..."
            fi
            $PSALM_BIN --init --level=5 > /dev/null 2>&1 || true
        fi

        if $PSALM_BIN --no-progress 2>&1; then
            check_pass "Psalm: No errors detected"
        else
            check_fail "Psalm: Found issues (see above)"
        fi
    fi
fi

# 3. Composer Audit (CVE detection)
if echo "$CHECKS" | grep -q "composer-audit"; then
    print_header "3. Composer Audit (Vulnerability Detection)"

    # Run in a temp dir with a plain composer.json (Packagist only) so private repos
    # (hyva-themes, Magento) — which have no security advisories — cannot block the audit.
    # The lock file carries the full package list; only the Packagist advisory DB is needed.
    AUDIT_TMP=$(mktemp -d)
    AUDIT_HOME=$(mktemp -d)
    echo '{}' > "$AUDIT_TMP/composer.json"
    cp composer.lock "$AUDIT_TMP/"
    set +e
    AUDIT_OUTPUT=$(COMPOSER_HOME="$AUDIT_HOME" COMPOSER_NO_INTERACTION=1 composer -d "$AUDIT_TMP" audit --locked 2>&1)
    AUDIT_STATUS=$?
    set -e
    rm -rf "$AUDIT_TMP" "$AUDIT_HOME"

    echo "$AUDIT_OUTPUT" | tee /tmp/composer-audit.txt

    if [ $AUDIT_STATUS -eq 0 ]; then
        check_pass "Composer Audit: No vulnerabilities detected"
    elif [ $AUDIT_STATUS -eq 1 ]; then
        check_fail "Composer Audit: Vulnerabilities found"
    else
        check_warn "Composer Audit: Warning or skipped"
    fi
fi

# 4. PHPCS Code Sniffer (PSR-12 + Security)
if echo "$CHECKS" | grep -q "phpcs"; then
    print_header "4. PHPCS Code Style & Security"

    if PHPCS_OUT=$(php vendor/bin/phpcs --runtime-set ignore_warnings_on_exit 1 2>&1); then
        check_pass "PHPCS: All files compliant"
    else
        check_fail "PHPCS: Style violations found"
        echo "$PHPCS_OUT"
    fi
fi

# 5. Psalm Security Checks (custom configuration)
if echo "$CHECKS" | grep -q "psalm-security"; then
    print_header "5. Psalm Security Issues"

    if psalm --no-progress --show-info=false 2>&1 | grep -i "security\|injection\|xss\|taint"; then
        check_fail "Psalm: Potential security issues detected"
    else
        check_pass "Psalm Security: No obvious security issues"
    fi
fi

# 6. Custom security patterns check
print_header "6. Custom Security Patterns"

# Check for dangerous functions.
DANGEROUS_PATTERNS="eval|exec|system|passthru|shell_exec|proc_open|unserialize|assert|create_function"

if grep -r "^\s*\($DANGEROUS_PATTERNS\)" Controller Model Plugin ViewModel --include="*.php" 2>/dev/null; then
    check_fail "Custom Patterns: Found dangerous functions"
else
    check_pass "Custom Patterns: No dangerous functions detected"
fi

# Check for hardcoded credentials/keys: literal string assigned, not a function call result.
CRED_MATCHES=$(grep -rn "api_key\|apikey\|api_secret\|password" Controller Model Plugin ViewModel --include="*.php" 2>/dev/null \
  | grep -v "//\|^\s*\*\|http\|url\|endpoint\|()\|function\|param\|const " \
  | grep -E "=\s*['\"][^'\"]{8,}['\"]" | head -5)
if [ -n "$CRED_MATCHES" ]; then
    check_warn "Custom Patterns: Possible hardcoded credentials (review manually)"
    echo "$CRED_MATCHES"
else
    check_pass "Custom Patterns: No obvious hardcoded credentials"
fi

# Check for SQL injection: variable interpolation inside a query string (not in comments).
SQL_MATCHES=$(grep -rn "SELECT\|INSERT\|UPDATE\|DELETE" Controller Model Plugin ViewModel --include="*.php" 2>/dev/null \
  | grep -v "^\s*//\|^\s*\*\|_UPDATE\|_SELECT\|_INSERT\|_DELETE" \
  | grep -E '\$[a-zA-Z_]|\bconcat\b' | head -5)
if [ -n "$SQL_MATCHES" ]; then
    check_warn "Custom Patterns: SQL patterns found (verify no injection)"
    echo "$SQL_MATCHES"
else
    check_pass "Custom Patterns: No SQL queries detected (as expected)"
fi

# Summary
print_header "Security Scan Summary"

TOTAL=$((CHECKS_PASSED + CHECKS_FAILED))

printf "Checks Passed: ${GREEN}%d${NC}\n" "$CHECKS_PASSED"
printf "Checks Failed: ${RED}%d${NC}\n" "$CHECKS_FAILED"
printf "Total Checks:  %d\n" "$TOTAL"
echo ""

if [ $CHECKS_FAILED -eq 0 ]; then
    printf "${GREEN}✓ All security checks passed!${NC}\n"
    exit 0
else
    printf "${RED}✗ Security checks failed. Please review the issues above.${NC}\n"
    exit 1
fi