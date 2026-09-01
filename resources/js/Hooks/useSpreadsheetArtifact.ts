import { useState, useEffect, useCallback } from 'react';


export interface SpreadsheetFormat {
    bold?: boolean;
    background_color?: string;
    text_color?: string;
    number_format?: {
        type?: string;
        pattern?: string;
    };
}

export interface SpreadsheetCell {
    value: string | number | boolean | null;
    formatted_value: string | null;
    formula: string | null;
    format: SpreadsheetFormat;
}

export interface SpreadsheetSheetInfo {
    title: string;
    index: number;
    row_count: number;
    column_count: number;
    frozen_rows: number;
    frozen_columns: number;
}

export interface SpreadsheetData {
    resource_uuid: string;
    name: string;
    type: string;
    provider: string;
    status: string;
    revision: number;
    capabilities: {
        preview: boolean;
        edit: boolean;
        download: boolean;
    };
    active_sheet: string;
    requested_range: string;
    sheets: SpreadsheetSheetInfo[];
    grid: {
        range: string;
        rows: SpreadsheetCell[][];
        column_widths: Record<string, number>;
        row_heights: Record<string, number>;
        merged_ranges: any[];
    };
}

export type SpreadsheetTarget = 
    | { mode: 'draft'; artifactUuid: string }
    | { mode: 'resource'; resourceUuid: string };

export function useSpreadsheetArtifact(target: SpreadsheetTarget | undefined, sheetTitle?: string, range?: string) {
    const [data, setData] = useState<SpreadsheetData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSpreadsheet = useCallback(async (abortSignal?: AbortSignal) => {
        if (!target) return;
        
        const isDraft = target.mode === 'draft';
        const uuid = isDraft ? target.artifactUuid : target.resourceUuid;
        
        if (!uuid) return;

        setIsLoading(true);
        setError(null);

        try {
            const searchParams = new URLSearchParams();
            if (sheetTitle) searchParams.set('sheet', sheetTitle);
            if (range) searchParams.set('range', range);
            
            const queryString = searchParams.toString();
            const baseUrl = isDraft ? `/artifacts/${uuid}/spreadsheet` : `/resources/${uuid}/spreadsheet`;
            const url = queryString ? `${baseUrl}?${queryString}` : baseUrl;

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: abortSignal,
            });
            
            if (!response.ok || response.redirected) {
                let body = null;
                try { body = await response.json(); } catch { body = await response.text(); }
                if (!abortSignal?.aborted) {
                    console.error('[SPREADSHEET_ARTIFACT] Request failed or redirected', { url, status: response.status, body });
                    throw new Error(`Falha ao carregar a planilha (${response.status}).`);
                }
            }

            const resData = await response.json();
            
            if (resData?.success && !abortSignal?.aborted) {
                const raw = resData.data;
                
                // Normalization
                const sheets = Array.isArray(raw.sheets) 
                    ? raw.sheets 
                    : (raw.active_sheet ? [raw.active_sheet] : []);
                
                const activeSheetTitle = typeof raw.active_sheet === 'string' 
                    ? raw.active_sheet 
                    : (raw.active_sheet?.title || sheets[0]?.title || '');
                
                let rows: any[][] = [];
                if (raw.grid && Array.isArray(raw.grid.rows)) {
                    rows = raw.grid.rows;
                } else if (raw.viewport && Array.isArray(raw.viewport.cells)) {
                    // Convert 1D cells to 2D rows
                    raw.viewport.cells.forEach((cell: any) => {
                        const r = cell.row;
                        const c = cell.column;
                        if (!rows[r]) rows[r] = [];
                        rows[r][c] = {
                            value: cell.value ?? null,
                            formatted_value: cell.formatted_value ?? null,
                            formula: cell.formula ?? null,
                            format: Array.isArray(cell.format) ? {} : (cell.format || {})
                        };
                    });
                    
                    // Fill nulls for sparse arrays
                    for (let i = 0; i < rows.length; i++) {
                        if (!rows[i]) rows[i] = [];
                        for (let j = 0; j < rows[i].length; j++) {
                            if (rows[i][j] === undefined) rows[i][j] = null;
                        }
                    }
                }

                // Format normalizer for Resource rows
                if (raw.grid && Array.isArray(raw.grid.rows)) {
                    rows = rows.map(r => r ? r.map(c => {
                        if (!c) return c;
                        return { ...c, format: Array.isArray(c.format) ? {} : (c.format || {}) };
                    }) : r);
                }

                const normalizedData: SpreadsheetData = {
                    resource_uuid: raw.resource_uuid || raw.artifact_uuid || uuid,
                    name: raw.name || raw.title || 'Planilha',
                    type: raw.type || 'spreadsheet',
                    provider: raw.provider || 'system',
                    status: raw.status || 'draft',
                    revision: raw.revision || 1,
                    capabilities: raw.capabilities || { preview: true, edit: true, download: true },
                    active_sheet: activeSheetTitle,
                    requested_range: raw.requested_range || raw.viewport?.range || 'A1:Z100',
                    sheets: sheets,
                    grid: {
                        range: raw.grid?.range || raw.viewport?.range || 'A1:Z100',
                        rows: rows,
                        column_widths: raw.grid?.column_widths || raw.viewport?.column_widths || {},
                        row_heights: raw.grid?.row_heights || raw.viewport?.row_heights || {},
                        merged_ranges: Array.isArray(raw.grid?.merged_ranges || raw.viewport?.merged_ranges) ? (raw.grid?.merged_ranges || raw.viewport?.merged_ranges) : []
                    }
                };

                setData(normalizedData);
            } else if (!resData?.success && !abortSignal?.aborted) {
                throw new Error(resData?.message || 'Falha ao carregar a planilha.');
            }
        } catch (err: any) {
            if (err.name === 'AbortError') return;
            console.error('Error fetching spreadsheet:', err);
            setError(err.message || 'Erro desconhecido');
        } finally {
            if (!abortSignal?.aborted) {
                setIsLoading(false);
            }
        }
    }, [target?.mode, target?.mode === 'draft' ? target.artifactUuid : target?.mode === 'resource' ? target.resourceUuid : null, sheetTitle, range]);

    useEffect(() => {
        const abortController = new AbortController();
        fetchSpreadsheet(abortController.signal);
        
        const handleRefresh = () => {
            fetchSpreadsheet();
        };
        
        window.addEventListener('focus', handleRefresh);
        window.addEventListener('assistant:message_completed', handleRefresh);
        
        return () => {
            abortController.abort();
            window.removeEventListener('focus', handleRefresh);
            window.removeEventListener('assistant:message_completed', handleRefresh);
        };
    }, [fetchSpreadsheet]);

    return {
        data,
        isLoading,
        error,
        refetch: () => fetchSpreadsheet(),
    };
}
