/**
 * Only what a phone number is written with stays (digits, and a + first,
 * spaces, dashes, brackets, dots): a letter typed simply does not appear.
 * Ten plain digits get the US dashes as they are typed (8324438072 becomes
 * 832-443-8072); type one more, or take one off, and those dashes go again,
 * so a number from elsewhere is never forced into US shape. Anything written
 * another way stays as typed. App\Actions\Projects\UpdateVenue does the same
 * on save.
 */
export const phoneDashes = (value) => {
    const text = String(value ?? '').replace(/[^\d\s().+-]/g, '').replace(/(?!^)\+/g, '');
    if (!/^[\d-]*$/.test(text)) return text;
    const digits = text.replace(/-/g, '');
    if (digits.length === 10) return `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}`;
    // Our own dashes from a moment ago (3-3-…): gone again once it is not ten digits.
    if (/^\d{3}-\d{3}-\d*$/.test(text) || /^\d{3}-\d{0,3}$/.test(text)) return digits;
    return text;
};
