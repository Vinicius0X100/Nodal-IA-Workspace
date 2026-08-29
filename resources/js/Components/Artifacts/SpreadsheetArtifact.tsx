import React, { useState } from 'react';
import { useSpreadsheetArtifact } from '@/Hooks/useSpreadsheetArtifact';
import { Loader2, AlertCircle, RefreshCw, FileSpreadsheet } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

interface Props {
    resourceUuid: string;
}

export default function SpreadsheetArtifact({ resourceUuid }: Props) {
    const [activeSheetTitle, setActiveSheetTitle] = useState<string | undefined>();
    const { data, isLoading, error, refetch } = useSpreadsheetArtifact(resourceUuid, activeSheetTitle);

    if (isLoading && !data) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-8 text-neutral-500">
                <Loader2 className="w-8 h-8 animate-spin text-blue-500 mb-4" />
                <p className="text-sm font-medium">Carregando planilha...</p>
            </div>
        );
    }

    if (error && !data) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-8 text-red-500">
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

    return (
        <div className="flex flex-col h-full bg-white font-sans text-neutral-800">
            {/* Header */}
            <div className="px-6 py-4 border-b border-neutral-200 flex items-center justify-between bg-neutral-50">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center text-green-600 shadow-sm border border-green-200/50">
                        <FileSpreadsheet className="w-5 h-5" />
                    </div>
                    <div>
                        <h3 className="font-semibold text-neutral-900 truncate max-w-xs" title={name}>{name}</h3>
                        <p className="text-xs text-neutral-500 mt-0.5">Google Sheets • Visualização rápida</p>
                    </div>
                </div>
                
                <button
                    onClick={refetch}
                    disabled={isLoading}
                    title="Atualizar"
                    className="p-2 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-200/50 rounded-lg transition-colors disabled:opacity-50"
                >
                    <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                </button>
            </div>

            {/* Grid Container */}
            <div className="flex-1 overflow-auto relative bg-neutral-50/50 p-4">
                <div className="bg-white rounded-xl shadow-sm border border-neutral-200 overflow-hidden inline-block min-w-full">
                    <table className="w-full border-collapse text-sm">
                        <tbody>
                            {grid.rows.map((row, rowIndex) => (
                                <tr key={rowIndex}>
                                    {row.map((cell, colIndex) => {
                                        const isHeader = rowIndex < (sheets.find(s => s.title === currentSheetTitle)?.frozen_rows || 0);
                                        const style: React.CSSProperties = {
                                            backgroundColor: cell.format?.background_color || (isHeader ? '#f8fafc' : 'transparent'),
                                            color: cell.format?.text_color || (isHeader ? '#475569' : '#1e293b'),
                                            fontWeight: cell.format?.bold || isHeader ? '600' : 'normal',
                                            textAlign: typeof cell.value === 'number' ? 'right' : 'left',
                                        };

                                        return (
                                            <td
                                                key={colIndex}
                                                style={style}
                                                className={`
                                                    border border-neutral-200 px-3 py-2 whitespace-nowrap
                                                    ${isHeader ? 'shadow-[0_1px_0_rgba(0,0,0,0.1)] z-10 sticky top-0' : ''}
                                                `}
                                            >
                                                {cell.formatted_value || (cell.value !== null ? String(cell.value) : '')}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Sheet Tabs */}
            {sheets.length > 1 && (
                <div className="px-4 py-2 border-t border-neutral-200 bg-neutral-50 flex items-center gap-2 overflow-x-auto scrollbar-hide">
                    {sheets.map(sheet => (
                        <button
                            key={sheet.title}
                            onClick={() => setActiveSheetTitle(sheet.title)}
                            className={`
                                px-4 py-1.5 rounded-full text-xs font-medium transition-all duration-200 whitespace-nowrap
                                ${currentSheetTitle === sheet.title 
                                    ? 'bg-white shadow-sm border border-neutral-200 text-green-700' 
                                    : 'text-neutral-500 hover:bg-neutral-200/50 hover:text-neutral-700 border border-transparent'
                                }
                            `}
                        >
                            {sheet.title}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
