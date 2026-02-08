#!/bin/bash

# Script to install git hooks

set -e

# Get the project root directory
project_root=$(git rev-parse --show-toplevel)

if [ -z "$project_root" ]; then
    echo "Error: Not in a git repository"
    exit 1
fi

hooks_dir="$project_root/.git/hooks"
git_hooks_source="$project_root/scripts/git-hooks"

# Check if hooks directory exists
if [ ! -d "$hooks_dir" ]; then
    echo "Error: .git/hooks directory not found"
    exit 1
fi

# Check if git-hooks source directory exists
if [ ! -d "$git_hooks_source" ]; then
    echo "Error: scripts/git-hooks directory not found"
    exit 1
fi

# Install pre-push hook
pre_push_source="$git_hooks_source/pre-push"
pre_push_dest="$hooks_dir/pre-push"

if [ -f "$pre_push_source" ]; then
    echo "Installing pre-push hook..."
    cp "$pre_push_source" "$pre_push_dest"
    chmod +x "$pre_push_dest"
    echo "✓ pre-push hook installed"
else
    echo "Warning: pre-push hook source not found at $pre_push_source"
fi

echo ""
echo "Git hooks installation complete!"
echo ""
echo "The pre-push hook will automatically bump the patch version"
echo "when you push to the main or master branch."

