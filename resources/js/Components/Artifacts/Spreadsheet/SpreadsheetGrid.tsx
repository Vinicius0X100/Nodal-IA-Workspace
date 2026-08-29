import React, { useMemo } from 'react';
import SpreadsheetCell, { SpreadsheetCellProps } from './SpreadsheetCell';
import { indexToColumnLetter, getCellReference } from '@/Utils/spreadsheet';

interface SheetData {
    title: string;
    index: number;
    row_count: number;
    column_count: number;
    frozen_rows: number;
    frozen_columns: number;
    merged_ranges?: {
        start_row: number;
        end_row: number;
        start_col: number;
        end_col: number;
    }[];
    column_widths?: Record<number, number>;
    row_heights?: Record<number, number>;
}

interface GridData {
    range: string;
    rows: (SpreadsheetCellProps | null)[][];
}

interface Props {
    sheet: SheetData;
    grid: GridData;
    activeCell: { row: number, col: number } | null;
    onCellClick: (row: number, col: number) => void;
}

export default function SpreadsheetGrid({ sheet, grid, activeCell, onCellClick }: Props) {
    const { rows } = grid;
    
    // Calcula o tamanho da matriz a ser exibida com base nos dados reais vindos da API
    const rowCount = Math.max(rows.length, 1);
    const colCount = Math.max(...rows.map(r => r.length), 1);

    const { frozen_rows = 0, frozen_columns = 0, column_widths = {}, row_heights = {} } = sheet;

    // Gerar headers de coluna A, B, C...
    const colHeaders = useMemo(() => {
        const headers = [];
        for (let i = 0; i < colCount; i++) {
            headers.push(indexToColumnLetter(i));
        }
        return headers;
    }, [colCount]);

    const defaultColWidth = 100;
    const defaultRowHeight = 21;

    // TODO: Merged cells requires building a map to skip rendering cells that are part of a merge.
    // We will skip merged_ranges mapping in this immediate iteration if they conflict, 
    // but the backend returns null for cells swallowed by a merge, so standard rendering usually leaves them empty.
    // Natively, colSpan/rowSpan logic requires tracking them carefully. We will implement simple mapping for it.
    
    const mergedMap = useMemo(() => {
        const map: Record<string, { rowSpan: number, colSpan: number, skip: boolean }> = {};
        if (sheet.merged_ranges) {
            sheet.merged_ranges.forEach(range => {
                for (let r = range.start_row; r < range.end_row; r++) {
                    for (let c = range.start_col; c < range.end_col; c++) {
                        if (r === range.start_row && c === range.start_col) {
                            map[`${r}-${c}`] = {
                                rowSpan: range.end_row - range.start_row,
                                colSpan: range.end_col - range.start_col,
                                skip: false
                            };
                        } else {
                            map[`${r}-${c}`] = { rowSpan: 1, colSpan: 1, skip: true };
                        }
                    }
                }
            });
        }
        return map;
    }, [sheet.merged_ranges]);

    return (
        <div className="flex-1 overflow-auto bg-[#F8F9FA] relative select-none">
            <table className="border-collapse table-fixed bg-white w-max" style={{ borderSpacing: 0, borderCollapse: 'separate' }}>
                <colgroup>
                    <col style={{ width: '40px', minWidth: '40px' }} /> {/* Corner & row headers */}
                    {colHeaders.map((_, i) => (
                        <col key={`col-${i}`} style={{ width: `${column_widths[i] || defaultColWidth}px` }} />
                    ))}
                </colgroup>
                
                <thead>
                    <tr>
                        {/* Corner cell */}
                        <th className="sticky top-0 left-0 z-30 bg-[#F8F9FA] border-r border-b border-neutral-300 w-[40px] h-[24px]">
                            {/* Corner */}
                        </th>
                        {/* Column headers (A, B, C...) */}
                        {colHeaders.map((col, i) => (
                            <th 
                                key={`col-header-${i}`} 
                                className={`
                                    sticky top-0 z-20 bg-[#F8F9FA] border-r border-b border-neutral-300
                                    text-xs font-normal text-neutral-600 h-[24px] select-none
                                `}
                            >
                                {col}
                            </th>
                        ))}
                    </tr>
                </thead>
                
                <tbody>
                    {rows.map((row, rIdx) => {
                        const height = row_heights[rIdx] || defaultRowHeight;
                        const isFrozenRow = rIdx < frozen_rows;

                        return (
                            <tr key={`row-${rIdx}`} style={{ height: `${height}px` }}>
                                {/* Row header (1, 2, 3...) */}
                                <th 
                                    className={`
                                        sticky left-0 bg-[#F8F9FA] border-r border-b border-neutral-300
                                        text-xs font-normal text-neutral-600 px-1 text-center select-none
                                        ${isFrozenRow ? 'z-20' : 'z-10'}
                                    `}
                                    style={{
                                        ...(isFrozenRow ? { top: '24px' } : {})
                                    }}
                                >
                                    {rIdx + 1}
                                </th>
                                
                                {/* Data cells */}
                                {row.map((cell, cIdx) => {
                                    const mergeData = mergedMap[`${rIdx}-${cIdx}`];
                                    if (mergeData?.skip) {
                                        return null;
                                    }

                                    const isSelected = activeCell?.row === rIdx && activeCell?.col === cIdx;
                                    const isFrozenCol = cIdx < frozen_columns;

                                    return (
                                        <SpreadsheetCell
                                            key={`cell-${rIdx}-${cIdx}`}
                                            rowIndex={rIdx}
                                            colIndex={cIdx}
                                            cell={cell}
                                            isSelected={isSelected}
                                            isFrozenRow={isFrozenRow}
                                            isFrozenCol={isFrozenCol}
                                            rowSpan={mergeData?.rowSpan}
                                            colSpan={mergeData?.colSpan}
                                            onClick={() => onCellClick(rIdx, cIdx)}
                                        />
                                    );
                                })}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
