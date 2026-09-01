import React from 'react';

export interface SpreadsheetCellProps {
    value: any;
    formatted_value: string | null;
    formula: string | null;
    format?: {
        bold?: boolean;
        italic?: boolean;
        text_color?: string;
        background_color?: string;
        horizontal_alignment?: 'left' | 'center' | 'right';
        vertical_alignment?: 'top' | 'middle' | 'bottom';
        wrap?: boolean;
    };
}

interface Props {
    rowIndex: number;
    colIndex: number;
    cell: SpreadsheetCellProps | null;
    isSelected: boolean;
    isEditing?: boolean;
    editingValue?: string;
    isMutating?: boolean;
    isFrozenRow: boolean;
    isFrozenCol: boolean;
    rowSpan?: number;
    colSpan?: number;
    onClick: () => void;
    onDoubleClick?: () => void;
    onEditingValueChange?: (value: string) => void;
    onSaveValue?: (value: string, moveToNext?: 'down' | 'right' | 'left' | 'none') => void;
    onCancelEdit?: () => void;
}

export default function SpreadsheetCell({
    rowIndex,
    colIndex,
    cell,
    isSelected,
    isEditing,
    editingValue,
    isMutating,
    isFrozenRow,
    isFrozenCol,
    rowSpan,
    colSpan,
    onClick,
    onDoubleClick,
    onEditingValueChange,
    onSaveValue,
    onCancelEdit
}: Props) {
    if (!cell) {
        return (
            <td 
                className={`
                    border-r border-b border-neutral-200 bg-white
                    ${isFrozenRow ? 'sticky bg-white shadow-[0_1px_0_#e5e5e5]' : ''}
                    ${isFrozenCol ? 'sticky left-0 bg-white shadow-[1px_0_0_#e5e5e5]' : ''}
                    ${isFrozenRow && isFrozenCol ? 'z-20' : (isFrozenRow || isFrozenCol ? 'z-10' : '')}
                `}
                style={{
                    ...(isFrozenRow ? { top: '24px' } : {}), // 24px is header height (A, B, C...)
                    ...(isFrozenCol ? { left: '40px' } : {}) // 40px is row header width (1, 2, 3...)
                }}
            />
        );
    }

    const { format, value, formatted_value } = cell;

    const style: React.CSSProperties = {
        backgroundColor: format?.background_color || '#ffffff',
        color: format?.text_color || '#1e293b',
        fontWeight: format?.bold ? '600' : 'normal',
        fontStyle: format?.italic ? 'italic' : 'normal',
        textAlign: format?.horizontal_alignment || (typeof value === 'number' ? 'right' : 'left'),
        verticalAlign: format?.vertical_alignment || 'bottom',
        whiteSpace: format?.wrap ? 'normal' : 'nowrap',
    };

    if (isFrozenRow) {
        style.top = '24px';
    }
    if (isFrozenCol) {
        style.left = '40px';
    }

    const displayValue = formatted_value !== null ? formatted_value : (value !== null ? String(value) : '');

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            onSaveValue?.(editingValue || '', e.shiftKey ? 'none' : 'down');
            e.preventDefault();
        } else if (e.key === 'Tab') {
            onSaveValue?.(editingValue || '', e.shiftKey ? 'left' : 'right');
            e.preventDefault();
        } else if (e.key === 'Escape') {
            onCancelEdit?.();
            e.preventDefault();
        }
    };

    return (
        <td
            onClick={onClick}
            onDoubleClick={onDoubleClick}
            style={style}
            colSpan={colSpan}
            rowSpan={rowSpan}
            className={`
                relative px-1.5 py-0.5 min-h-[21px] max-h-[100px] cursor-cell
                border-r border-b border-neutral-200
                ${format?.wrap ? 'break-words' : 'overflow-hidden'}
                ${isFrozenRow || isFrozenCol ? 'sticky' : ''}
                ${isFrozenRow && isFrozenCol ? 'z-20' : (isFrozenRow || isFrozenCol ? 'z-10' : '')}
                ${isSelected ? 'outline outline-2 outline-[#0B57D0] outline-offset-[-2px] z-30' : ''}
                ${isMutating && isSelected ? 'opacity-50' : ''}
            `}
        >
            {isEditing ? (
                <input
                    autoFocus
                    type="text"
                    value={editingValue || ''}
                    onChange={(e) => onEditingValueChange?.(e.target.value)}
                    onKeyDown={handleKeyDown}
                    disabled={isMutating}
                    className="absolute inset-0 w-full h-full p-1 bg-white outline-none border-none shadow-[inset_0_0_0_2px_#0B57D0]"
                    style={{ ...style, backgroundColor: 'white', color: 'black' }}
                />
            ) : (
                displayValue
            )}
        </td>
    );
}
