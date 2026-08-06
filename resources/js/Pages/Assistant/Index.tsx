import React, { useEffect, useRef, useState } from 'react';
import { Head, router, useForm, Link } from '@inertiajs/react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Plus, Search, Trash2, Pencil, Share2,
    Send, Paperclip, Mic, MessageSquare, ArrowLeft,
    FileText, FileSpreadsheet, BarChart3, Search as SearchIcon,
    FileSearch, PresentationIcon, X, Check, Loader2,
    Sparkles, Menu, Edit, MoreHorizontal, Pin, PinOff, Edit2, Trash
} from 'lucide-react';
import { Toaster, toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Message {
    id: number;
    uuid: string;
    role: 'user' | 'assistant' | 'system' | 'tool';
    content: string;
    created_at: string;
}

interface Conversation {
    uuid: string;
    title: string;
    status: string;
    created_at: string;
}

interface ConversationItem {
    uuid: string;
    title: string;
    is_pinned?: boolean;
    updated_at: string;
}

interface Group {
    label: string;
    items: ConversationItem[];
}

interface Props {
    conversation: Conversation | null;
    messages: Message[];
    groups: Group[];
}

// ─── Animations ───────────────────────────────────────────────────────────────

const containerVariants = {
    hidden: { opacity: 0 },
    show: {
        opacity: 1,
        transition: { staggerChildren: 0.05, delayChildren: 0.1 }
    }
};

const itemVariants = {
    hidden: { opacity: 0, y: 15, filter: 'blur(4px)' },
    show: { 
        opacity: 1, 
        y: 0, 
        filter: 'blur(0px)',
        transition: { type: 'spring', stiffness: 300, damping: 24 } 
    }
};

// ─── Skeleton / Shimmer ───────────────────────────────────────────────────────

function ShimmerSkeleton() {
    return (
        <div className="flex flex-col gap-8 py-8 px-4 w-full max-w-3xl mx-auto">
            <div className="flex justify-end w-full animate-pulse">
                <div className="w-64 h-16 bg-neutral-100 rounded-[2rem] rounded-br-sm" />
            </div>
            <div className="flex gap-4 w-full animate-pulse">
                <div className="w-8 h-8 rounded-full bg-neutral-100 flex-shrink-0" />
                <div className="flex flex-col gap-3 w-full max-w-md mt-1">
                    <div className="w-full h-4 bg-neutral-100 rounded-md" />
                    <div className="w-5/6 h-4 bg-neutral-100 rounded-md" />
                    <div className="w-4/6 h-4 bg-neutral-100 rounded-md" />
                </div>
            </div>
        </div>
    );
}

// ─── Message Bubble ───────────────────────────────────────────────────────────

function MessageBubble({ message }: { message: Message }) {
    const isUser = message.role === 'user';

    if (isUser) {
        return (
            <motion.div 
                layout
                initial={{ opacity: 0, y: 10, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                className="flex justify-end px-4 py-3 w-full max-w-3xl mx-auto"
            >
                <div className="max-w-[75%] bg-[#0048AA] text-white px-5 py-3.5 rounded-3xl rounded-br-sm text-[15px] leading-relaxed shadow-sm font-medium">
                    {message.content}
                </div>
            </motion.div>
        );
    }

    return (
        <motion.div 
            layout
            initial={{ opacity: 0, y: 10, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            className="flex gap-4 px-4 py-3 w-full max-w-3xl mx-auto group"
        >
            <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0 mt-1 shadow-sm">
                <Sparkles className="w-4 h-4 text-blue-600" />
            </div>
            <div className="max-w-[85%] text-neutral-800 text-[15px] leading-relaxed prose prose-neutral prose-p:leading-relaxed max-w-none">
                <ReactMarkdown
                    remarkPlugins={[remarkGfm]}
                    components={{
                        code: ({ node, className, children, ...props }) => {
                            const isBlock = className?.includes('language-');
                            return isBlock ? (
                                <pre className="rounded-2xl p-4 overflow-x-auto my-4 bg-neutral-50 border border-neutral-200 text-neutral-800 shadow-sm">
                                    <code className={`${className} text-sm font-mono`} {...props}>
                                        {children}
                                    </code>
                                </pre>
                            ) : (
                                <code className="bg-neutral-100 text-blue-700 px-1.5 py-0.5 rounded-md text-sm font-mono border border-neutral-200" {...props}>
                                    {children}
                                </code>
                            );
                        },
                        table: ({ children }) => (
                            <div className="overflow-x-auto my-4 rounded-xl border border-neutral-200 bg-white shadow-sm">
                                <table className="w-full border-collapse text-sm">{children}</table>
                            </div>
                        ),
                        th: ({ children }) => (
                            <th className="border-b border-neutral-200 bg-neutral-50 px-4 py-3 text-left font-medium text-neutral-900">{children}</th>
                        ),
                        td: ({ children }) => (
                            <td className="border-b border-neutral-100 px-4 py-3 text-neutral-700">{children}</td>
                        ),
                        a: ({ href, children }) => (
                            <a href={href} target="_blank" rel="noreferrer" className="text-blue-600 hover:text-blue-700 underline underline-offset-4 decoration-blue-200 hover:decoration-blue-400 transition-colors">
                                {children}
                            </a>
                        ),
                    }}
                >
                    {message.content}
                </ReactMarkdown>
            </div>
        </motion.div>
    );
}

// ─── Empty State ──────────────────────────────────────────────────────────────

const suggestions = [
    { icon: FileSearch, label: 'Encontrar contratos', text: 'Encontre contratos relacionados a' },
    { icon: FileText, label: 'Resumir documentos', text: 'Resuma os principais pontos de' },
    { icon: SearchIcon, label: 'Pesquisar clientes', text: 'Pesquise informações sobre o cliente' },
    { icon: FileSpreadsheet, label: 'Encontrar planilhas', text: 'Encontre planilhas sobre' },
    { icon: BarChart3, label: 'Analisar indicadores', text: 'Analise os indicadores de' },
    { icon: PresentationIcon, label: 'Criar relatório', text: 'Crie um relatório sobre' },
];

function EmptyState({ onSuggestion }: { onSuggestion: (text: string) => void }) {
    return (
        <motion.div 
            variants={containerVariants}
            initial="hidden"
            animate="show"
            className="flex flex-col items-center justify-center h-full px-6 w-full max-w-3xl mx-auto py-10"
        >
            <motion.div layoutId="nodal-logo" className="mb-8 flex items-center justify-center w-32">
                <img src="/images/Nodal-Logo.png" alt="Nodal" className="w-full h-auto object-contain drop-shadow-sm" />
            </motion.div>

            <motion.h1 variants={itemVariants} className="text-3xl font-semibold text-neutral-900 mb-3 text-center tracking-tight">
                Como podemos ajudar hoje?
            </motion.h1>

            <motion.div variants={containerVariants} className="grid grid-cols-2 md:grid-cols-3 gap-4 w-full max-w-4xl mt-6">
                {suggestions.map((s, i) => {
                    const Icon = s.icon;
                    return (
                        <motion.button
                            variants={itemVariants}
                            key={s.label}
                            onClick={() => onSuggestion(s.text + ' ')}
                            className="group relative overflow-hidden flex flex-col gap-3 p-5 bg-white hover:bg-neutral-50 border border-neutral-200 hover:border-blue-200 rounded-3xl text-left transition-all duration-300 shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                        >
                            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-blue-50/50 to-transparent translate-x-[-200%] group-hover:translate-x-[200%] transition-transform duration-1000 ease-in-out z-10" />
                            
                            <div className="w-10 h-10 rounded-2xl bg-neutral-50 border border-neutral-100 flex items-center justify-center group-hover:bg-blue-50 group-hover:border-blue-100 transition-colors">
                                <Icon className="w-5 h-5 text-neutral-400 group-hover:text-blue-600 transition-colors" />
                            </div>
                            <span className="text-sm text-neutral-600 group-hover:text-neutral-900 font-medium transition-colors">
                                {s.label}
                            </span>
                        </motion.button>
                    );
                })}
            </motion.div>
        </motion.div>
    );
}

// ─── Chat Header ──────────────────────────────────────────────────────────────

function ChatHeader({
    conversation,
    onRename,
    onDelete,
    onToggleSidebar
}: {
    conversation: Conversation | null;
    onRename: (title: string) => void;
    onDelete: () => void;
    onToggleSidebar: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const [title, setTitle] = useState(conversation?.title || '');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (conversation) setTitle(conversation.title);
    }, [conversation]);

    useEffect(() => {
        if (editing) inputRef.current?.focus();
    }, [editing]);

    const handleConfirm = () => {
        if (title.trim() && title !== conversation?.title) {
            onRename(title.trim());
        }
        setEditing(false);
    };

    return (
        <header className="h-16 flex items-center justify-between px-4 sticky top-0 z-20 bg-white/80 backdrop-blur-xl border-b border-neutral-100">
            <div className="flex items-center gap-4 flex-1">
                <button
                    onClick={onToggleSidebar}
                    className="p-2 rounded-xl hover:bg-neutral-100 text-neutral-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                >
                    <Menu className="w-5 h-5" />
                </button>

                {conversation ? (
                    <Link href={route('dashboard')} className="flex items-center gap-3 hover:opacity-80 transition-opacity">
                        <div className="w-8 h-8 rounded-xl bg-neutral-100 flex items-center justify-center">
                            <ArrowLeft className="w-4 h-4 text-neutral-600" />
                        </div>
                        <motion.div layoutId="nodal-logo" className="w-20 hidden sm:block">
                            <img src="/images/Nodal-Logo.png" alt="Nodal" className="w-full h-auto object-contain" />
                        </motion.div>
                    </Link>
                ) : (
                    <Link href={route('dashboard')} className="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-neutral-100 transition-colors text-sm font-medium text-neutral-600">
                        <ArrowLeft className="w-4 h-4" />
                        Dashboard
                    </Link>
                )}
            </div>

            {conversation && (
                <div className="flex items-center justify-center flex-1 max-w-sm absolute left-1/2 -translate-x-1/2">
                    {editing ? (
                        <div className="flex items-center gap-2 w-full">
                            <input
                                ref={inputRef}
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') handleConfirm();
                                    if (e.key === 'Escape') { setTitle(conversation.title); setEditing(false); }
                                }}
                                className="bg-white text-neutral-900 text-sm rounded-xl px-4 py-1.5 flex-1 outline-none border border-neutral-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all font-medium text-center shadow-sm"
                            />
                        </div>
                    ) : (
                        <div 
                            className="flex items-center gap-2 cursor-pointer group px-3 py-1.5 rounded-lg hover:bg-neutral-50 transition-colors"
                            onClick={() => setEditing(true)}
                        >
                            <h2 className="text-[15px] font-medium text-neutral-800 truncate tracking-tight">
                                {conversation.title || 'Nova Conversa'}
                            </h2>
                            <Edit className="w-3.5 h-3.5 text-neutral-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                    )}
                </div>
            )}

            <div className="flex items-center gap-2 flex-1 justify-end">
                {conversation && (
                    <>
                        <button
                            title="Compartilhar"
                            disabled
                            className="p-2 rounded-xl text-neutral-300 cursor-not-allowed hover:bg-neutral-50 transition-colors hidden sm:block"
                        >
                            <Share2 className="w-4 h-4" />
                        </button>
                        <button
                            onClick={onDelete}
                            title="Excluir conversa"
                            className="p-2 rounded-xl text-neutral-400 hover:bg-red-50 hover:text-red-500 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500/20"
                        >
                            <Trash2 className="w-4 h-4" />
                        </button>
                    </>
                )}
                <button
                    onClick={() => router.post(route('assistant.store'))}
                    title="Nova Conversa"
                    className="p-2 rounded-xl text-neutral-600 hover:bg-neutral-100 transition-colors ml-1"
                >
                    <Plus className="w-5 h-5" />
                </button>
            </div>
        </header>
    );
}

// ─── Conversation Sidebar ─────────────────────────────────────────────────────

function ConversationSidebar({
    groups,
    activeUuid,
    isOpen,
    onClose
}: {
    groups: Group[];
    activeUuid?: string;
    isOpen: boolean;
    onClose: () => void;
}) {
    const [search, setSearch] = useState('');
    const [editingUuid, setEditingUuid] = useState<string | null>(null);
    const [editTitle, setEditTitle] = useState('');
    const [conversationToDelete, setConversationToDelete] = useState<ConversationItem | null>(null);
    const editInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editingUuid) {
            editInputRef.current?.focus();
        }
    }, [editingUuid]);

    const handleRename = (uuid: string) => {
        if (!editTitle.trim()) {
            setEditingUuid(null);
            return;
        }
        router.patch(route('assistant.update', uuid), { title: editTitle }, { preserveScroll: true });
        setEditingUuid(null);
    };

    const handleTogglePin = (uuid: string, currentPin: boolean) => {
        router.patch(route('assistant.update', uuid), { is_pinned: !currentPin }, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!conversationToDelete) return;
        router.delete(route('assistant.destroy', conversationToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setConversationToDelete(null);
                if (activeUuid === conversationToDelete.uuid) {
                    router.visit(route('assistant.index'));
                }
            }
        });
    };

    const filteredGroups = search
        ? groups.map((g) => ({
            ...g,
            items: g.items.filter((i) =>
                i.title.toLowerCase().includes(search.toLowerCase())
            ),
        })).filter((g) => g.items.length > 0)
        : groups;

    return (
        <AnimatePresence>
            {isOpen && (
                <>
                    {/* Backdrop */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={onClose}
                        className="fixed inset-0 bg-neutral-900/20 backdrop-blur-sm z-30"
                    />
                    
                    {/* Sidebar */}
                    <motion.div
                        initial={{ x: '-100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '-100%' }}
                        transition={{ type: 'spring', damping: 25, stiffness: 200 }}
                        className="fixed inset-y-0 left-0 w-[280px] bg-white border-r border-neutral-200 z-40 flex flex-col shadow-2xl"
                    >
                        <div className="p-4 flex items-center justify-between border-b border-neutral-100">
                            <h3 className="font-medium text-neutral-800">Histórico</h3>
                            <button onClick={onClose} className="p-2 rounded-xl hover:bg-neutral-100 text-neutral-500 transition-colors">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="p-4">
                            <div className="relative">
                                <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Pesquisar..."
                                    className="w-full pl-10 pr-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-sm text-neutral-900 placeholder:text-neutral-400 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all font-medium"
                                />
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto px-3 pb-6 space-y-6">
                            {filteredGroups.length === 0 ? (
                                <p className="text-sm text-neutral-400 text-center pt-8 px-4 font-medium">
                                    {search ? 'Nada encontrado.' : 'Seu histórico aparecerá aqui.'}
                                </p>
                            ) : (
                                filteredGroups.map((group) => (
                                    <div key={group.label}>
                                        <p className="text-[11px] font-semibold text-neutral-400 uppercase tracking-widest px-4 mb-2">
                                            {group.label}
                                        </p>
                                        <div className="space-y-1">
                                            {group.items.map((item) => (
                                                <div key={item.uuid} className="group/item relative flex items-center">
                                                    {editingUuid === item.uuid ? (
                                                        <div className="w-full px-2 py-1.5 flex items-center gap-2">
                                                            <Input
                                                                ref={editInputRef}
                                                                value={editTitle}
                                                                onChange={(e) => setEditTitle(e.target.value)}
                                                                onKeyDown={(e) => {
                                                                    if (e.key === 'Enter') handleRename(item.uuid);
                                                                    if (e.key === 'Escape') setEditingUuid(null);
                                                                }}
                                                                onBlur={() => handleRename(item.uuid)}
                                                                className="h-8 text-[13px]"
                                                            />
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <button
                                                                onClick={() => {
                                                                    router.visit(route('assistant.show', item.uuid));
                                                                    onClose();
                                                                }}
                                                                className={`w-full text-left pl-4 pr-10 py-2.5 rounded-xl text-[13px] font-medium transition-all duration-200 flex items-center gap-3 focus:outline-none focus:ring-2 focus:ring-blue-500/20 ${
                                                                    item.uuid === activeUuid
                                                                        ? 'bg-blue-50 text-blue-700'
                                                                        : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100'
                                                                }`}
                                                            >
                                                                <MessageSquare className={`w-4 h-4 flex-shrink-0 ${item.uuid === activeUuid ? 'text-blue-500' : 'text-neutral-400'}`} />
                                                                <span className="truncate flex-1">{item.title || 'Nova Conversa'}</span>
                                                                {item.is_pinned && <Pin className="w-3.5 h-3.5 text-neutral-400 flex-shrink-0" />}
                                                            </button>

                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <button className="absolute right-2 opacity-0 group-hover/item:opacity-100 p-1.5 rounded-lg hover:bg-neutral-200 text-neutral-500 transition-all focus:opacity-100">
                                                                        <MoreHorizontal className="w-4 h-4" />
                                                                    </button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end" className="w-48">
                                                                    <DropdownMenuItem onClick={() => {
                                                                        setEditingUuid(item.uuid);
                                                                        setEditTitle(item.title);
                                                                    }}>
                                                                        <Edit2 className="w-4 h-4 mr-2" />
                                                                        Renomear
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem onClick={() => handleTogglePin(item.uuid, item.is_pinned ?? false)}>
                                                                        {item.is_pinned ? (
                                                                            <><PinOff className="w-4 h-4 mr-2" /> Desafixar</>
                                                                        ) : (
                                                                            <><Pin className="w-4 h-4 mr-2" /> Fixar</>
                                                                        )}
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem className="text-red-600 focus:bg-red-50 focus:text-red-700" onClick={() => setConversationToDelete(item)}>
                                                                        <Trash className="w-4 h-4 mr-2" />
                                                                        Excluir
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </motion.div>

                    {/* Delete Confirmation Dialog */}
                    <Dialog open={!!conversationToDelete} onOpenChange={(open) => !open && setConversationToDelete(null)}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir conversa?</DialogTitle>
                                <DialogDescription>
                                    Tem certeza que deseja excluir "{conversationToDelete?.title}"? Esta ação não pode ser desfeita.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setConversationToDelete(null)}>Cancelar</Button>
                                <Button variant="destructive" onClick={handleDelete}>Excluir</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </>
            )}
        </AnimatePresence>
    );
}

// ─── Message Input ─────────────────────────────────────────────────────────────

function MessageInput({
    conversationUuid,
    value,
    onChange,
}: {
    conversationUuid?: string;
    value: string;
    onChange: (v: string) => void;
}) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const { post, processing } = useForm({ content: value });

    const handleSubmit = () => {
        if (!value.trim() || processing) return;

        if (!conversationUuid) {
            router.post(
                route('assistant.store'),
                { message: value },
                {
                    preserveScroll: false,
                    onSuccess: () => onChange(''),
                }
            );
        } else {
            router.post(
                route('assistant.messages.store', conversationUuid),
                { content: value },
                {
                    preserveScroll: true,
                    onSuccess: () => onChange(''),
                }
            );
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit();
        }
    };

    useEffect(() => {
        const ta = textareaRef.current;
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 200) + 'px';
    }, [value]);

    const disabled = false; // Never disabled, can type in empty state
    const hasValue = value.trim().length > 0;

    return (
        <div className="px-4 pb-8 pt-4 w-full max-w-3xl mx-auto flex-shrink-0 relative bg-white">
            <div className={`relative bg-neutral-50/50 border rounded-3xl transition-all duration-300 shadow-sm ${
                !hasValue && !conversationUuid ? 'border-neutral-200 opacity-90' : 'border-neutral-200 focus-within:border-blue-400 focus-within:ring-4 focus-within:ring-blue-500/10 focus-within:bg-white'
            }`}>
                <textarea
                    ref={textareaRef}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={handleKeyDown}
                    disabled={processing}
                    placeholder="Mande uma mensagem para a IA..."
                    rows={1}
                    className="w-full bg-transparent text-neutral-900 placeholder:text-neutral-400 text-[15px] resize-none outline-none px-6 pt-5 pb-16 leading-relaxed max-h-[250px] font-medium"
                />

                <div className="absolute bottom-3 left-4 right-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <button disabled className="p-2 rounded-xl text-neutral-400 cursor-not-allowed hover:bg-neutral-100 transition-colors">
                            <Paperclip className="w-5 h-5" />
                        </button>
                        <button disabled className="p-2 rounded-xl text-neutral-400 cursor-not-allowed hover:bg-neutral-100 transition-colors">
                            <Mic className="w-5 h-5" />
                        </button>
                    </div>

                    <button
                        onClick={handleSubmit}
                        disabled={disabled || !hasValue || processing}
                        className={`p-2.5 rounded-2xl transition-all duration-300 flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-blue-500/50 ${
                            hasValue && !disabled
                                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg hover:-translate-y-0.5 shadow-blue-600/20'
                                : 'bg-neutral-200 text-neutral-400 cursor-not-allowed'
                        }`}
                    >
                        {processing ? (
                            <Loader2 className="w-5 h-5 animate-spin" />
                        ) : (
                            <ArrowLeft className="w-5 h-5 rotate-[135deg]" />
                        )}
                    </button>
                </div>
            </div>

            <p className="text-center text-xs text-neutral-400 mt-4 font-medium">
                A IA pode cometer erros. Verifique informações importantes.
            </p>
        </div>
    );
}

// ─── Main Page ─────────────────────────────────────────────────────────────────

export default function AssistantIndex({ conversation, messages, groups }: Props) {
    const [inputValue, setInputValue] = useState('');
    const [isLoading, setIsLoading] = useState(true);
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const timer = setTimeout(() => setIsLoading(false), 500);
        return () => clearTimeout(timer);
    }, [conversation?.uuid]);

    useEffect(() => {
        if (!isLoading) {
            messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages, isLoading]);

    const handleRename = (title: string) => {
        if (!conversation) return;
        router.patch(route('assistant.update', conversation.uuid), { title }, {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        if (!conversation) return;
        if (confirm('Excluir esta conversa? Esta ação não pode ser desfeita.')) {
            router.delete(route('assistant.destroy', conversation.uuid));
        }
    };

    return (
        <>
            <Head title="IA" />
            
            <Toaster position="top-center" theme="light" richColors />

            {/* Container fullscreen clean and white */}
            <div className="flex flex-col h-screen w-screen bg-white text-neutral-900 overflow-hidden font-sans">
                
                <ConversationSidebar
                    groups={groups}
                    activeUuid={conversation?.uuid}
                    isOpen={isSidebarOpen}
                    onClose={() => setIsSidebarOpen(false)}
                />

                <ChatHeader
                    conversation={conversation}
                    onRename={handleRename}
                    onDelete={handleDelete}
                    onToggleSidebar={() => setIsSidebarOpen(true)}
                />

                {/* Main Area */}
                <div className="flex-1 flex flex-col overflow-hidden relative">
                    <div className="flex-1 overflow-y-auto w-full scroll-smooth" style={{ scrollbarWidth: 'none' }}>
                        
                        <AnimatePresence mode="wait">
                            {isLoading && conversation ? (
                                <motion.div key="skeleton" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.2 }}>
                                    <ShimmerSkeleton />
                                </motion.div>
                            ) : !conversation || messages.length === 0 ? (
                                <motion.div key="empty" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.2 }}>
                                    <EmptyState onSuggestion={(text) => {
                                        setInputValue(text);
                                        if (!conversation) {
                                            router.post(route('assistant.store'), {}, { preserveScroll: false });
                                        }
                                    }} />
                                </motion.div>
                            ) : (
                                <motion.div key="messages" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.3 }} className="py-8 space-y-6">
                                    {messages.map((msg) => (
                                        <MessageBubble key={msg.uuid} message={msg} />
                                    ))}
                                    <div ref={messagesEndRef} className="h-4" />
                                </motion.div>
                            )}
                        </AnimatePresence>

                    </div>

                    <MessageInput
                        conversationUuid={conversation?.uuid}
                        value={inputValue}
                        onChange={setInputValue}
                    />
                </div>
            </div>
        </>
    );
}
