# Known Issues & Technical Debt

This document tracks known issues, bugs, and technical debt items that need to be addressed.

---

## 🔴 Critical Issues

### Database: `hiddenLocation` Column Too Small

**Status**: 🔴 Needs Fix  
**Discovered**: June 2024  
**Priority**: High  
**Impact**: Users cannot save location descriptions longer than 255 characters

#### Problem

The `hiddenLocation` column in the `locations` table is defined as `VARCHAR(255)`, but users frequently need to enter longer descriptions for hidden/virtual event locations (e.g., "This production has 3 pieces: an online questionnaire, a live phone call and a live experience. Participants will receive all necessary information...").

When text exceeds 255 characters, the database throws:
```
SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'hiddenLocation' at row 1
```

#### Error Location
- **Table**: `locations`
- **Column**: `hiddenLocation`
- **Controller**: `App\Http\Controllers\Create\LocationController.php` (line 59)
- **Model**: `App\Models\Location.php` (line 38)

#### Solution

Create a migration to change the column type from `VARCHAR(255)` to `TEXT`:

```php
// Create migration
php artisan make:migration increase_hidden_location_column_length

// In the migration file:
public function up()
{
    Schema::table('locations', function (Blueprint $table) {
        $table->text('hiddenLocation')->nullable()->change();
    });
}

public function down()
{
    Schema::table('locations', function (Blueprint $table) {
        $table->string('hiddenLocation', 255)->nullable()->change();
    });
}
```

**Requirements**:
- Install `doctrine/dbal` package: `composer require doctrine/dbal`
- Run migration on production after testing locally

---

## 🟡 Medium Priority Issues

_To be added as discovered_

---

## 🟢 Low Priority / Technical Debt

_To be added as discovered_

---

## ✅ Resolved Issues

_Issues will be moved here once fixed with date and details_

---

**Last Updated**: November 7, 2025  
**Maintained By**: Development Team

