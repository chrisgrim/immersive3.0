# Event Creation Dates Section - Focused Audit Report

**Date**: October 14, 2025  
**Scope**: Complete Vue/Laravel event creation dates flow  
**Reviewer**: AI Code Review

---

## Executive Summary

Comprehensive audit of the Everything Immersive event creation dates system, covering all three date modes (Specific, Ongoing, Always), their Vue components, Laravel backend processing, and database interactions.

### Quick Status
- ✅ **Backend Logic**: Solid
- ⚠️ **Frontend State Management**: Some concerns
- ⚠️ **Timezone Handling**: Potential edge cases
- ✅ **Validation**: Good coverage
- ⚠️ **User Experience**: Minor issues

---

## Architecture Overview

### Date Modes
1. **Specific Dates** (`s`) - User selects individual dates via calendar
2. **Ongoing/Recurring** (`o`) - User selects days of the week with start/end dates
3. **Always Available** (`a`) - Event always searchable until specified end date

### Key Files
- **Frontend**: 
  - `resources/js/PageComponents/Creation/Core/Pages/dates.vue` (parent coordinator)
  - `resources/js/PageComponents/Creation/Core/Pages/Dates/specific-dates.vue`
  - `resources/js/PageComponents/Creation/Core/Pages/Dates/ongoing-dates.vue`
  - `resources/js/PageComponents/Creation/Core/Pages/Dates/always-dates.vue`

- **Backend**:
  - `app/Http/Controllers/Creation/HostEventController.php`
  - `app/Models/Events/Show.php`

---

## Critical Issues 🔴

### None Found
No critical blocking issues identified that would prevent core functionality.

---

## High Priority Issues ⚠️

### 1. Timezone Conversion Inconsistencies

**Location**: Multiple components  
**Issue**: Inconsistent timezone handling between frontend and backend could lead to date shifts.

**Examples**:

#### `specific-dates.vue` Line 440-444:
```javascript
const normalizeDate = (d) => {
    const date = new Date(d);
    date.setHours(12, 0, 0, 0); // Always set to noon to avoid timezone issues
    return date.toISOString().split('T')[0];
};
```

#### `ongoing-dates.vue` Line 851-854:
```javascript
const [year, month, day] = dateStr.split('-');
return new Date(year, month - 1, day, 12, 0, 0); // Set to noon to avoid timezone issues
```

#### `Show.php` Line 105:
```php
$formattedDate = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
```

**Impact**: 
- Users in different timezones might see dates shifted by ±1 day
- Embargo dates could activate at wrong times
- Show dates could be off by a day in certain timezone combinations

**Recommendation**:
```javascript
// Frontend: Use explicit timezone handling
const normalizeDate = (d, timezone) => {
    const moment = require('moment-timezone');
    return moment.tz(d, timezone).format('YYYY-MM-DD');
};
```

```php
// Backend: Accept and respect frontend timezone
$formattedDate = Carbon::parse($date, $request->timezone ?? 'UTC')
    ->setTimezone('UTC')
    ->format('Y-m-d H:i:s');
```

**Severity**: HIGH  
**Effort**: Medium (2-3 hours)

---

### 2. State Preservation Logic Complexity

**Location**: `dates.vue` Lines 211-268  
**Issue**: Complex state preservation logic when switching between date modes could lose data.

**Code**:
```javascript
const setOngoingDates = () => {
    // Note: Don't save specific dates state here because handleBackToSelection already did it
    // if we came from the selection screen. If we came directly from specific mode,
    // the state is already being tracked via handleSpecificDatesUpdated
    
    console.log('Switching to ongoing dates:', {
        previousShowtype: event.showtype,
        ongoingDatesState: ongoingDatesState.value,
        currentSelectedDates: selectedDates.value,
        specificDatesState: specificDatesState.value
    });
    
    event.showtype = 'o';
    // ... more logic
};
```

**Problems**:
1. Comments suggest uncertainty about when state is saved
2. Multiple code paths could lead to inconsistent state
3. setTimeout used for state restoration (fragile timing dependency)

**Example Edge Case**:
```
User flow:
1. Select specific dates
2. Switch to ongoing
3. Switch to always
4. Switch back to specific <- might lose original dates
```

**Recommendation**:
```javascript
// Centralize state management
const stateMachine = {
    's': specificDatesState,
    'o': ongoingDatesState,
    'a': alwaysDatesState
};

const saveCurrentState = () => {
    const currentMode = event.showtype;
    if (!currentMode) return;
    
    if (currentMode === 's' && specificDatesRef.value) {
        stateMachine.s.value = specificDatesRef.value.getDates();
    }
    // ... similar for other modes
};

const switchMode = (newMode) => {
    saveCurrentState();
    event.showtype = newMode;
    nextTick(() => restoreState(newMode));
};
```

**Severity**: HIGH  
**Effort**: High (4-5 hours)

---

### 3. Ongoing Dates Reconstruction from Existing Data

**Location**: `ongoing-dates.vue` Lines 845-907 (reconstructFromDates)  
**Issue**: Heuristic-based reconstruction of weekly pattern from saved dates is fragile.

**Code**:
```javascript
reconstructFromDates: (dates) => {
    // ...
    // Count occurrences of each day of the week
    parsedDates.forEach(date => {
        const dayOfWeek = date.getDay();
        dayFrequency[dayOfWeek] = (dayFrequency[dayOfWeek] || 0) + 1;
    });
    
    // Determine which days were selected
    const minOccurrences = totalDates <= 7 ? 1 : 2;
    
    const reconstructedDays = Object.keys(dayFrequency)
        .filter(day => dayFrequency[day] >= minOccurrences)
        .map(day => parseInt(day))
        .sort();
```

**Problems**:
1. If user created Monday/Wednesday ongoing event, then deleted some future Mondays, the reconstruction might not correctly identify the pattern
2. `minOccurrences` threshold is arbitrary (2 for >7 dates)
3. No way to distinguish between specific dates that happen to fall on same weekday vs. intentional recurring pattern

**Example Failure**:
```
Original: Ongoing every Monday/Wednesday
User action: Delete 3 future Mondays manually
Reload: System might drop Monday from the pattern
```

**Recommendation**:
Store the original configuration separately in database:
```php
// Add to events table migration
$table->json('showtype_config')->nullable();

// Store original pattern
'showtype_config' => [
    'type' => 'ongoing',
    'days_of_week' => [1, 3], // Mon, Wed
    'start_date' => '2025-10-15',
    'end_date' => '2026-04-15'
]
```

**Severity**: HIGH  
**Effort**: High (6-8 hours with migration)

---

## Medium Priority Issues ⚡

### 4. Race Condition in Date Submission

**Location**: `dates.vue` Line 386-451 (submitData)  
**Issue**: Multiple async operations without proper coordination

**Code**:
```javascript
submitData: () => {
    let formattedDates = [];
    
    // Get dates based on the showtype
    if (event.showtype === 'o' && ongoingDatesRef.value) {
        const ongoingDates = ongoingDatesRef.value.getDates();
        formattedDates = ongoingDates.map(date => 
            new Date(date).toISOString().slice(0, 19).replace('T', ' ')
        );
    } else if (event.showtype === 'a' && alwaysDatesRef.value) {
        const alwaysDates = alwaysDatesRef.value.getDates();
        formattedDates = alwaysDates.map(date => 
            new Date(date).toISOString().slice(0, 19).replace('T', ' ')
        );
    }
    // ...
```

**Problem**: 
- Component refs might not be ready when `submitData()` is called
- No error handling if `getDates()` fails
- Timezone conversion happens client-side with potential drift

**Recommendation**:
```javascript
submitData: async () => {
    try {
        // Ensure component is mounted
        await nextTick();
        
        if (!ongoingDatesRef.value) {
            throw new Error('Component not ready');
        }
        
        // ... rest of logic with error handling
    } catch (error) {
        console.error('Date submission failed:', error);
        throw error;
    }
}
```

**Severity**: MEDIUM  
**Effort**: Low (1-2 hours)

---

### 5. Six-Month Calculation Inconsistency

**Location**: Multiple files  
**Issue**: Different methods of calculating "6 months from now"

**Examples**:

**Frontend** (`ongoing-dates.vue` Line 583-589):
```javascript
const maxDate = endDate.value 
    ? new Date(endDate.value)
    : (() => {
        const sixMonthsFromNow = new Date();
        sixMonthsFromNow.setMonth(sixMonthsFromNow.getMonth() + 6);
        sixMonthsFromNow.setHours(23, 59, 59, 999);
        return sixMonthsFromNow;
    })();
```

**Backend** (`Show.php` Lines 183-184):
```php
// Default for always shows: 6 months from now
return Carbon::now()->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
```

**Problem**:
- JavaScript `setMonth()` vs PHP `addMonths()` handle edge cases differently
- Example: Adding 6 months to August 31 → February 31 → March 2/3 (depending on implementation)
- Timezone differences between server and client

**Recommendation**:
Use consistent library on both sides:
```javascript
// Frontend - use moment
const sixMonthsLater = moment().add(6, 'months').endOf('day');
```

```php
// Backend - Carbon is already consistent
Carbon::now()->addMonths(6)->endOf('day')
```

Add validation to ensure both produce same result.

**Severity**: MEDIUM  
**Effort**: Low (1 hour)

---

### 6. Lack of Optimistic Updates

**Location**: All date components  
**Issue**: No UI feedback while dates are being saved

**Current Flow**:
1. User clicks "Next" or "Save"
2. UI waits for server response
3. No loading state or skeleton screen
4. Could feel frozen on slow connections

**Recommendation**:
```javascript
const isSubmitting = ref(false);

const submitData = async () => {
    isSubmitting.value = true;
    
    try {
        const data = prepareData();
        await axios.post('/api/events/dates', data);
        // Success
    } catch (error) {
        // Revert optimistic update
    } finally {
        isSubmitting.value = false;
    }
};
```

**Severity**: MEDIUM  
**Effort**: Medium (2-3 hours)

---

## Low Priority Issues 📝

### 7. Console.log Statements in Production

**Location**: Multiple files  
**Count**: 15+ console.log statements

**Examples**:
- `dates.vue` Line 217: `console.log('Switching to specific dates:', ...)`
- `ongoing-dates.vue` Line 586: `console.log('Frontend 6-month calculation:', ...)`

**Recommendation**:
Use environment-aware logging:
```javascript
const logger = {
    log: (...args) => {
        if (import.meta.env.DEV) {
            console.log(...args);
        }
    }
};
```

**Severity**: LOW  
**Effort**: Low (30 minutes)

---

### 8. Magic Numbers and Strings

**Location**: Throughout codebase  
**Issue**: Hard-coded values without constants

**Examples**:
- `500` character limit (multiple places)
- `12` months for admins
- `6` months default duration
- `'s'`, `'o'`, `'a'`, `'l'` showtype codes

**Recommendation**:
```javascript
// constants.js
export const SHOWTYPES = {
    SPECIFIC: 's',
    ONGOING: 'o',
    ALWAYS: 'a',
    LIMITED: 'l'
};

export const LIMITS = {
    SHOW_TIMES_MAX_LENGTH: 500,
    DEFAULT_DURATION_MONTHS: 6,
    ADMIN_MAX_MONTHS: 12
};
```

**Severity**: LOW  
**Effort**: Medium (2 hours)

---

### 9. Incomplete Error Messages

**Location**: `specific-dates.vue`, `ongoing-dates.vue`  
**Issue**: Generic validation messages don't guide users

**Current**:
```javascript
errors.value = { dates: ['Please select at least one date'] };
```

**Better**:
```javascript
errors.value = { 
    dates: [
        'Please select at least one date to continue.',
        'Tip: Click on the calendar to add your first show date.'
    ] 
};
```

**Severity**: LOW  
**Effort**: Low (1 hour)

---

### 10. Missing Accessibility Features

**Location**: All date picker components  
**Issues**:
- Calendar modals lack proper ARIA labels
- No keyboard navigation instructions
- Screen reader announcements missing

**Recommendation**:
```vue
<VueDatePicker
    ref="calendarRef"
    v-model="date"
    aria-label="Select event dates"
    :aria-describedby="datePickerHelp"
    role="dialog"
    aria-modal="true"
/>

<div id="datePickerHelp" class="sr-only">
    Use arrow keys to navigate calendar.
    Press Enter to select a date.
    Press Escape to close.
</div>
```

**Severity**: LOW  
**Effort**: Medium (3-4 hours)

---

## Positive Findings ✅

### What's Working Well

1. **Validation Coverage**
   - Good client-side validation with Vuelidate
   - Server-side validation via `StoreEventRequest`
   - Edge cases like max character limits handled

2. **Component Isolation**
   - Clean separation between date modes
   - Well-defined `defineExpose()` APIs
   - Reusable across creation and edit modes

3. **State Preservation Intent**
   - Clear attempt to preserve user data when switching modes
   - Prevents frustrating data loss

4. **Backend Delete Logic**
   - Proper cascading deletes for tickets
   - Handles showtype transitions correctly
   - Maintains database integrity

5. **Timezone Awareness**
   - System attempts to respect user timezones
   - Uses moment-timezone for comprehensive coverage

---

## Testing Recommendations

### Critical Test Cases

#### 1. Timezone Edge Cases
```javascript
test('date preserves across timezone boundaries', async () => {
    // User in Tokyo selects October 15, 2025
    const selectedDate = '2025-10-15';
    const timezone = 'Asia/Tokyo';
    
    // Submit to backend
    const response = await submitDate(selectedDate, timezone);
    
    // Verify backend stored correct date
    expect(response.data.shows[0].date).toBe('2025-10-15 00:00:00');
    
    // User in New York loads same event
    const loadedDate = await loadDate(response.data.id, 'America/New_York');
    
    // Should still show October 15
    expect(loadedDate).toBe('2025-10-15');
});
```

#### 2. State Preservation
```javascript
test('preserves dates when switching modes', async () => {
    // Select specific dates
    await selectSpecificDates(['2025-10-15', '2025-10-16']);
    
    // Switch to ongoing
    await switchToOngoing();
    await selectDays([1, 3]); // Monday, Wednesday
    
    // Switch back to specific
    await switchToSpecific();
    
    // Should still have original dates
    expect(getSelectedDates()).toEqual(['2025-10-15', '2025-10-16']);
});
```

#### 3. Ongoing Dates Reconstruction
```javascript
test('correctly reconstructs ongoing pattern', async () => {
    // Create ongoing event: Every Monday for 3 months
    await createOngoingEvent({
        days: [1], // Monday
        startDate: '2025-10-14',
        endDate: '2026-01-14'
    });
    
    // Reload event
    const reconstructed = await loadEvent();
    
    // Should identify Monday pattern
    expect(reconstructed.config.days).toEqual([1]);
    expect(reconstructed.shows.length).toBe(13); // ~13 Mondays in 3 months
});
```

#### 4. Embargo Date Handling
```javascript
test('embargo date affects event visibility', async () => {
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 7);
    
    // Create event with embargo
    const event = await createEvent({
        embargoDate: futureDate,
        status: 'e' // embargoed
    });
    
    // Should not be searchable
    const searchResults = await searchEvents();
    expect(searchResults).not.toContainObject(event);
    
    // Advance time past embargo
    jest.advanceTimersByTime(8 * 24 * 60 * 60 * 1000);
    
    // Should now be searchable
    const newResults = await searchEvents();
    expect(newResults).toContainObject(event);
});
```

#### 5. Six-Month Calculation
```javascript
test('six-month calculation handles edge dates', () => {
    // Edge case: January 31
    const start = new Date('2025-01-31');
    const sixMonthsLater = addSixMonths(start);
    
    // Should be July 31, not August 1
    expect(sixMonthsLater.toISOString().slice(0, 10)).toBe('2025-07-31');
    
    // Edge case: August 31
    const augustStart = new Date('2025-08-31');
    const februaryEnd = addSixMonths(augustStart);
    
    // February has fewer days - should be Feb 28 or March 2?
    // Document expected behavior
    expect(februaryEnd.getMonth()).toBe(2); // March
    expect(februaryEnd.getDate()).toBe(2); // March 2 (JS behavior)
});
```

---

## Performance Considerations

### Current Performance

#### Database Queries
```php
// HostEventController.php Line 33
$event->load([
    'shows.tickets',
    'location',
    'contentAdvisories',
    // ... 12 more relationships
]);
```

**Analysis**:
- ✅ Good: Eager loading prevents N+1 queries
- ⚠️ Potential issue: Loading all tickets for all shows could be heavy for long-running events

**Recommendation**:
```php
$event->load([
    'shows' => function($query) {
        $query->orderBy('date', 'ASC')
              ->limit(100); // Paginate if needed
    },
    'shows.tickets'
]);
```

#### Frontend Rendering
```javascript
// specific-dates.vue generates up to 12 months of calendars
displayedMonths.value = 12; // for admins
```

**Impact**:
- 12 months × ~30 days = ~360 DOM elements per calendar
- Could cause lag on mobile devices

**Recommendation**:
```javascript
// Lazy load calendar months
const visibleMonths = ref(3);

const loadMoreMonths = () => {
    if (visibleMonths.value < 12) {
        visibleMonths.value += 3;
    }
};
```

---

## Security Considerations

### 1. Admin Bypass for Past Dates

**Location**: `specific-dates.vue` Lines 343-352

```javascript
const minDate = computed(() => {
    // Admins can select past dates (up to 1 year ago)
    if (isAdmin.value) {
        const pastDate = new Date();
        pastDate.setFullYear(pastDate.getFullYear() - 1);
        return pastDate;
    }
    return new Date();
});
```

**Security Check**: ✅ Validated server-side?

**Verification Needed**: Check if backend validates admin permissions:
```php
// HostEventController should verify
if ($request->dateArray contains past dates) {
    if (!auth()->user()->isAdmin()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
}
```

**Severity**: MEDIUM if missing  
**Effort**: Low (30 minutes)

---

## Recommendations Summary

### Immediate Actions (Before Push)

1. ✅ **Add server-side timezone validation**
   - Ensure dates don't shift across timezones
   - Test with Tokyo, New York, London timezones

2. ✅ **Add error handling to submitData()**
   - Prevent silent failures
   - Give users clear feedback

3. ✅ **Test state preservation manually**
   - Switch between all three modes
   - Verify no data loss

4. ✅ **Verify admin past-date authorization**
   - Security check

### Short Term (Next Sprint)

5. ⚡ Refactor state management to centralized system
6. ⚡ Add loading states for better UX
7. ⚡ Store showtype_config in database for reliable reconstruction
8. ⚡ Remove console.log statements

### Long Term (Future Improvements)

9. 📝 Implement comprehensive date picker accessibility
10. 📝 Add end-to-end tests for date flows
11. 📝 Performance optimization for long-running events
12. 📝 Create constants file for magic numbers

---

## Testing Checklist Before Push

### Manual Testing

- [ ] Create event with specific dates in Tokyo timezone, verify in New York
- [ ] Create ongoing event (Mon/Wed), edit it, verify pattern preserved
- [ ] Create always-available event, verify 6-month end date
- [ ] Switch between all three modes, verify no data loss
- [ ] Submit event with embargo date, verify it's not searchable
- [ ] Test as regular user (should NOT be able to select past dates)
- [ ] Test as admin (SHOULD be able to select past dates)
- [ ] Test on mobile device (responsive calendar)
- [ ] Test with slow network (check loading states)

### Code Review

- [ ] Grep for console.log, remove or wrap in dev check
- [ ] Verify all API calls have error handling
- [ ] Check that timezone is passed to backend
- [ ] Verify `StoreEventRequest` validates dates
- [ ] Confirm `Show::saveShows()` handles all three modes

### Database

- [ ] Verify shows are created correctly in DB
- [ ] Check closingDate is set properly
- [ ] Confirm embargo_date is saved
- [ ] Test cascade delete (deleting event removes shows)

---

## Conclusion

Your dates section is **fundamentally sound** with good separation of concerns and comprehensive feature coverage. The main risks are:

1. **Timezone handling** - Could cause date shifts for international users
2. **State preservation** - Complex logic with potential edge cases
3. **Ongoing reconstruction** - Fragile heuristic-based approach

**Recommendation**: Safe to push with manual testing of the checklist above. Address timezone and state management issues in next sprint.

**Overall Grade**: B+ (Good, with room for improvement)

---

## Questions to Ask Yourself

1. Have you tested with users in different timezones?
2. What happens if a user has 200+ dates selected? (Performance)
3. How do you handle daylight saving time transitions?
4. What if the user's browser timezone doesn't match their event timezone?
5. How do you communicate to users that switching modes will preserve their data?

---

**Audit Completed**: October 14, 2025  
**Next Review**: After implementing state management refactor

