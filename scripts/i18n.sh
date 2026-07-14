#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Generating POT template"
wp i18n make-pot . languages/prikogstreg-online-invitations.pot \
  --domain=prikogstreg-online-invitations \
  --exclude=vendor,node_modules,pdf-plugin,assets/src

echo "==> Building Danish PO/MO"
python3 scripts/build-da-po.py

echo "==> i18n build complete"
