# NEXIAM Project Identity and Naming Policy

## Canonical product name

The canonical name of the project is **NEXIAM**.

NEXIAM is the active capstone product name, repository name, user-facing application name, documentation name, and architecture name.

The canonical GitHub repository is:

`daviddatuX25/NEXIAM`

## Predecessor name

**SecureCAT-v2** is the historical/predecessor name of the codebase. It may appear in:

- legacy planning records;
- historical manuscript revisions;
- audit evidence describing the migration from the predecessor system;
- old commits and original asset files;
- migration notes that explain how the system evolved.

It is not the active product name and must not be used for new user-facing labels, new documentation, new code identifiers, or new capstone artifacts.

## Naming rules

| Context | Canonical name |
|---|---|
| Product display name | NEXIAM |
| Repository | NEXIAM |
| Application display name | NEXIAM |
| Browser/page titles | NEXIAM |
| Email and notification branding | NEXIAM |
| Installation guide | NEXIAM |
| New scripts and conventions | NEXIAM |
| Architecture and OpenSpec documents | NEXIAM |
| Historical predecessor references | SecureCAT-v2, explicitly labeled predecessor |

## Compatibility identifiers

Some infrastructure identifiers may remain temporarily for safety:

- existing database names;
- Docker volume names;
- already-deployed hostnames;
- historical asset filenames;
- old migration and deployment records.

These identifiers are implementation compatibility details, not product branding. They must not be renamed casually because changing a database or persistent-volume identifier can create a new empty storage location instead of reusing existing data.

Any future infrastructure rename must be its own migration task with backup, restore, cutover, and rollback verification.

## Decision

From this point forward:

> NEXIAM is the product. SecureCAT-v2 is only the predecessor history.

New work, commits, specifications, screenshots, tests, emails, pages, and capstone writing should use NEXIAM.
