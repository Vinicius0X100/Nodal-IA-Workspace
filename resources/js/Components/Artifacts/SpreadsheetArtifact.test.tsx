import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import SpreadsheetArtifact from './SpreadsheetArtifact';
import * as useSpreadsheetDraftMutationsHook from '@/Hooks/useSpreadsheetDraftMutations';
import * as useSpreadsheetArtifactHook from '@/Hooks/useSpreadsheetArtifact';

// Mock Lucide icons
vi.mock('lucide-react', () => ({
    Loader2: () => <div data-testid="loader">Loader</div>,
    AlertCircle: () => <div data-testid="alert">Alert</div>,
    RefreshCw: () => <div data-testid="refresh">Refresh</div>,
    Undo2: () => <span>Undo</span>,
    Redo2: () => <span>Redo</span>,
    Bold: () => <span data-testid="icon-bold">Bold</span>,
    Italic: () => <span>Italic</span>,
    Baseline: () => <span>Baseline</span>,
    Palette: () => <span>Palette</span>,
    AlignLeft: () => <span>AlignLeft</span>,
    AlignCenter: () => <span>AlignCenter</span>,
    AlignRight: () => <span>AlignRight</span>,
    WrapText: () => <span>WrapText</span>,
    Grid3X3: () => <span>Grid3X3</span>,
    Columns: () => <span>Columns</span>,
    Rows: () => <span>Rows</span>,
    FileSpreadsheet: () => <span>FileSpreadsheet</span>,
    Download: () => <span>Download</span>,
    Menu: () => <span>Menu</span>,
}));

// Mock Sonner
vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    }
}));

describe('SpreadsheetArtifact', () => {
    let updateFormattingMock: any;
    let updateValuesMock: any;

    beforeEach(() => {
        vi.clearAllMocks();

        updateFormattingMock = vi.fn().mockResolvedValue({ revision: 2 });
        updateValuesMock = vi.fn().mockResolvedValue({ revision: 2 });

        vi.spyOn(useSpreadsheetDraftMutationsHook, 'useSpreadsheetDraftMutations').mockReturnValue({
            updateValues: updateValuesMock,
            updateFormatting: updateFormattingMock,
            isMutating: false
        } as any);

        vi.spyOn(useSpreadsheetArtifactHook, 'useSpreadsheetArtifact').mockReturnValue({
            data: {
                revision: 1,
                name: 'Test File',
                active_sheet: 'Sheet1',
                sheets: [
                    { uuid: 'sheet-uuid-1', title: 'Sheet1', index: 0, row_count: 10, column_count: 10 }
                ],
                grid: {
                    range: 'A1:A1',
                    rows: [
                        [ { value: 'Test', formatted_value: 'Test', formula: null } ]
                    ]
                }
            },
            isLoading: false,
            error: null,
            refetch: vi.fn()
        } as any);
    });

    it('renders and dispatches updateFormatting when a toolbar button is clicked', async () => {
        render(<SpreadsheetArtifact mode="draft" artifactUuid="draft-123" />);

        // Click on the cell A1 to make it active
        const cell = screen.getByText('Test');
        fireEvent.click(cell);

        // Click on Bold in the Toolbar
        const boldBtn = screen.getByTestId('icon-bold').closest('button');
        expect(boldBtn).not.toBeNull();
        fireEvent.click(boldBtn!);

        // Expect the format mutation to have been called with the correct args
        expect(updateFormattingMock).toHaveBeenCalledTimes(1);
        expect(updateFormattingMock).toHaveBeenCalledWith({
            sheetUuid: 'sheet-uuid-1',
            expectedRevision: 1,
            operations: [
                {
                    type: 'format_range',
                    range: 'A1',
                    format: { bold: true }
                }
            ]
        });
    });
});
