/**
 * Format a city and state as "City, ST", skipping whichever is missing.
 */
export function formatLocation(
    city: string | null | undefined,
    state: string | null | undefined,
): string {
    return [city, state].filter(Boolean).join(', ');
}
