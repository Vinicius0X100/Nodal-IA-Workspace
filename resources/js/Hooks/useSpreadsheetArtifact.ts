import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

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

export function useSpreadsheetArtifact(resourceUuid: string | undefined, sheetTitle?: string, range?: string) {
    const [data, setData] = useState<SpreadsheetData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSpreadsheet = useCallback(async () => {
        if (!resourceUuid) return;

        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (sheetTitle) params.append('sheet', sheetTitle);
            if (range) params.append('range', range);
            
            const queryString = params.toString();
            const url = `/resources/${resourceUuid}/spreadsheet${queryString ? `?${queryString}` : ''}`;

            const response = await axios.get(url);
            
            if (response.data?.success) {
                setData(response.data.data);
            } else {
                throw new Error(response.data?.message || 'Falha ao carregar a planilha.');
            }
        } catch (err: any) {
            console.error('Error fetching spreadsheet:', err);
            setError(err.response?.data?.message || err.message || 'Erro desconhecido');
        } finally {
            setIsLoading(false);
        }
    }, [resourceUuid, sheetTitle, range]);

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
