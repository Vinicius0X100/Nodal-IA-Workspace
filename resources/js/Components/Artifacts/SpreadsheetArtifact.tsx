import React, { useState, useMemo } from 'react';
import { useSpreadsheetArtifact } from '@/Hooks/useSpreadsheetArtifact';
import { Loader2, AlertCircle, RefreshCw } from 'lucide-react';
import { getCellReference } from '@/Utils/spreadsheet';

import SpreadsheetHeader from './Spreadsheet/SpreadsheetHeader';
import SpreadsheetToolbar from './Spreadsheet/SpreadsheetToolbar';
import SpreadsheetFormulaBar from './Spreadsheet/SpreadsheetFormulaBar';
import SpreadsheetGrid from './Spreadsheet/SpreadsheetGrid';
import SpreadsheetTabs from './Spreadsheet/SpreadsheetTabs';
import { useSpreadsheetDraftMutations } from '@/Hooks/useSpreadsheetDraftMutations';
import { toast } from 'sonner';

import ErrorBoundary from '../ErrorBoundary';

export type SpreadsheetArtifactProps =
    | {
          mode: 'draft';
          artifactUuid: string;
      }
    | {
          mode: 'resource';
          resourceUuid: string;
      };

function SpreadsheetArtifactInner(props: SpreadsheetArtifactProps) {
    const [activeSheetTitle, setActiveSheetTitle] = useState<string | undefined>();
    const [activeCell, setActiveCell] = useState<{ row: number, col: number } | null>(null);
    const [isEditing, setIsEditing] = useState(false);
    const [editingValue, setEditingValue] = useState('');

    const { data, isLoading, error, refetch } = useSpreadsheetArtifact(props, activeSheetTitle);
    const { updateValues, updateFormatting, isMutating } = useSpreadsheetDraftMutations(
        props.mode === 'draft' ? props.artifactUuid : ''
    );

    const handleSelectSheet = (title: string) => {
        setActiveSheetTitle(title);
        setActiveCell(null);
        setIsEditing(false);
    };

    const handleCellClick = (row: number, col: number) => {
        if (isMutating) return;
        setActiveCell({ row, col });
        setIsEditing(false);
    };

    const handleCellDoubleClick = (row: number, col: number) => {
        if (props.mode !== 'draft' || isMutating) return;
        
        setActiveCell({ row, col });
        setIsEditing(true);
        
        const cellData = data?.grid.rows[row]?.[col];
        if (cellData) {
            setEditingValue(cellData.formula || (cellData.value !== null ? String(cellData.value) : ''));
        } else {
            setEditingValue('');
        }
    };

    const activeCellRef = useMemo(() => {
        if (!activeCell) return null;
        return getCellReference(activeCell.row, activeCell.col);
    }, [activeCell]);

    const activeCellValue = useMemo(() => {
        if (!activeCell || !data) return '';
        const { row, col } = activeCell;
        const cellData = data.grid.rows[row]?.[col];
        if (!cellData) return '';
        
        if (cellData.formula) {
            return cellData.formula;
        }
        return cellData.value !== null ? String(cellData.value) : '';
    }, [activeCell, data]);

    const handleSaveValue = async (value: string, moveToNext: 'down' | 'right' | 'left' | 'none' = 'none') => {
        if (props.mode !== 'draft' || !activeCell || !data) return;

        const currentRevision = data.revision;
        const currentSheet = data.sheets.find(s => s.title === (activeSheetTitle || data.active_sheet)) || data.sheets[0];
        
        if (!currentSheet) return;

        const { row, col } = activeCell;
        const range = getCellReference(row, col);

        // Normalize value
        let parsedValue: any = value;
        if (value === '') {
            parsedValue = null;
        } else if (!value.startsWith('=')) {
            const num = Number(value);
            // Conservatively treat as number if it doesn't lose precision or leading zeros
            if (!isNaN(num) && String(num) === value) {
                parsedValue = num;
            } else if (value.toLowerCase() === 'true') {
                parsedValue = true;
            } else if (value.toLowerCase() === 'false') {
                parsedValue = false;
            }
        }

        try {
            const result = await updateValues({
                sheetUuid: currentSheet.uuid,
                expectedRevision: currentRevision,
                updates: [
                    {
                        range,
                        values: [[parsedValue]]
                    }
                ]
            });

            if (result?.revision) {
                await refetch();
                setIsEditing(false);
                
                if (moveToNext === 'down') {
                    setActiveCell({ row: row + 1, col });
                } else if (moveToNext === 'right') {
                    setActiveCell({ row, col: col + 1 });
                } else if (moveToNext === 'left') {
                    setActiveCell({ row, col: Math.max(0, col - 1) });
                }
            }
        } catch (err: any) {
            if (err.status === 409 || err.code === 'DRAFT_REVISION_CONFLICT') {
                toast.warning('A planilha foi atualizada. Recarregamos a versão mais recente.');
                await refetch();
            } else {
                toast.error(err.message || 'Erro ao salvar valor.');
            }
        }
    };

    const handleFormat = async (format: any) => {
        if (props.mode !== 'draft' || !activeCell || !data) return;

        const currentRevision = data.revision;
        const currentSheet = data.sheets.find(s => s.title === (activeSheetTitle || data.active_sheet)) || data.sheets[0];
        
        if (!currentSheet) return;

        const { row, col } = activeCell;
        const range = getCellReference(row, col);

        try {
            const result = await updateFormatting({
                sheetUuid: currentSheet.uuid,
                expectedRevision: currentRevision,
                operations: [
                    {
                        type: 'format_range',
                        range,
                        format
                    }
                ]
            });

            if (result?.revision) {
                await refetch();
            }
        } catch (err: any) {
            if (err.status === 409 || err.code === 'DRAFT_REVISION_CONFLICT') {
                toast.warning('A planilha foi atualizada. Recarregamos a versão mais recente.');
                await refetch();
            } else {
                toast.error(err.message || 'Erro ao aplicar formatação.');
            }
        }
    };

    if (isLoading && !data) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-8 text-neutral-500 bg-white">
                <Loader2 className="w-8 h-8 animate-spin text-[#0F9D58] mb-4" />
                <p className="text-sm font-medium">Carregando planilha...</p>
            </div>
        );
    }

    if (error && !data) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-8 text-red-500 bg-white">
                <AlertCircle className="w-10 h-10 mb-4" />
                <p className="text-sm font-medium text-center mb-4">{error}</p>
                <button
                    onClick={refetch}
                    className="flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-colors text-sm font-medium"
                >
                    <RefreshCw className="w-4 h-4" /> Tentar novamente
                </button>
            </div>
        );
    }

    if (!data) return null;

    const { sheets, grid, name } = data;
    const currentSheetTitle = activeSheetTitle || data.active_sheet;
    const currentSheet = sheets.find(s => s.title === currentSheetTitle) || sheets[0];

    return (
        <div className="flex flex-col h-full bg-white font-sans text-neutral-800 border-l border-neutral-200">
            <SpreadsheetHeader 
                name={name} 
                isLoading={isLoading} 
                onRefresh={refetch}
                mode={props.mode}
                persistenceLabel={props.mode === 'draft' ? 'Ainda não salvo no Google Drive' : 'Salvo no Google Drive'}
            />
            
            <SpreadsheetToolbar 
                disabled={props.mode === 'draft' && isMutating}
                isDraft={props.mode === 'draft'}
                onFormat={handleFormat} 
            />
            
            <SpreadsheetFormulaBar 
                activeCellRef={activeCellRef}
                activeCellValue={isEditing ? editingValue : activeCellValue}
                isEditing={isEditing}
                isMutating={isMutating}
                onChange={setEditingValue}
                onSave={() => handleSaveValue(editingValue, 'down')}
                onCancel={() => setIsEditing(false)}
                disabled={props.mode !== 'draft'}
            />
            
            <SpreadsheetGrid 
                sheet={currentSheet}
                grid={grid}
                activeCell={activeCell}
                isEditing={isEditing}
                editingValue={editingValue}
                isMutating={isMutating}
                onCellClick={handleCellClick}
                onCellDoubleClick={handleCellDoubleClick}
                onEditingValueChange={setEditingValue}
                onSaveValue={handleSaveValue}
                onCancelEdit={() => setIsEditing(false)}
            />

            <SpreadsheetTabs 
                sheets={sheets}
                activeSheetTitle={currentSheetTitle}
                onSelectSheet={handleSelectSheet}
            />
        </div>
    );
}

export default function SpreadsheetArtifact(props: SpreadsheetArtifactProps) {
    // A key baseada no target garante que se o UUID mudar, o componente anterior morre por completo e desmonta.
    const key = props.mode === 'draft' ? `draft-${props.artifactUuid}` : `resource-${props.resourceUuid}`;
    return (
        <ErrorBoundary>
            <SpreadsheetArtifactInner key={key} {...props} />
        </ErrorBoundary>
    );
}
