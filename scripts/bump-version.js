#!/usr/bin/env node

/**
 * Version bump script for package.json
 * Usage: node scripts/bump-version.js [patch|minor|major]
 */

const fs = require('fs');
const path = require('path');

// Get version type from command line argument
const versionType = process.argv[2] || 'patch';

// Validate version type
if (!['patch', 'minor', 'major'].includes(versionType)) {
  console.error(`Invalid version type: ${versionType}`);
  console.error('Usage: node scripts/bump-version.js [patch|minor|major]');
  process.exit(1);
}

// Path to package.json
const packageJsonPath = path.join(__dirname, '..', 'package.json');

// Read package.json
let packageJson;
try {
  const packageJsonContent = fs.readFileSync(packageJsonPath, 'utf8');
  packageJson = JSON.parse(packageJsonContent);
} catch (error) {
  console.error(`Error reading package.json: ${error.message}`);
  process.exit(1);
}

// Parse current version
const currentVersion = packageJson.version;
const versionParts = currentVersion.split('.').map(Number);

if (versionParts.length !== 3 || versionParts.some(isNaN)) {
  console.error(`Invalid version format: ${currentVersion}`);
  console.error('Expected format: MAJOR.MINOR.PATCH (e.g., 1.0.0)');
  process.exit(1);
}

let [major, minor, patch] = versionParts;

// Increment version based on type
switch (versionType) {
  case 'major':
    major++;
    minor = 0;
    patch = 0;
    break;
  case 'minor':
    minor++;
    patch = 0;
    break;
  case 'patch':
    patch++;
    break;
}

// Create new version string
const newVersion = `${major}.${minor}.${patch}`;

// Update package.json
packageJson.version = newVersion;

try {
  // Write back to package.json with proper formatting (2 spaces indentation)
  fs.writeFileSync(
    packageJsonPath,
    JSON.stringify(packageJson, null, 2) + '\n',
    'utf8'
  );
  
  console.log(`Version bumped: ${currentVersion} → ${newVersion} (${versionType})`);
  console.log(newVersion);
} catch (error) {
  console.error(`Error writing package.json: ${error.message}`);
  process.exit(1);
}

