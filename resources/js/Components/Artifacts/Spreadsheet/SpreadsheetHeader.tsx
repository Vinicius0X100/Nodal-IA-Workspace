import React from 'react';
import { FileSpreadsheet, RefreshCw, X, Download } from 'lucide-react';

interface Props {
    name: string;
    isLoading: boolean;
    onRefresh: () => void;
    onClose?: () => void;
}

export default function SpreadsheetHeader({ name, isLoading, onRefresh, onClose }: Props) {
    return (
        <div className="px-4 py-2 border-b border-neutral-200 flex items-center justify-between bg-white shrink-0">
            <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-[#0F9D58]/10 flex items-center justify-center text-[#0F9D58] shadow-sm border border-[#0F9D58]/20">
                    <FileSpreadsheet className="w-4 h-4" />
                </div>
                <div>
                    <h3 className="text-sm font-semibold text-neutral-900 truncate max-w-[200px] sm:max-w-xs" title={name}>
                        {name}
                    </h3>
                    <p className="text-[10px] text-neutral-500 mt-0.5 flex items-center gap-1">
                        Salvo no Google Drive
                    </p>
                </div>
            </div>
            
            <div className="flex items-center gap-1">
                <button
                    disabled
                    title="Download indisponível no momento"
                    className="p-1.5 text-neutral-300 rounded-md transition-colors cursor-not-allowed"
                >
                    <Download className="w-4 h-4" />
                </button>
                <div className="w-px h-4 bg-neutral-200 mx-1" />
                <button
                    onClick={onRefresh}
                    disabled={isLoading}
                    title="Atualizar"
                    className="p-1.5 text-neutral-500 hover:text-neutral-800 hover:bg-neutral-100 rounded-md transition-colors disabled:opacity-50"
                >
                    <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                </button>
                {onClose && (
                    <button
                        onClick={onClose}
                        title="Fechar"
                        className="p-1.5 text-neutral-500 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                    >
                        <X className="w-4 h-4" />
                    </button>
                )}
            </div>
        </div>
    );
}
