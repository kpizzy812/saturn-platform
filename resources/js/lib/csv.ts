/**
 * CSV export/import utilities with proper Excel compatibility
 */

// UTF-8 BOM for Excel to correctly recognize encoding and use comma as delimiter
export const CSV_BOM = '\uFEFF';

/**
 * Remove BOM from the beginning of a string (for import compatibility)
 */
export function stripBOM(text: string): string {
    // Remove UTF-8 BOM if present
    if (text.charCodeAt(0) === 0xFEFF) {
        return text.slice(1);
    }
    return text;
}

/**
 * Escape a value for CSV format
 * - Wraps in quotes if contains comma, quote, or newline
 * - Escapes quotes by doubling them
 */
export function escapeCSVValue(value: unknown): string {
    if (value === null || value === undefined) return '';

    const stringValue = String(value);

    // If contains special characters, wrap in quotes and escape internal quotes
    if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n') || stringValue.includes('\r')) {
        return `"${stringValue.replace(/"/g, '""')}"`;
    }

    return stringValue;
}

/**
 * Convert data to CSV string with BOM for Excel compatibility
 */
export function toCSV(columns: string[], rows: Record<string, unknown>[]): string {
    const header = columns.map(escapeCSVValue).join(',');
    const dataRows = rows.map(row =>
        columns.map(col => escapeCSVValue(row[col])).join(',')
    );

    return CSV_BOM + [header, ...dataRows].join('\n');
}

/**
 * Download content as a file
 */
export function downloadFile(content: string, filename: string, mimeType: string): void {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

/**
 * Export data to CSV file with Excel compatibility
 */
export function exportToCSV(columns: string[], rows: Record<string, unknown>[], filename: string): void {
    const csv = toCSV(columns, rows);
    downloadFile(csv, filename, 'text/csv;charset=utf-8');
}

/**
 * Export data to JSON file
 */
export function exportToJSON(data: unknown, filename: string, pretty = true): void {
    const json = pretty ? JSON.stringify(data, null, 2) : JSON.stringify(data);
    downloadFile(json, filename, 'application/json');
}
