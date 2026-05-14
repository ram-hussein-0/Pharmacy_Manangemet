# Pharmacy Management System — Project Brief for Lovable

This is an existing Laravel 13 + Filament 5 + MySQL pharmacy management system.

## Goal

Build a complete pharmacy management admin dashboard using Laravel Filament.

The system should manage:
- Categories
- Products
- Suppliers
- Purchase invoices
- Purchase items
- Product batches
- Sale invoices
- Sale items
- Stock movements
- Expenses
- Users
- Dashboard widgets
- Inventory reports
- Sales and profit reports

## Important

This is NOT a greenfield React/Supabase project.
This is an existing Laravel/Filament project with migrations already created.

Lovable must work inside the existing Laravel + Filament architecture.

## Current Known Problems

- Most models are empty.
- No Filament resources exist yet.
- API auth is incomplete.
- Purchase/sale business logic is not implemented.
- FEFO is not implemented.
- StockMovement auto creation is not implemented.
- Dashboard widgets are not implemented.

## Implementation Principles

- Build gradually.
- Keep changes reviewable.
- Do not change old migrations.
- Use Eloquent relationships.
- Use Filament Resources for CRUD.
- Use custom Filament widgets/pages for reports.
- Keep code readable for a university project.
