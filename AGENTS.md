# AGENTS.md — Pharmacy Management System

You are working on an EXISTING Laravel 13 + Filament 5 + MySQL project.

## Absolute Rules

- Do NOT replace Laravel with another backend.
- Do NOT convert the project to Supabase.
- Do NOT build a React admin dashboard.
- Do NOT use ready-made dashboard templates.
- Do NOT change the database name.
- Do NOT edit existing migrations unless explicitly instructed.
- Any schema change must be added through a NEW migration.
- Do NOT touch .env, APP_KEY, database passwords, or secrets.
- Do NOT remove Filament.
- Do NOT change the project from Laravel Filament to another admin system.
- Do NOT implement Flutter now.
- Do NOT add heavy packages unless justified.
- Work in small phases.
- After each phase, list changed files and manual tests.

## Current Stack

- PHP 8.3.31
- Composer 2.8.9
- Laravel Framework 13.8.0
- Filament 5.6.3
- Livewire 4.3.0
- Laravel Sanctum 4.3.2
- MySQL 9.4.0
- Node.js 25.9.0
- npm 11.12.1
- Vite 8

## Active Project

Path:
`/Users/fangyuan/Desktop/LaravelProjects/Pharmacy_Manangemet`

Git:
- Main baseline commit exists.
- Current work should happen on feature branches.
- Current intended branch: `feature/basic-filament-resources`

Database:
- MySQL database name: `pharmacy_system`
- Existing migrations already run successfully.
- Do not rename the database.

## Current State

- `/admin` works.
- Filament AdminPanelProvider exists.
- No Filament Resources currently exist.
- Most Eloquent Models are empty except User.
- API currently only has simple UserController routes.
- Sanctum is installed but token login is not complete.
- Business logic is not implemented yet.

## Existing Tables

- users
- categories
- products
- suppliers
- purchase_invoices
- purchase_items
- product_batches
- sale_invoices
- sale_items
- stock_movements
- expenses
- personal_access_tokens
- Laravel system tables: cache, jobs, sessions, migrations, etc.

## Business Rules

- ProductBatch is the real stock source.
- FEFO must be used later for sales: first expiring batch should be sold first.
- Purchase invoices create purchase items and product batches.
- Sale invoices create sale items and decrease product batch quantities.
- StockMovement records inventory changes for purchase and sale operations.
- Profit depends on `sale_items.purchase_price_at_sale`.
- Products have a default sale price.
- Users have simple roles through `users.role`, not separate roles tables.

## Academic / Project Constraints

- Laravel Filament is allowed.
- Filament Resources, Forms, Tables, Widgets, Pages, and artisan generators are allowed.
- Ready-made dashboard templates are not allowed.
- Flutter is only for mobile later, not for the admin dashboard.
- Admin dashboard must be built with Laravel Filament.
- UI must be custom enough to show project work, not a copied template.

## Development Strategy

Build the project in 4 phases:

1. Foundation: Models, relationships, basic Filament CRUD.
2. Inventory and purchase flow.
3. Sales, FEFO, stock movements, and API auth.
4. Dashboard widgets, reports, polish, testing, and documentation.

Always start each phase in Plan Mode.
Only switch to Agent Mode after producing a file-by-file implementation plan.
