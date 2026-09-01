import { useState, useCallback, useRef } from 'react';
import axios from 'axios';

export interface SpreadsheetDraftFormatOperation {
    type: 'format_range' | 'number_format' | 'borders' | 'freeze' | 'set_column_width' | 'set_row_height' | 'auto_resize_columns' | 'merge_cells';
    range?: string;
    format?: any;
    rows?: number;
    columns?: number;
}

interface UpdateValuesPayload {
    sheetUuid: string;
    expectedRevision: number;
    updates: {
        range: string;
        values: any[][];
    }[];
}

interface UpdateFormattingPayload {
    sheetUuid: string;
    expectedRevision: number;
    operations: SpreadsheetDraftFormatOperation[];
}

export function useSpreadsheetDraftMutations(artifactUuid: string) {
    const [isMutating, setIsMutating] = useState(false);
    const mutationRef = useRef<boolean>(false);

    const checkConcurrency = () => {
        if (mutationRef.current) {
            console.warn('[SpreadsheetMutation] Mutation in progress, ignoring.');
            return false;
        }
        return true;
    };

    const updateValues = useCallback(async (payload: UpdateValuesPayload) => {
        if (!checkConcurrency()) return null;
        
        setIsMutating(true);
        mutationRef.current = true;
        
        try {
            const response = await axios.patch(`/artifacts/${artifactUuid}/spreadsheet/values`, {
                expected_revision: payload.expectedRevision,
                sheet_uuid: payload.sheetUuid,
                updates: payload.updates
            }, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.data?.success) {
                return response.data.data;
            } else {
                throw new Error(response.data?.message || 'Erro ao atualizar valores');
            }
        } catch (error: any) {
            // Se o backend retornar erro com json {success: false}, o axios lanca erro se for 4xx/5xx
            const axiosErrorResponse = error.response?.data;
            throw {
                status: error.response?.status,
                code: axiosErrorResponse?.code || 'UNKNOWN_ERROR',
                message: axiosErrorResponse?.message || error.message
            };
        } finally {
            setIsMutating(false);
            mutationRef.current = false;
        }
    }, [artifactUuid]);

    const updateFormatting = useCallback(async (payload: UpdateFormattingPayload) => {
        if (!checkConcurrency()) return null;

        setIsMutating(true);
        mutationRef.current = true;
        
        try {
            const response = await axios.patch(`/artifacts/${artifactUuid}/spreadsheet/format`, {
                expected_revision: payload.expectedRevision,
                sheet_uuid: payload.sheetUuid,
                operations: payload.operations
            }, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.data?.success) {
                return response.data.data;
            } else {
                throw new Error(response.data?.message || 'Erro ao atualizar formatação');
            }
        } catch (error: any) {
            const axiosErrorResponse = error.response?.data;
            throw {
                status: error.response?.status,
                code: axiosErrorResponse?.code || 'UNKNOWN_ERROR',
                message: axiosErrorResponse?.message || error.message
            };
        } finally {
            setIsMutating(false);
            mutationRef.current = false;
        }
    }, [artifactUuid]);

    return {
        updateValues,
        updateFormatting,
        isMutating
    };
}
