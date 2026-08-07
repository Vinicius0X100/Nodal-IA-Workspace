import React, { useState, useEffect, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { 
    Search, RefreshCw, Folder, FileText, FileSpreadsheet, FileIcon, 
    Calendar, Image as ImageIcon, FileAudio, FileVideo, Files, Clock, 
    MoreHorizontal, ExternalLink, HardDrive, FileImage, LayoutGrid, List as ListIcon,
    Users, AlertCircle
} from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import { ptBR } from 'date-fns/locale';

interface Resource {
    id: number;
    uuid: string;
    provider: string;
    resource_type: string;
    name: string;
    description: string;
    mime_type: string;
    url: string;
    icon: string;
    owner_name: string;
    owner_email: string;
    is_folder: boolean;
    is_shared: boolean;
    size: number;
    updated_by_provider_at: string;
    last_synced_at: string;
}

interface ResourcesIndexProps {
    resources: {
        data: Resource[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
        type?: string;
        is_shared?: boolean;
        provider?: string;
    };
    dashboard: {
        total: number;
        folders: number;
        documents: number;
        spreadsheets: number;
        pdfs: number;
        calendars: number;
        last_sync: string | null;
    };
}

export default function ResourcesIndex({ resources, filters, dashboard }: ResourcesIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [isSyncing, setIsSyncing] = useState(false);
    const [showSyncWarning, setShowSyncWarning] = useState(false);
    const [isSearching, setIsSearching] = useState(false);
    
    const syncWarningTimeout = useRef<NodeJS.Timeout | null>(null);

    // Debounce de Busca Assíncrona
    useEffect(() => {
        if (searchQuery === (filters.search || '')) return;

        setIsSearching(true);
        const delayDebounceFn = setTimeout(() => {
            router.get(
                route('resources.index'), 
                { ...filters, search: searchQuery }, 
                { 
                    preserveState: true, 
                    preserveScroll: true,
                    onFinish: () => setIsSearching(false)
                }
            );
        }, 500);

        return () => clearTimeout(delayDebounceFn);
    }, [searchQuery, filters]);

    const handleFilterChange = (type?: string, is_shared?: boolean) => {
        const currentParams: any = { ...filters };
        if (type !== undefined) currentParams.type = type;
        if (is_shared !== undefined) currentParams.is_shared = is_shared;
        
        router.get(route('resources.index'), currentParams, { preserveState: true, preserveScroll: true });
    };

    const handleSync = () => {
        setIsSyncing(true);
        setShowSyncWarning(false);
        
        // Exibe o aviso se a sincronização demorar mais de 3 segundos
        syncWarningTimeout.current = setTimeout(() => {
            setShowSyncWarning(true);
        }, 3000);

        router.post(route('resources.sync'), {}, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setIsSyncing(false);
                setShowSyncWarning(false);
                if (syncWarningTimeout.current) clearTimeout(syncWarningTimeout.current);
            },
        });
    };

    const formatBytes = (bytes: number) => {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    const getIconForType = (type: string, isFolder: boolean) => {
        if (isFolder) return <Folder className="w-5 h-5 text-blue-500" />;
        switch (type) {
            case 'document': return <FileText className="w-5 h-5 text-blue-600" />;
            case 'spreadsheet': return <FileSpreadsheet className="w-5 h-5 text-green-600" />;
            case 'presentation': return <FileIcon className="w-5 h-5 text-yellow-500" />;
            case 'pdf': return <FileIcon className="w-5 h-5 text-red-500" />;
            case 'image': return <ImageIcon className="w-5 h-5 text-purple-500" />;
            case 'video': return <FileVideo className="w-5 h-5 text-red-400" />;
            case 'audio': return <FileAudio className="w-5 h-5 text-yellow-400" />;
            case 'calendar': return <Calendar className="w-5 h-5 text-indigo-500" />;
            default: return <Files className="w-5 h-5 text-neutral-500" />;
        }
    };

    return (
        <AppLayout>
            <Head title="Base de Conhecimento" />

            <div className="flex flex-col h-[calc(100vh-64px)] overflow-hidden bg-neutral-50/50">
                {/* Header Premium Spotlight-style */}
                <div className="bg-white/80 backdrop-blur-xl border-b border-neutral-200 px-6 py-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 z-10 sticky top-0">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-neutral-900 mb-1">Conhecimento</h1>
                        <p className="text-sm font-medium text-neutral-500">
                            Base de dados sincronizada e indexada para a IA corporativa.
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                        <div className="relative w-full sm:w-80 group">
                            <Search className={`absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 transition-colors ${isSearching ? 'text-blue-500 animate-pulse' : 'text-neutral-400 group-focus-within:text-blue-500'}`} />
                            <input
                                type="text"
                                placeholder="Pesquisar em toda a base..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-10 pr-4 py-2.5 bg-neutral-100/80 border-transparent rounded-full font-medium text-neutral-900 placeholder:text-neutral-400 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all outline-none"
                            />
                        </div>

                        <div className="flex flex-col items-end w-full sm:w-auto relative">
                            <button
                                onClick={handleSync}
                                disabled={isSyncing}
                                className="w-full sm:w-auto flex items-center justify-center gap-2 px-5 py-2.5 bg-neutral-900 hover:bg-neutral-800 text-white rounded-full text-sm font-medium transition-all shadow-sm disabled:opacity-70 disabled:cursor-wait"
                            >
                                <RefreshCw className={`w-4 h-4 ${isSyncing ? 'animate-spin' : ''}`} />
                                <span>{isSyncing ? 'Sincronizando...' : 'Forçar Sincronização'}</span>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="flex-1 overflow-y-auto p-6 md:p-8">
                    
                    {/* Alerta de Demora na Sincronização */}
                    {showSyncWarning && (
                        <div className="mb-6 animate-in slide-in-from-top-4 fade-in duration-300">
                            <div className="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 flex gap-3 items-start shadow-sm">
                                <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-600" />
                                <div>
                                    <h4 className="text-sm font-semibold mb-0.5">Sincronização em andamento</h4>
                                    <p className="text-sm text-amber-700/90 leading-relaxed">
                                        Esse processo pode demorar um pouco por conta do grande volume de arquivos que um drive corporativo pode conter. Por favor, aguarde enquanto indexamos todos os dados para a inteligência artificial.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Dashboard Cards Premium */}
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-10">
                        <div className="bg-white p-5 rounded-2xl border border-neutral-200/60 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-neutral-500 text-xs font-bold uppercase tracking-wider">Total</span>
                                <div className="p-1.5 bg-neutral-100 rounded-lg"><HardDrive className="w-4 h-4 text-neutral-600" /></div>
                            </div>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{dashboard.total}</div>
                        </div>
                        <div className="bg-white p-5 rounded-2xl border border-neutral-200/60 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-neutral-500 text-xs font-bold uppercase tracking-wider">Pastas</span>
                                <div className="p-1.5 bg-blue-50 rounded-lg"><Folder className="w-4 h-4 text-blue-500" /></div>
                            </div>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{dashboard.folders}</div>
                        </div>
                        <div className="bg-white p-5 rounded-2xl border border-neutral-200/60 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-neutral-500 text-xs font-bold uppercase tracking-wider">Docs</span>
                                <div className="p-1.5 bg-blue-50 rounded-lg"><FileText className="w-4 h-4 text-blue-600" /></div>
                            </div>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{dashboard.documents}</div>
                        </div>
                        <div className="bg-white p-5 rounded-2xl border border-neutral-200/60 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-neutral-500 text-xs font-bold uppercase tracking-wider">Planilhas</span>
                                <div className="p-1.5 bg-green-50 rounded-lg"><FileSpreadsheet className="w-4 h-4 text-green-600" /></div>
                            </div>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{dashboard.spreadsheets}</div>
                        </div>
                        <div className="bg-white p-5 rounded-2xl border border-neutral-200/60 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-neutral-500 text-xs font-bold uppercase tracking-wider">PDFs</span>
                                <div className="p-1.5 bg-red-50 rounded-lg"><FileIcon className="w-4 h-4 text-red-500" /></div>
                            </div>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{dashboard.pdfs}</div>
                        </div>
                        <div className="bg-gradient-to-br from-neutral-900 to-neutral-800 p-5 rounded-2xl border border-neutral-800 shadow-lg flex flex-col justify-between text-white">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-neutral-400 text-xs font-bold uppercase tracking-wider">Última Sync</span>
                                <div className="p-1.5 bg-neutral-800 rounded-lg"><Clock className="w-4 h-4 text-neutral-300" /></div>
                            </div>
                            <div className="text-base font-semibold tracking-tight mt-2 leading-tight">
                                {dashboard.last_sync ? formatDistanceToNow(new Date(dashboard.last_sync), { addSuffix: true, locale: ptBR }) : 'Pendente'}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col lg:flex-row gap-8">
                        {/* Sidebar Filters Premium */}
                        <div className="w-full lg:w-64 flex-shrink-0">
                            <div className="bg-white/60 backdrop-blur-md border border-neutral-200/60 rounded-2xl p-5 shadow-sm sticky top-4">
                                <h3 className="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4 px-2">Categorias</h3>
                                <nav className="space-y-1.5">
                                    <button 
                                        onClick={() => handleFilterChange('')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${!filters.type && !filters.is_shared ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <Files className="w-4 h-4" /> Todos
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('folder')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.type === 'folder' ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <Folder className="w-4 h-4" /> Pastas
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('document')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.type === 'document' ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <FileText className="w-4 h-4" /> Documentos
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('spreadsheet')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.type === 'spreadsheet' ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <FileSpreadsheet className="w-4 h-4" /> Planilhas
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('presentation')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.type === 'presentation' ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <FileIcon className="w-4 h-4" /> Apresentações
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('pdf')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.type === 'pdf' ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <FileIcon className="w-4 h-4" /> PDFs
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('image')}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.type === 'image' ? 'bg-neutral-900 text-white shadow-md' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <FileImage className="w-4 h-4" /> Imagens
                                    </button>
                                </nav>
                                
                                <h3 className="text-xs font-bold text-neutral-400 uppercase tracking-widest mt-6 mb-4 px-2">Filtros Especiais</h3>
                                <nav>
                                    <button 
                                        onClick={() => handleFilterChange(undefined, true)}
                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${filters.is_shared ? 'bg-blue-50 text-blue-700 shadow-sm border border-blue-100' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'}`}
                                    >
                                        <Users className="w-4 h-4" /> Compartilhados comigo
                                    </button>
                                </nav>
                            </div>
                        </div>

                        {/* Explorer List/Grid */}
                        <div className="flex-1">
                            <div className="flex items-center justify-between mb-6">
                                <div className="text-sm text-neutral-500 font-medium bg-white/60 px-4 py-2 rounded-full border border-neutral-200 shadow-sm">
                                    <span className="text-neutral-900 font-bold">{resources.total}</span> arquivos catalogados
                                </div>
                                <div className="flex items-center bg-white border border-neutral-200 rounded-xl p-1 shadow-sm">
                                    <button 
                                        onClick={() => setViewMode('grid')}
                                        className={`p-2 rounded-lg transition-all ${viewMode === 'grid' ? 'bg-neutral-100 text-neutral-900 shadow-sm' : 'text-neutral-400 hover:text-neutral-700 hover:bg-neutral-50'}`}
                                    >
                                        <LayoutGrid className="w-4 h-4" />
                                    </button>
                                    <button 
                                        onClick={() => setViewMode('list')}
                                        className={`p-2 rounded-lg transition-all ${viewMode === 'list' ? 'bg-neutral-100 text-neutral-900 shadow-sm' : 'text-neutral-400 hover:text-neutral-700 hover:bg-neutral-50'}`}
                                    >
                                        <ListIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {resources.data.length === 0 ? (
                                <div className="bg-white/60 backdrop-blur-sm border border-dashed border-neutral-300 rounded-3xl flex flex-col items-center justify-center p-16 text-center shadow-sm">
                                    <div className="w-20 h-20 bg-neutral-100 rounded-full flex items-center justify-center mb-6 shadow-inner">
                                        <Search className="w-10 h-10 text-neutral-400" />
                                    </div>
                                    <h3 className="text-xl font-bold tracking-tight text-neutral-900 mb-2">Nenhum documento encontrado</h3>
                                    <p className="text-base text-neutral-500 max-w-md mb-8 leading-relaxed">
                                        Não encontramos arquivos correspondentes à sua busca atual. A IA só indexa o que aparece aqui.
                                    </p>
                                    <button onClick={handleSync} className="px-6 py-2.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded-full transition-colors shadow-md">
                                        Forçar Sincronização Agora
                                    </button>
                                </div>
                            ) : viewMode === 'grid' ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5">
                                    {resources.data.map((resource) => (
                                        <div key={resource.id} className="bg-white/80 backdrop-blur-sm border border-neutral-200/80 rounded-2xl p-5 shadow-[0_2px_8px_-4px_rgba(0,0,0,0.05)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group flex flex-col relative">
                                            <div className="flex justify-between items-start mb-4">
                                                <div className="p-3 bg-neutral-50 rounded-xl border border-neutral-100 group-hover:bg-white group-hover:shadow-sm transition-all">
                                                    {getIconForType(resource.resource_type, resource.is_folder)}
                                                </div>
                                                {resource.is_shared && (
                                                    <span className="flex items-center gap-1 text-[10px] uppercase font-bold tracking-wider text-blue-600 bg-blue-50 px-2 py-1 rounded-md border border-blue-100">
                                                        <Users className="w-3 h-3" /> Shared
                                                    </span>
                                                )}
                                            </div>
                                            <h4 className="font-semibold text-neutral-900 truncate mb-1 text-base tracking-tight" title={resource.name}>
                                                {resource.name}
                                            </h4>
                                            <div className="text-xs text-neutral-500 flex flex-col gap-1.5 flex-1">
                                                <span className="truncate flex items-center gap-1.5"><div className="w-1.5 h-1.5 rounded-full bg-neutral-300"></div>{resource.owner_name || 'Desconhecido'}</span>
                                                <span className="flex items-center gap-1.5"><div className="w-1.5 h-1.5 rounded-full bg-neutral-300"></div>{resource.updated_by_provider_at ? new Date(resource.updated_by_provider_at).toLocaleDateString('pt-BR') : '-'}</span>
                                            </div>
                                            {resource.url && (
                                                <a 
                                                    href={resource.url} 
                                                    target="_blank" 
                                                    rel="noreferrer"
                                                    className="mt-5 flex items-center justify-center gap-2 w-full py-2.5 bg-neutral-50 hover:bg-neutral-900 hover:text-white rounded-xl text-sm text-neutral-700 font-semibold transition-all duration-300 opacity-0 group-hover:opacity-100 border border-transparent hover:shadow-md"
                                                >
                                                    Abrir <ExternalLink className="w-3.5 h-3.5" />
                                                </a>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="bg-white/80 backdrop-blur-md border border-neutral-200 rounded-2xl overflow-hidden shadow-sm">
                                    <table className="w-full text-left text-sm whitespace-nowrap">
                                        <thead className="bg-neutral-50/50 border-b border-neutral-200/80">
                                            <tr>
                                                <th className="px-5 py-4 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Nome do Arquivo</th>
                                                <th className="px-5 py-4 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Proprietário</th>
                                                <th className="px-5 py-4 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Modificado em</th>
                                                <th className="px-5 py-4 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Tamanho</th>
                                                <th className="px-5 py-4 font-semibold text-neutral-500 text-xs uppercase tracking-wider text-right">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-100">
                                            {resources.data.map((resource) => (
                                                <tr key={resource.id} className="hover:bg-neutral-50 transition-colors group">
                                                    <td className="px-5 py-4 flex items-center gap-4">
                                                        <div className="p-2 bg-white border border-neutral-100 rounded-lg shadow-sm">
                                                            {getIconForType(resource.resource_type, resource.is_folder)}
                                                        </div>
                                                        <span className="font-semibold tracking-tight text-neutral-900 truncate max-w-[200px] lg:max-w-[300px]">
                                                            {resource.name}
                                                        </span>
                                                        {resource.is_shared && (
                                                            <Users className="w-4 h-4 text-blue-500 flex-shrink-0" title="Compartilhado" />
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4 text-neutral-600 font-medium">{resource.owner_name || '-'}</td>
                                                    <td className="px-5 py-4 text-neutral-500">
                                                        {resource.updated_by_provider_at ? new Date(resource.updated_by_provider_at).toLocaleDateString('pt-BR') : '-'}
                                                    </td>
                                                    <td className="px-5 py-4 text-neutral-500">
                                                        {resource.is_folder ? '-' : formatBytes(resource.size)}
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        {resource.url && (
                                                            <a 
                                                                href={resource.url} 
                                                                target="_blank" 
                                                                rel="noreferrer"
                                                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-neutral-200 shadow-sm hover:bg-neutral-900 hover:text-white rounded-lg text-xs font-bold transition-all duration-300 opacity-0 group-hover:opacity-100 focus:opacity-100"
                                                            >
                                                                Visualizar <ExternalLink className="w-3.5 h-3.5" />
                                                            </a>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {/* Pagination */}
                            {resources.last_page > 1 && (
                                <div className="mt-8 flex justify-center pb-8">
                                    <div className="flex items-center gap-1.5 bg-white p-1.5 border border-neutral-200 rounded-xl shadow-sm">
                                        {resources.links.map((link, i) => (
                                            <button
                                                key={i}
                                                disabled={!link.url}
                                                onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                                className={`px-4 py-2 text-sm rounded-lg transition-all font-medium ${
                                                    link.active 
                                                        ? 'bg-neutral-900 text-white shadow-md' 
                                                        : link.url 
                                                            ? 'bg-transparent text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' 
                                                            : 'text-neutral-300 cursor-not-allowed hidden md:block'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
