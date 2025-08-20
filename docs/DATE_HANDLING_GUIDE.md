# Date Handling Best Practices for Remindy

This document outlines the best practices for handling dates in the Remindy application to prevent timezone-related issues and ensure consistent date behavior across the frontend and backend.

## Overview

The Remindy application deals primarily with **dates** (not times) for billing cycles, payment dates, and subscription dates. Since we're working with dates rather than specific times, timezone conversion can cause unexpected date shifts that lead to bugs.

## Key Principles

1. **Avoid unnecessary timezone conversions** when working with date-only values
2. **Use consistent date formatting** across frontend and backend
3. **Store dates in ISO format** (YYYY-MM-DD) in the database
4. **Use utility functions** for date conversion to ensure consistency

## Backend (PHP/Laravel) Best Practices

### Date Storage
- Always store dates in `date` columns (not `datetime`) when time is not relevant
- Use Laravel's date casting: `'payment_date' => 'date'`
- Store dates in ISO format (YYYY-MM-DD)

### Date Formatting in Emails
```php
// ❌ WRONG - Can cause timezone shifts
$formattedDate = $dueDate->setTimezone('UTC')->format($format);

// ✅ CORRECT - No timezone conversion for date-only formatting
$formattedDate = $dueDate->format($format);
```

### Using DateHelper
```php
// Use the centralized DateHelper for consistent formatting
use App\Helpers\DateHelper;

$formattedDate = DateHelper::formatDate($date, $userFormat);
$dbDate = DateHelper::formatForDatabase($date);
```

### Carbon Date Handling
```php
// When creating Carbon instances from date strings
$date = Carbon::parse($dateString); // Safe for date-only strings
$date = Carbon::createFromFormat('Y-m-d', $dateString); // More explicit
```

## Frontend (JavaScript/TypeScript) Best Practices

### Date Conversion Issues
```javascript
// ❌ WRONG - Can cause timezone shifts
const date = new Date(dateString);
const formatted = date.toISOString().split('T')[0];

// ✅ CORRECT - Use utility functions
import { getTodayString, toDateString } from '@/lib/utils';

const today = getTodayString();
const formatted = toDateString(dateInput);
```

### Date Picker Components
```typescript
// ✅ Use the provided utility functions
<DatePickerInput
    value={data.payment_date}
    max={getTodayString()}
    onChange={(value) => setData('payment_date', value)}
/>
```

### Form Initialization
```typescript
// ✅ CORRECT - Use utility function
const { data, setData } = useForm({
    payment_date: getTodayString(),
    // ...
});

// ❌ WRONG - Can cause timezone issues
const { data, setData } = useForm({
    payment_date: new Date().toISOString().split('T')[0],
    // ...
});
```

## Utility Functions

### Backend (PHP)
Located in `app/Helpers/DateHelper.php`:
- `DateHelper::formatDate($date, $format)` - Format date with user preferences
- `DateHelper::formatForDatabase($date)` - Format for database storage
- `DateHelper::formatDateTime($datetime)` - Format datetime values

### Frontend (JavaScript/TypeScript)
Located in `resources/js/lib/utils.ts`:
- `getTodayString()` - Get today's date as YYYY-MM-DD
- `toDateString(dateInput)` - Convert any date input to YYYY-MM-DD safely
- `formatDate(date, format)` - Format date with specified format

## Common Pitfalls to Avoid

### 1. JavaScript Date Constructor with Date Strings
```javascript
// ❌ WRONG - Interprets as local time, then converts to UTC
new Date('2025-08-10').toISOString().split('T')[0]
// Can return '2025-08-09' depending on timezone

// ✅ CORRECT - Use utility function
toDateString('2025-08-10') // Always returns '2025-08-10'
```

### 2. Carbon Timezone Conversion for Dates
```php
// ❌ WRONG - Can shift the date
$date->setTimezone('UTC')->format('Y-m-d')

// ✅ CORRECT - Direct formatting without timezone conversion
$date->format('Y-m-d')
```

### 3. Form Date Initialization
```typescript
// ❌ WRONG - Timezone dependent
start_date: new Date().toISOString().split('T')[0]

// ✅ CORRECT - Timezone safe
start_date: getTodayString()
```

## Testing Date Handling

Always test date handling with:
1. Different user timezones
2. Edge cases (end of month, leap years)
3. Date persistence (save and reload)
4. Email formatting

Example test:
```php
public function test_payment_date_preserves_without_timezone_shift()
{
    $originalDate = '2025-08-10';
    $payment = PaymentHistory::create(['payment_date' => $originalDate]);
    
    $this->assertEquals($originalDate, $payment->payment_date->format('Y-m-d'));
}
```

## Migration Guide

When updating existing code:

1. **Replace `toISOString().split('T')[0]`** with `getTodayString()` or `toDateString()`
2. **Remove timezone conversions** from date-only formatting
3. **Use utility functions** instead of manual date manipulation
4. **Add tests** to verify date handling works correctly

## Checklist for New Features

- [ ] Use utility functions for date conversion
- [ ] Avoid timezone conversion for date-only values
- [ ] Test with different timezones
- [ ] Use consistent date formats
- [ ] Document any special date handling requirements
