/**
 * Centralized Date Utilities for Everything Immersive
 * 
 * Ensures consistent timezone handling across all date operations
 * to prevent date shifts when users are in different timezones.
 */

import moment from 'moment-timezone';

/**
 * Normalize a date to YYYY-MM-DD format in the specified timezone
 * Always returns the date as it appears in that timezone
 * 
 * @param {Date|string} date - Date to normalize
 * @param {string} timezone - IANA timezone (e.g., 'America/New_York')
 * @returns {string} Date in YYYY-MM-DD format
 */
export const normalizeDateToTimezone = (date, timezone) => {
    if (!date) return null;
    
    // Parse the date in the specified timezone
    const m = moment.tz(date, timezone);
    
    // Return as YYYY-MM-DD (no time component, no timezone conversion)
    return m.format('YYYY-MM-DD');
};

/**
 * Create a Date object at noon in the specified timezone
 * Used for date picker compatibility while avoiding timezone issues
 * 
 * @param {string} dateString - Date in YYYY-MM-DD format
 * @param {string} timezone - IANA timezone
 * @returns {Date} Date object set to noon in the specified timezone
 */
export const createDateAtNoon = (dateString, timezone) => {
    if (!dateString) return null;
    
    // Parse as YYYY-MM-DD in the specified timezone at noon
    const m = moment.tz(dateString, timezone).hour(12).minute(0).second(0).millisecond(0);
    
    // Return as native Date object
    return m.toDate();
};

/**
 * Format a date for display in the user's locale
 * 
 * @param {Date|string} date - Date to format
 * @param {string} timezone - IANA timezone
 * @param {object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
export const formatDateForDisplay = (date, timezone, options = {}) => {
    if (!date) return '';
    
    const defaultOptions = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: timezone
    };
    
    return new Date(date).toLocaleDateString('en-US', { ...defaultOptions, ...options });
};

/**
 * Format a date for API submission (YYYY-MM-DD HH:MM:SS in UTC)
 * Backend will receive this and store in database as-is
 * 
 * @param {Date|string} date - Date to format
 * @param {string} timezone - IANA timezone the date was selected in
 * @returns {string} Date in YYYY-MM-DD HH:MM:SS format (UTC)
 */
export const formatDateForAPI = (date, timezone) => {
    if (!date) return null;
    
    // Parse the date in the user's timezone
    const m = moment.tz(date, timezone);
    
    // Set to noon in that timezone
    m.hour(12).minute(0).second(0);
    
    // Convert to UTC and format for database
    return m.utc().format('YYYY-MM-DD HH:mm:ss');
};

/**
 * Parse a date string from YYYY-MM-DD format into a Date object
 * Ensures the date is interpreted in the specified timezone
 * 
 * @param {string|Date} dateString - Date in YYYY-MM-DD format or Date object
 * @param {string} timezone - IANA timezone
 * @returns {Date} Date object
 */
export const parseDateString = (dateString, timezone) => {
    if (!dateString) return null;
    
    // If it's already a Date object, return it
    if (dateString instanceof Date) {
        return dateString;
    }
    
    // If it's not a string, try to convert it
    if (typeof dateString !== 'string') {
        dateString = String(dateString);
    }
    
    // Handle full datetime strings (YYYY-MM-DD HH:MM:SS)
    const datePart = dateString.includes(' ') ? dateString.split(' ')[0] : dateString;
    
    // Split the date to avoid timezone interpretation issues
    const [year, month, day] = datePart.split('-').map(Number);
    
    // Create moment in the specified timezone
    const m = moment.tz({ year, month: month - 1, day }, timezone).hour(12);
    
    return m.toDate();
};

/**
 * Calculate a date N months in the future
 * Handles edge cases like Jan 31 + 1 month = Feb 28/29
 * 
 * @param {Date|string} startDate - Starting date
 * @param {number} months - Number of months to add
 * @param {string} timezone - IANA timezone
 * @returns {Date} Calculated date
 */
export const addMonths = (startDate, months, timezone) => {
    const m = moment.tz(startDate, timezone);
    m.add(months, 'months');
    return m.toDate();
};

/**
 * Set a date to end of day (23:59:59) in the specified timezone
 * 
 * @param {Date|string} date - Date to modify
 * @param {string} timezone - IANA timezone
 * @returns {Date} Date at end of day
 */
export const endOfDay = (date, timezone) => {
    const m = moment.tz(date, timezone);
    m.hour(23).minute(59).second(59).millisecond(999);
    return m.toDate();
};

/**
 * Get the current date/time in a specific timezone
 * 
 * @param {string} timezone - IANA timezone
 * @returns {Date} Current date/time
 */
export const now = (timezone) => {
    return moment.tz(timezone).toDate();
};

/**
 * Calculate days between two dates
 * 
 * @param {Date|string} startDate - Start date
 * @param {Date|string} endDate - End date
 * @param {string} timezone - IANA timezone
 * @returns {number} Number of days
 */
export const daysBetween = (startDate, endDate, timezone) => {
    const start = moment.tz(startDate, timezone).startOf('day');
    const end = moment.tz(endDate, timezone).startOf('day');
    return end.diff(start, 'days');
};

/**
 * Generate dates for recurring weekly events
 * 
 * @param {Array<number>} daysOfWeek - Array of day indices (0 = Sunday, 6 = Saturday)
 * @param {Date|string} startDate - Start date
 * @param {Date|string} endDate - End date
 * @param {string} timezone - IANA timezone
 * @returns {Array<string>} Array of date strings in YYYY-MM-DD format
 */
export const generateRecurringDates = (daysOfWeek, startDate, endDate, timezone) => {
    const dates = [];
    const start = moment.tz(startDate, timezone).hour(12);
    const end = moment.tz(endDate, timezone).hour(12);
    
    // For each day of week
    daysOfWeek.forEach(dayIndex => {
        let current = start.clone();
        
        // Find first occurrence of this day
        while (current.day() !== dayIndex && current.isBefore(end)) {
            current.add(1, 'day');
        }
        
        // Generate weekly occurrences
        while (current.isSameOrBefore(end)) {
            dates.push(current.format('YYYY-MM-DD'));
            current.add(7, 'days');
        }
    });
    
    // Sort chronologically
    return dates.sort();
};

/**
 * Validate that a timezone string is valid
 * 
 * @param {string} timezone - Timezone to validate
 * @returns {boolean} True if valid
 */
export const isValidTimezone = (timezone) => {
    return moment.tz.names().includes(timezone);
};

/**
 * Get the user's browser timezone
 * 
 * @returns {string} IANA timezone name
 */
export const getBrowserTimezone = () => {
    return moment.tz.guess();
};

/**
 * Compare two dates for equality (ignoring time)
 * 
 * @param {Date|string} date1 - First date
 * @param {Date|string} date2 - Second date
 * @param {string} timezone - IANA timezone
 * @returns {boolean} True if dates are the same day
 */
export const isSameDay = (date1, date2, timezone) => {
    const m1 = moment.tz(date1, timezone).startOf('day');
    const m2 = moment.tz(date2, timezone).startOf('day');
    return m1.isSame(m2);
};

export default {
    normalizeDateToTimezone,
    createDateAtNoon,
    formatDateForDisplay,
    formatDateForAPI,
    parseDateString,
    addMonths,
    endOfDay,
    now,
    daysBetween,
    generateRecurringDates,
    isValidTimezone,
    getBrowserTimezone,
    isSameDay
};

