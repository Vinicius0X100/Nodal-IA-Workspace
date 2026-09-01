import React from 'react';
import { 
    Undo2, Redo2, Bold, Italic, Palette, Baseline, 
    AlignLeft, AlignCenter, AlignRight, WrapText,
    Grid3X3, Columns, Rows
} from 'lucide-react';

interface Props {
    disabled?: boolean;
    isDraft?: boolean;
    onFormat?: (format: any) => void;
}

export default function SpreadsheetToolbar({ disabled = true, isDraft = false, onFormat }: Props) {
    const Button = ({ 
        icon: Icon, 
        disabled: btnDisabled = disabled, 
        onClick 
    }: { 
        icon: React.ElementType, 
        disabled?: boolean, 
        onClick?: () => void 
    }) => (
        <button 
            disabled={btnDisabled}
            onClick={onClick}
            className="p-1.5 text-neutral-400 rounded hover:bg-neutral-100 disabled:opacity-40 disabled:hover:bg-transparent transition-colors"
        >
            <Icon className="w-4 h-4" />
        </button>
    );

    const Divider = () => <div className="w-px h-4 bg-neutral-200 mx-1" />;

    const formatBtnsDisabled = disabled || !isDraft; // Disable format buttons if not draft or global disabled

    return (
        <div className="flex items-center gap-1 px-4 py-1.5 border-b border-neutral-200 bg-neutral-50 shrink-0 overflow-x-auto scrollbar-hide">
            <Button icon={Undo2} disabled />
            <Button icon={Redo2} disabled />
            
            <Divider />
            
            {/* Number format placeholder */}
            <button disabled className="text-xs font-medium px-2 py-1 text-neutral-400 rounded hover:bg-neutral-100 disabled:opacity-40 disabled:hover:bg-transparent transition-colors">
                123
            </button>
            
            <Divider />

            <Button icon={Bold} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ bold: true })} />
            <Button icon={Italic} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ italic: true })} />
            
            <Button icon={Baseline} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ text_color: '#0B57D0' })} /> 
            <Button icon={Palette} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ background_color: '#e8f0fe' })} /> 
            
            <Button icon={Grid3X3} disabled />
            
            <Divider />
            
            <Button icon={AlignLeft} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ horizontal_alignment: 'left' })} />
            <Button icon={AlignCenter} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ horizontal_alignment: 'center' })} />
            <Button icon={AlignRight} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ horizontal_alignment: 'right' })} />
            <Button icon={WrapText} disabled={formatBtnsDisabled} onClick={() => onFormat?.({ wrap: true })} />
            
            <Divider />
            
            <Button icon={Columns} disabled /> {/* Merge placeholder */}
            <Button icon={Rows} disabled /> {/* Freeze placeholder */}
        </div>
    );
}
