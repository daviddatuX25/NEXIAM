const { spawnSync } = require('child_process');

console.log('Starting Clean CTXPKG Packaging Script (Project Identity Only)...');

// Clean project identity fact
const category = 'guidelines';
const key = 'project-identity';
const value = `# SecureCAT-v2 Project Conventions

## Project Identity & Purpose
- **Project Name:** SecureCAT-v2
- **Type:** BSIT Capstone Project (Exam Management & Proctoring System)
- **Roadmap & Guidelines:** Located in the \`capstone/\` directory (refer to \`capstone/README.md\`)

## Environment & Tech Stack
- **OS Environment:** Linux
- **Language:** PHP 8.4
- **Backend Framework:** Laravel 12 (Middleware declared in \`bootstrap/app.php\`, casts defined in \`casts()\` methods)
- **Frontend Stack:** Inertia.js v2 (Svelte adapter), Svelte 5, Tailwind CSS v4, shadcn-svelte
- **Database:** MySQL

## Core Principles
1. **Contract over Creativity:** Implement strictly following specifications in \`docs/architecture/\` and design documents, not imagination.
2. **Quality over Speed:** Write clean, self-documenting code. Do not leave placeholders or TODOs.
3. **Ask Before Assuming:** Ask clarifying questions up front to avoid backtracking or incorrect assumptions.
4. **Test-First for Behavior:** Write tests to cover happy paths, failure paths, and edge cases.

## Tools & Commands
- **Testing:** PHPUnit (Pest is not used). Run tests using \`php artisan test --compact\` (with \`--filter\` or filename target to keep it fast).
- **Code Style & Formatting:** Laravel Pint. Format dirty files before finalizing with: \`vendor/bin/pint --dirty --format agent\`
`;

// Utility to run a command and log output (safely using spawnSync)
console.log(`Registering project identity fact: ${category}/${key}`);
const result = spawnSync('lean-ctx', [
  'knowledge',
  'remember',
  value,
  '--category', category,
  '--key', key
], { encoding: 'utf8' });

if (result.status !== 0) {
  console.error(`Failed to remember: ${category}/${key}`, result.stderr);
  process.exit(1);
} else {
  console.log(`Successfully remembered: ${category}/${key}`);
}

// Build Graph and Export Package
console.log('Rebuilding Code Graph...');
const graphResult = spawnSync('lean-ctx', ['graph', 'build'], { stdio: 'inherit' });
if (graphResult.status !== 0) {
  console.error('Failed to build graph');
  process.exit(1);
}

console.log('Creating Context Package...');
const packResult = spawnSync('lean-ctx', ['pack', 'create', '--name', 'securecat-conventions'], { stdio: 'inherit' });
if (packResult.status !== 0) {
  console.error('Failed to create pack');
  process.exit(1);
}

console.log('Exporting Context Package...');
const exportResult = spawnSync('lean-ctx', ['pack', 'export', 'securecat-conventions'], { stdio: 'inherit' });
if (exportResult.status !== 0) {
  console.error('Failed to export pack');
  process.exit(1);
}

console.log('CTXPKG Clean Packaging Complete!');
