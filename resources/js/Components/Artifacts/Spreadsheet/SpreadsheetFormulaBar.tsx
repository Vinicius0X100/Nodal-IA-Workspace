import React from 'react';

interface Props {
    activeCellRef: string | null;
    activeCellValue: string;
    isEditing?: boolean;
    isMutating?: boolean;
    disabled?: boolean;
    onChange?: (value: string) => void;
    onSave?: () => void;
    onCancel?: () => void;
}

export default function SpreadsheetFormulaBar({ 
    activeCellRef, 
    activeCellValue,
    isEditing,
    isMutating,
    disabled,
    onChange,
    onSave,
    onCancel
}: Props) {
    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            onSave?.();
            e.preventDefault();
        } else if (e.key === 'Escape') {
            onCancel?.();
            e.preventDefault();
        }
    };

    return (
        <div className="flex items-center border-b border-neutral-200 bg-white shrink-0">
            <div className="w-12 border-r border-neutral-200 flex items-center justify-center py-1.5 shrink-0 bg-neutral-50/50">
                <span className="text-xs font-semibold text-neutral-600 font-mono tracking-tight">
                    {activeCellRef || ''}
                </span>
            </div>
            
            <div className="flex-1 px-3 py-1.5 flex items-center bg-white min-w-0">
                <div className="text-sm text-neutral-400 select-none mr-2 italic font-serif">fx</div>
                <div className="flex-1 min-w-0">
                    <input
                        type="text"
                        value={activeCellValue || ''}
                        onChange={(e) => onChange?.(e.target.value)}
                        onKeyDown={handleKeyDown}
                        disabled={disabled || isMutating || !activeCellRef}
                        className="w-full bg-transparent outline-none text-sm text-neutral-800 font-mono disabled:opacity-50"
                    />
                </div>
            </div>
        </div>
    );
}
