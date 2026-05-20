# B1 Mobile API

Laravel-integrated mobile API foundation for the Bismel1 customer app.

Source commit:

```text
Gusgraph/Bismel1.com@5234de9 api: add mobile customer foundation
```

Base route prefix:

```text
/api/mobile/v1
```

This repository contains the mobile API module files copied from the Laravel app:

- `routes/api.php`
- `app/Http/Controllers/Api/Mobile/*`
- `app/Http/Middleware/MobileApiAuthenticate.php`
- `app/Models/MobileAccessToken.php`
- `app/Models/MobileAuditLog.php`
- `app/Models/MobileSupportTicket.php`
- `app/Support/Mobile/MobileApiResponse.php`
- `database/migrations/2026_05_20_130000_create_mobile_api_foundation_tables.php`
- `tests/Feature/Api/Mobile/MobileApiFoundationTest.php`

The module depends on the main Bismel1 Laravel app models and services for account scoping, billing entitlements, broker account slots, trading records, and support data.
