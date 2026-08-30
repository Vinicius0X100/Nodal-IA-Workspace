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

    const fetchSpreadsheet = useCallback(async () => {
        if (!target) return;
        
        const isDraft = target.mode === 'draft';
        const uuid = isDraft ? target.artifactUuid : target.resourceUuid;
        
        if (!uuid) return;

        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (sheetTitle) params.append('sheet', sheetTitle);
            if (range) params.append('range', range);
            
            const queryString = params.toString();
            const baseUrl = isDraft ? `/artifacts/${uuid}/spreadsheet` : `/resources/${uuid}/spreadsheet`;
            const url = `${baseUrl}${queryString ? `?${queryString}` : ''}`;

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            
            if (!response.ok || response.redirected) {
                let body = null;
                try {
                    body = await response.json();
                } catch {
                    body = await response.text();
                }

                console.error('[SPREADSHEET_ARTIFACT] Request failed or redirected', {
                    url,
                    status: response.status,
                    redirected: response.redirected,
                    responseUrl: response.url,
                    body,
                });

                throw new Error(`Falha ao carregar a planilha (${response.status}${response.redirected ? ' redirected' : ''}).`);
            }

            const data = await response.json();
            
            if (data?.success) {
                setData(data.data);
            } else {
                throw new Error(data?.message || 'Falha ao carregar a planilha.');
            }
        } catch (err: any) {
            console.error('Error fetching spreadsheet:', err);
            setError(err.message || 'Erro desconhecido');
        } finally {
            setIsLoading(false);
        }
    }, [target?.mode, target?.mode === 'draft' ? target.artifactUuid : target?.mode === 'resource' ? target.resourceUuid : null, sheetTitle, range]);

    useEffect(() => {
        fetchSpreadsheet();
    }, [fetchSpreadsheet]);

    return {
        data,
        isLoading,
        error,
        refetch: fetchSpreadsheet,
    };
}
