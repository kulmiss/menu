#!/bin/sh
# Optional local secret check before each commit (GitHub push protection is not free on
# private repos). Install once:  ln -s ../../scripts/pre-commit-secrets.sh .git/hooks/pre-commit
# Needs gitleaks: https://github.com/gitleaks/gitleaks/releases (or `brew install gitleaks`).
command -v gitleaks >/dev/null 2>&1 || { echo "gitleaks not installed; skipping secret check"; exit 0; }
exec gitleaks git --pre-commit --staged --redact --no-banner .
