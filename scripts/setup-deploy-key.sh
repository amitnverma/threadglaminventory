#!/bin/bash
# One-time deploy key setup — run on your Mac
# Usage: bash scripts/setup-deploy-key.sh

set -e

KEY_FILE="$HOME/.ssh/threadglam_deploy"
VPS_USER="threadglam"
VPS_HOST="srv792158.hstgr.cloud"

echo ""
echo "=== ThreadGlam Deploy Key Setup ==="
echo ""

# Step 1: Create key if missing
if [ ! -f "$KEY_FILE" ]; then
  echo "Creating SSH key..."
  ssh-keygen -t ed25519 -C "github-deploy-threadglam" -f "$KEY_FILE" -N ""
  echo "✅ Key created: $KEY_FILE"
else
  echo "✅ Key already exists: $KEY_FILE"
fi

PUB_KEY=$(cat "${KEY_FILE}.pub")

# Step 2: Build the ONE command to paste on VPS (key already inside)
VPS_COMMAND="mkdir -p ~/.ssh && chmod 700 ~/.ssh && echo '${PUB_KEY}' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && mkdir -p ~/htdocs/www.threadglam.com/inventory/uploads && chmod 755 ~/htdocs/www.threadglam.com/inventory/uploads && echo 'Done! Key added.'"

echo ""
echo "----------------------------------------"
echo "STEP A — Login to your VPS (run on Mac):"
echo "----------------------------------------"
echo ""
echo "  ssh ${VPS_USER}@${VPS_HOST}"
echo ""
echo "----------------------------------------"
echo "STEP B — Paste this ENTIRE block on VPS:"
echo "----------------------------------------"
echo ""
echo "$VPS_COMMAND"
echo ""

# Copy VPS command to clipboard (Mac)
if command -v pbcopy >/dev/null 2>&1; then
  echo "$VPS_COMMAND" | pbcopy
  echo "✅ STEP B command copied to your clipboard!"
  echo "   → Login to VPS, then press Cmd+V to paste and Enter."
else
  echo "⚠️  Copy the STEP B block above manually."
fi

echo ""
echo "----------------------------------------"
echo "STEP C — Test connection (run on Mac):"
echo "----------------------------------------"
echo ""
echo "  ssh -i $KEY_FILE ${VPS_USER}@${VPS_HOST}"
echo ""
echo "----------------------------------------"
echo "STEP D — GitHub secret VPS_SSH_KEY:"
echo "----------------------------------------"
echo ""
echo "  cat $KEY_FILE"
echo ""

if command -v pbcopy >/dev/null 2>&1; then
  read -r -p "Copy private key to clipboard for GitHub? (y/n): " COPY_PRIVATE
  if [ "$COPY_PRIVATE" = "y" ] || [ "$COPY_PRIVATE" = "Y" ]; then
    cat "$KEY_FILE" | pbcopy
    echo "✅ Private key copied! Paste into GitHub secret: VPS_SSH_KEY"
  fi
fi

echo ""
echo "Done. Next: add GitHub secrets (see DEPLOY.md step 3)."
echo ""
