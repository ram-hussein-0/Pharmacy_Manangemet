# Phase 1 — Domain Flows, Filament Resources, and Testing Summary

## Project

Real project path:

    /Users/fangyuan/Desktop/LaravelProjects/Pharmacy_Manangemet

Current confirmed stack:

    PHP 8.3.31
    Laravel 13.8.0
    Filament 5.6.3
    Livewire 4.3.0
    Laravel Sanctum 4.3.2
    MySQL
    Main database: pharmacy_system
    Testing database: pharmacy_system_test
    Filament admin path: /admin

This project is a Laravel/Filament Pharmacy Management System.

Important domain entities currently covered:

    users
    categories
    suppliers
    products
    product_batches
    purchase_invoices
    purchase_items
    sale_invoices
    sale_items
    stock_movements
    expenses

---

## Important Safety Rules Followed

During this phase:

- No old migrations were edited.
- No migrate:fresh or migrate:rollback was used against pharmacy_system.
- Lovable migrations were not run against the real database.
- Lovable files were not copied blindly.
- Sensitive project files were not overwritten blindly:
  - app/Models/User.php
  - app/Providers/Filament/AdminPanelProvider.php
  - app/Providers/AppServiceProvider.php
  - routes/api.php
  - .env
  - existing migrations
- No ready-made admin template or theme was added.
- Only Laravel, Filament core Resources, Pages, Tables, Forms, Actions, and custom service logic were used.
- .env.testing is local-only and must not be committed.

---

## Core Business Rules Preserved

The following business rules were preserved and tested:

1. ProductBatch is the real source of stock.
2. Sales consume stock using FEFO: First Expiry, First Out.
3. Purchase stock changes go through PurchaseInvoiceService.
4. Sale stock changes go through SaleInvoiceService.
5. Stock movement audit rows are written when stock changes.
6. Profit uses sale_items.purchase_price_at_sale.
7. Historical profit must not change if product_batches.purchase_price changes later.
8. ProductBatchResource is read-only.
9. StockMovementResource is read-only.
10. SaleInvoiceResource is list/view only.
11. Sales are created through the custom NewSale page only.
12. Financial writes such as expenses go through services.

---

## Implemented and Committed Work

### Domain services

The following service layer was added or hardened:

    app/Exceptions/InsufficientStockException.php
    app/Services/InventoryService.php
    app/Services/PurchaseInvoiceService.php
    app/Services/SaleInvoiceService.php
    app/Services/StockMovementService.php
    app/Services/ExpenseService.php

Key behavior:

- PurchaseInvoiceService::create() creates purchase invoices and purchase items.
- PurchaseInvoiceService::complete() creates product batches and inbound stock movements.
- PurchaseInvoiceService::complete() is idempotent.
- SaleInvoiceService::create() applies FEFO, decrements batches, creates sale items, snapshots purchase price, and writes outbound stock movements.
- SaleInvoiceService::create() throws InsufficientStockException before writes if stock is short.
- ExpenseService::create() and ExpenseService::update() control financial expense writes.

---

## Filament Resources and Pages Added

### Catalog

    CategoryResource
    SupplierResource
    ProductResource

Note:

ProductResource/Pages/ListProducts.php, CreateProduct.php, and EditProduct.php did not exist in the Lovable transfer package even though ProductResource.php referenced them. They were manually created as simple compatibility/completion pages. They should be revisited later for possible UI/design improvements if needed.

### Inventory audit

    ProductBatchResource
    StockMovementResource

Both are read-only.

### Finance

    ExpenseResource

Create and edit operations go through ExpenseService.

### Purchases

    PurchaseInvoiceResource
    CreatePurchaseInvoice
    EditPurchaseInvoice
    ViewPurchaseInvoice
    ListPurchaseInvoices
    PurchaseItemsRelationManager

Important design:

- Purchase invoice creation goes through PurchaseInvoiceService::create().
- Completing a purchase invoice goes through PurchaseInvoiceService::complete().
- Completed invoices are locked from normal editing.
- Purchase items are displayed read-only after creation.

### Sales

    SaleInvoiceResource
    ListSaleInvoices
    ViewSaleInvoice
    SaleItemsRelationManager
    NewSale page
    resources/views/filament/pages/new-sale.blade.php

Important design:

- SaleInvoiceResource is read-only.
- No CreateSaleInvoice resource page exists.
- NewSale is the only Filament entry point for creating sales.
- NewSale calls SaleInvoiceService::create().
- The sale flow applies FEFO.

---

## Manual Domain Verification Completed

Manual tests were performed before writing PHPUnit tests.

### FEFO split sale

A product had two batches:

    BATCH-FEFO-NEAR-105821 | Expiry: 2026-07-01 | Qty: 4 | Cost: 30
    BATCH-001              | Expiry: 2026-08-06 | Qty: 7 | Cost: 25

A sale of quantity 5 consumed:

    4 units from BATCH-FEFO-NEAR-105821
    1 unit from BATCH-001

Result:

    FEFO split sale: PASSED
    purchase_price_at_sale snapshot: PASSED
    batch quantity decrement: PASSED
    stock movements per consumed batch: PASSED
    sale invoice profit display: PASSED

### Purchase complete idempotency

Calling PurchaseInvoiceService::complete() multiple times on an already completed purchase invoice did not duplicate:

    product_batches
    stock_movements

Result:

    Purchase complete idempotency: PASSED

### Insufficient stock rollback

Attempting to sell quantity 999 when only 6 units were available:

    did not create sale_invoice
    did not create sale_items
    did not create stock_movements
    did not change product_batches.quantity

Result:

    Insufficient stock rollback: PASSED

### Historical profit snapshot

Temporarily changing product_batches.purchase_price did not change:

    sale_items.purchase_price_at_sale

Result:

    Historical profit snapshot: PASSED

### Duplicate product lines

Selling the same product in two lines inside the same invoice correctly decremented stock by the total requested quantity.

Result:

    Duplicate product lines: PASSED

### Inventory checks

The following manual checks passed:

    Low stock detection: PASSED
    Stock value calculation: PASSED
    Expiring / expired batches service check: PASSED

---

## Testing Database Setup

A separate MySQL testing database was created:

    pharmacy_system_test

The following databases also exist on the machine but are unrelated to this project and must not be touched by this project:

    tenant_maqloba
    tenant_maqloba_analytics

The testing database was verified with Laravel:

    APP_ENV: testing
    DB_DATABASE config: pharmacy_system_test
    DB_DATABASE actual: pharmacy_system_test

Migrations were run only on:

    pharmacy_system_test

The main database pharmacy_system was not reset or migrated destructively.

The testing database can appear empty in phpMyAdmin after tests because the tests use database transactions and roll back test data.

---

## Important Testing Command

Because phpunit.xml currently forces SQLite memory:

    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>

domain tests must currently be run with explicit MySQL testing variables:

    APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pharmacy_system_test php artisan test tests/Feature/Domain

Do not run plain:

    php artisan test

for these domain tests until the test database configuration strategy is reviewed.

---

## PHPUnit Domain Tests Added

### SaleInvoiceServiceTest

File:

    tests/Feature/Domain/SaleInvoiceServiceTest.php

Covers:

    test_it_consumes_batches_using_fefo
    test_it_throws_when_stock_is_insufficient_without_side_effects

### PurchaseInvoiceServiceTest

File:

    tests/Feature/Domain/PurchaseInvoiceServiceTest.php

Covers:

    test_it_creates_batches_only_when_completed
    test_completing_twice_is_idempotent

### InventoryServiceTest

File:

    tests/Feature/Domain/InventoryServiceTest.php

Covers:

    test_low_stock_uses_product_batch_quantities
    test_total_stock_value_uses_batch_quantity_times_purchase_price
    test_expiring_and_expired_batches_are_detected

### ExpenseServiceTest

File:

    tests/Feature/Domain/ExpenseServiceTest.php

Covers:

    test_it_creates_expense_with_authenticated_user
    test_it_creates_expense_with_explicit_user_id
    test_it_updates_allowed_fields_without_changing_creator
    test_it_requires_authenticated_user_when_no_user_is_passed

---

## Final Domain Test Result

Command:

    APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=pharmacy_system_test php artisan test tests/Feature/Domain

Result:

    PASS  Tests\Feature\Domain\ExpenseServiceTest
    PASS  Tests\Feature\Domain\InventoryServiceTest
    PASS  Tests\Feature\Domain\PurchaseInvoiceServiceTest
    PASS  Tests\Feature\Domain\SaleInvoiceServiceTest

    Tests: 11 passed (58 assertions)

This confirms that the critical domain flows are currently protected by regression tests.

---

## Recent Relevant Commits

    0a304cb add expense service domain tests
    1765d90 add inventory service domain tests
    0bc5480 add purchase invoice service domain tests
    ceb1e71 add sale invoice service domain tests
    19c951c add new sale page with FEFO service flow
    94eb7c2 add read-only sale invoice resource
    3f8df25 harden sale invoice service FEFO flow
    79b4b23 add purchase invoice resource using service flow
    4ca4075 harden purchase invoice service completion flow
    1a58b12 add expense resource using expense service
    1bfdc89 add expense service for financial records
    dfb3d6e add read-only inventory audit resources

---

## Known Notes / Technical Debt

### ProductResource pages

The three ProductResource page classes were manually created because they were missing from the Lovable package. They are functional but may need UI review later.

### purchase_items and product_batches duplicated fields

There is duplicated information between:

    purchase_items.batch_number
    purchase_items.expiry_date
    product_batches.batch_number
    product_batches.expiry_date

This is known design debt. It was not changed in this phase. Currently:

- purchase_items stores purchase line input/snapshot data.
- product_batches represents materialized stock after purchase completion.

Review later only during a dedicated purchase flow/database design review.

### Expense type mismatch

There is a minor mismatch between:

    Expense::TYPES
    ExpenseResource form options

The model contains values such as salary and maintenance, while the form currently contains values such as salaries and supplies.

This did not block tests because tests used values accepted by the current schema/service, but it should be reviewed later.

### phpunit.xml testing database config

phpunit.xml currently uses SQLite memory. The domain tests currently require MySQL because the project schema and migrations are MySQL-oriented.

Possible later improvement:

- update phpunit.xml carefully, or
- add a dedicated composer script such as test:domain, or
- document the explicit command and keep phpunit.xml unchanged.

Do not change this without team review.

---

## Recommended Next Phase

The next phase should focus on one of these safe tracks:

### Option A — Reports and Dashboard

Add and review:

    Dashboard widgets
    SalesReport
    PurchaseReport
    InventoryReport
    ProfitLossReport
    LowStockAlerts
    ExpiryAlerts

This should use the already-tested services and read-only queries.

### Option B — User management and authorization

Review-merge:

    UserResource
    roles
    permissions / access checks
    Filament panel access

Be careful because User.php and user-related logic are sensitive.

### Option C — API layer for future Flutter app

Review-merge:

    AuthController
    CatalogController
    SaleApiController
    API Requests
    API Resources
    routes/api.php append-only

Do not overwrite routes/api.php.

### Option D — AI Assistant

Review-merge:

    IntentClassifier
    AiDatabaseAssistantService
    LlmClientService
    config/llm.php
    AiDatabaseAssistant Filament page

The AI assistant must remain intent-classifier-only and must never generate raw SQL from user input.
