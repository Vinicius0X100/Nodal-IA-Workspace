import React from 'react';

interface Props {
    activeCellRef: string | null;
    activeCellValue: string;
}

export default function SpreadsheetFormulaBar({ activeCellRef, activeCellValue }: Props) {
    return (
        <div className="flex items-center border-b border-neutral-200 bg-white shrink-0">
            <div className="w-12 border-r border-neutral-200 flex items-center justify-center py-1.5 shrink-0 bg-neutral-50/50">
                <span className="text-xs font-semibold text-neutral-600 font-mono tracking-tight">
                    {activeCellRef || ''}
                </span>
            </div>
            
            <div className="flex-1 px-3 py-1.5 flex items-center bg-white min-w-0">
                <div className="text-sm text-neutral-400 select-none mr-2 italic font-serif">fx</div>
                <div className="flex-1 overflow-x-auto scrollbar-hide whitespace-nowrap text-sm text-neutral-800 font-mono">
                    {activeCellValue || ''}
                </div>
            </div>
        </div>
    );
}
