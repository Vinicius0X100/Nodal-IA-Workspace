import React from 'react';
import { 
    Undo2, Redo2, Bold, Italic, Palette, Baseline, 
    AlignLeft, AlignCenter, AlignRight, WrapText,
    BorderAll, Columns, Rows
} from 'lucide-react';

export default function SpreadsheetToolbar() {
    // Toolbar is purely visual for now, as requested.
    // When editing is supported, these will be active.

    const Button = ({ icon: Icon, disabled = true }: { icon: React.ElementType, disabled?: boolean }) => (
        <button 
            disabled={disabled}
            className="p-1.5 text-neutral-400 rounded hover:bg-neutral-100 disabled:opacity-40 disabled:hover:bg-transparent transition-colors"
        >
            <Icon className="w-4 h-4" />
        </button>
    );

    const Divider = () => <div className="w-px h-4 bg-neutral-200 mx-1" />;

    return (
        <div className="flex items-center gap-1 px-4 py-1.5 border-b border-neutral-200 bg-neutral-50 shrink-0 overflow-x-auto scrollbar-hide">
            <Button icon={Undo2} />
            <Button icon={Redo2} />
            
            <Divider />
            
            {/* Number format placeholder */}
            <button disabled className="text-xs font-medium px-2 py-1 text-neutral-400 rounded hover:bg-neutral-100 disabled:opacity-40 disabled:hover:bg-transparent transition-colors">
                123
            </button>
            
            <Divider />

            <Button icon={Bold} />
            <Button icon={Italic} />
            <Button icon={Baseline} /> {/* Text Color */}
            <Button icon={Palette} /> {/* Background Color */}
            <Button icon={BorderAll} />
            
            <Divider />
            
            <Button icon={AlignLeft} />
            <Button icon={AlignCenter} />
            <Button icon={AlignRight} />
            <Button icon={WrapText} />
            
            <Divider />
            
            <Button icon={Columns} /> {/* Merge placeholder */}
            <Button icon={Rows} /> {/* Freeze placeholder */}
        </div>
    );
}
