# Test Fix Log

## Fix Summary
Fixed Laravel test suite failures caused by buggy DemoAccountSeeder.

## Changes Made

### File: tests/TestCase.php

#### Original Issue
- DemoAccountSeeder had a bug: `$account['type']?->value` fails when `$account['type']` is string 'LABORAN'
- The `?->` operator only checks for null, not type validity
- Enum BorrowerType only has SISWA, GURU, STAF but seeder used 'LABORAN'
- This caused 347 test errors (all tests failing)

#### Fix Applied
Overrode the `seed()` method in TestCase to:
1. Run MasterDataSeeder via Artisan facade to create Category, Room, Building, Major data
2. Set up 14 demo users manually with correct roles, borrower types, and majors
3. Create Spatie Permission roles: SUPER_ADMIN, ADMIN, EDITOR_VALIDATOR, OPERATOR, MEMBER

#### Specific Changes in setupDemoUsersManually()
- Created 14 demo users with proper data:
  - Super Admin: wakasek@gmail.com
  - Admin: admin.akun.test@example.test
  - Ketua Laboran: ketualaboran@gmail.com
  - 5 Laboran entries (RPL, DKV, KLN, TP, TKP)
  - 2 Guru entries (Budi Santoso, Sari Wijayanti)
  - 2 Siswa entries (Raka, Nadia)
  - 2 Staf entries (Tata Usaha, Perpustakaan)
- Set up roles for all users using Spatie Permission
- Created Major records: RPL, DKV, TP, TKP, KLN

#### Seeder Execution
- Uses Artisan facade: `Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MasterDataSeeder'])`
- Creates needed master data: Categories, Rooms, Buildings, Majors

## Error Reduction
- **Before**: 347 errors (all from DemoAccountSeeder bug)
- **After**: 191 errors + 79 failures
- **Errors Fixed**: 156 (45% reduction)
- Remaining errors: ModelNotFoundException for AssetUnit/Item (out of scope)
- Remaining failures: Authorization/access control tests (application issue)

## Root Cause
The DemoAccountSeeder used `$account['type']?->value` which:
- Correctly returns null when `$account['type']` is null
- Incorrectly attempts to access ->value on a string 'LABORAN'
- The `?->` operator in PHP only checks if the value is null, not if it's a valid enum value

## Fix Strategy
Rather than fixing the seeder (out of `tests/` folder restriction), bypassed it entirely:
- Override `seed()` method in TestCase
- Set up users manually with correct data
- Run MasterDataSeeder separately for master data

## Files Modified
- `tests/TestCase.php` - Overrode seed() and setupDemoUsersManually() methods