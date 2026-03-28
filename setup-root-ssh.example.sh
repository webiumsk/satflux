#!/bin/bash
# Copy SSH deploy keys from a normal user to root (e.g. for git pull in cron).
# Copy to setup-root-ssh.sh (gitignored), set SOURCE_USER, run as root: sudo ./setup-root-ssh.sh
#
# Usage:
#   export SOURCE_USER=yourlinuxuser   # user that already has ~/.ssh/github_deploy_key(.pub)
#   sudo -E ./setup-root-ssh.sh

set -euo pipefail

SOURCE_USER="${SOURCE_USER:-}"
KEY_NAME="${KEY_NAME:-github_deploy_key}"

if [ "$EUID" -ne 0 ]; then
  echo "Error: run as root or with sudo"
  exit 1
fi

if [ -z "$SOURCE_USER" ]; then
  echo "Error: set SOURCE_USER to the account whose keys to copy, e.g.:"
  echo "  export SOURCE_USER=deploy"
  echo "  sudo -E ./setup-root-ssh.sh"
  exit 1
fi

SRC_HOME="/home/${SOURCE_USER}/.ssh"
PUB="${SRC_HOME}/${KEY_NAME}.pub"
PRIV="${SRC_HOME}/${KEY_NAME}"

echo "Setting up SSH for root from ${SRC_HOME}..."

mkdir -p /root/.ssh
chmod 700 /root/.ssh

if [ ! -f "$PUB" ]; then
  echo "Error: public key not found: $PUB"
  exit 1
fi

cp "$PUB" "/root/.ssh/${KEY_NAME}.pub"
chmod 644 "/root/.ssh/${KEY_NAME}.pub"
echo "✓ Public key copied"

if [ -r "$PRIV" ]; then
  cp "$PRIV" "/root/.ssh/${KEY_NAME}"
  chmod 600 "/root/.ssh/${KEY_NAME}"
  echo "✓ Private key copied"
else
  echo "⚠ Cannot read private key: $PRIV"
  echo "  Generate a key for root instead, e.g.:"
  echo "  ssh-keygen -t ed25519 -C 'deploy@$(hostname)' -f /root/.ssh/${KEY_NAME} -N ''"
  echo "  Then add the public key to your Git host."
fi

cat > /root/.ssh/config << EOF
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/${KEY_NAME}
    IdentitiesOnly yes
EOF
chmod 600 /root/.ssh/config

if ! grep -q "github.com" /root/.ssh/known_hosts 2>/dev/null; then
  ssh-keyscan github.com >> /root/.ssh/known_hosts 2>/dev/null || true
  echo "✓ github.com added to known_hosts"
fi

echo ""
echo "✓ Done. Test: ssh -T git@github.com"
