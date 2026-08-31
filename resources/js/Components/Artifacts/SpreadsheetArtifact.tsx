import React, { useState, useMemo } from 'react';
import { useSpreadsheetArtifact } from '@/Hooks/useSpreadsheetArtifact';
import { Loader2, AlertCircle, RefreshCw } from 'lucide-react';
import { getCellReference } from '@/Utils/spreadsheet';

import SpreadsheetHeader from './Spreadsheet/SpreadsheetHeader';
import SpreadsheetToolbar from './Spreadsheet/SpreadsheetToolbar';
import SpreadsheetFormulaBar from './Spreadsheet/SpreadsheetFormulaBar';
import SpreadsheetGrid from './Spreadsheet/SpreadsheetGrid';
import SpreadsheetTabs from './Spreadsheet/SpreadsheetTabs';

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

    const { data, isLoading, error, refetch } = useSpreadsheetArtifact(props, activeSheetTitle);

    const handleSelectSheet = (title: string) => {
        setActiveSheetTitle(title);
        setActiveCell(null); // Reset selection when changing tabs
    };

    const handleCellClick = (row: number, col: number) => {
        setActiveCell({ row, col });
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
            
            <SpreadsheetToolbar disabled={props.mode === 'draft'} />
            
            <SpreadsheetFormulaBar 
                activeCellRef={activeCellRef}
                activeCellValue={activeCellValue}
            />
            
            <SpreadsheetGrid 
                sheet={currentSheet}
                grid={grid}
                activeCell={activeCell}
                onCellClick={handleCellClick}
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
