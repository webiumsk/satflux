#!/bin/bash
# Setup SSH key for root user from peterhorvath user's key
# This script should be run as root or with sudo

set -e

echo "Setting up SSH key for root user..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Error: This script must be run as root or with sudo"
    exit 1
fi

# Create .ssh directory
mkdir -p /root/.ssh
chmod 700 /root/.ssh

# Copy public key
if [ -f /home/peterhorvath/.ssh/github_deploy_key.pub ]; then
    cp /home/peterhorvath/.ssh/github_deploy_key.pub /root/.ssh/github_deploy_key.pub
    chmod 644 /root/.ssh/github_deploy_key.pub
    echo "✓ Public key copied"
else
    echo "Error: Source public key not found at /home/peterhorvath/.ssh/github_deploy_key.pub"
    exit 1
fi

# Copy private key (may require permissions)
if [ -r /home/peterhorvath/.ssh/github_deploy_key ]; then
    cp /home/peterhorvath/.ssh/github_deploy_key /root/.ssh/github_deploy_key
    chmod 600 /root/.ssh/github_deploy_key
    echo "✓ Private key copied"
else
    echo "⚠ Warning: Cannot access private key at /home/peterhorvath/.ssh/github_deploy_key"
    echo "You may need to generate a new key for root:"
    echo "  ssh-keygen -t ed25519 -C 'satflux.io-deploy@server' -f /root/.ssh/github_deploy_key -N ''"
    echo "Then add the NEW public key to GitHub:"
    echo "  cat /root/.ssh/github_deploy_key.pub"
fi

# Create SSH config
cat > /root/.ssh/config << 'EOFCONFIG'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_deploy_key
    IdentitiesOnly yes
EOFCONFIG
chmod 600 /root/.ssh/config

# Add GitHub to known_hosts
if ! grep -q "github.com" /root/.ssh/known_hosts 2>/dev/null; then
    ssh-keyscan github.com >> /root/.ssh/known_hosts 2>/dev/null
    echo "✓ GitHub added to known_hosts"
fi

echo ""
echo "✓ SSH setup complete for root user"
echo ""
echo "Public key:"
cat /root/.ssh/github_deploy_key.pub
echo ""
echo "Test connection with:"
echo "  ssh -T git@github.com"

