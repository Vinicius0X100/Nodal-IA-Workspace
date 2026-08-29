import React from 'react';
import { Menu } from 'lucide-react';

interface Sheet {
    title: string;
}

interface Props {
    sheets: Sheet[];
    activeSheetTitle: string;
    onSelectSheet: (title: string) => void;
}

export default function SpreadsheetTabs({ sheets, activeSheetTitle, onSelectSheet }: Props) {
    if (!sheets || sheets.length === 0) return null;

    return (
        <div className="flex items-center border-t border-neutral-200 bg-neutral-100 shrink-0 h-9">
            <div className="px-2 flex items-center justify-center text-neutral-400">
                <Menu className="w-4 h-4" />
            </div>
            
            <div className="flex-1 flex items-end h-full overflow-x-auto scrollbar-hide">
                {sheets.map(sheet => {
                    const isActive = activeSheetTitle === sheet.title;
                    
                    return (
                        <button
                            key={sheet.title}
                            onClick={() => onSelectSheet(sheet.title)}
                            className={`
                                h-8 px-4 text-xs font-medium border-r border-t rounded-t-lg transition-colors whitespace-nowrap
                                ${isActive 
                                    ? 'bg-white text-[#0F9D58] border-neutral-200 border-t-white shadow-[0_-1px_0_#fff]' 
                                    : 'bg-neutral-50 text-neutral-500 border-neutral-200 hover:bg-neutral-100'
                                }
                            `}
                            style={isActive ? { marginBottom: '-1px' } : {}}
                        >
                            {sheet.title}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
