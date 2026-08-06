import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { 
    Search, RefreshCw, Folder, FileText, FileSpreadsheet, FileIcon, 
    Calendar, Image as ImageIcon, FileAudio, FileVideo, Files, Clock, 
    MoreHorizontal, ExternalLink, HardDrive, FileImage, LayoutGrid, List as ListIcon,
    Users
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

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('resources.index'), { ...filters, search: searchQuery }, { preserveState: true });
    };

    const handleFilterChange = (type?: string, is_shared?: boolean) => {
        const currentParams: any = { ...filters };
        if (type !== undefined) currentParams.type = type;
        if (is_shared !== undefined) currentParams.is_shared = is_shared;
        
        router.get(route('resources.index'), currentParams, { preserveState: true });
    };

    const handleSync = () => {
        setIsSyncing(true);
        router.post(route('resources.sync'), {}, {
            preserveState: true,
            onFinish: () => setIsSyncing(false),
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
            default: return <Files className="w-5 h-5 text-gray-500" />;
        }
    };

    return (
        <AppLayout>
            <Head title="Resources" />

            <div className="flex flex-col h-[calc(100vh-64px)] overflow-hidden">
                {/* Header Area */}
                <div className="bg-white border-b px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4 flex-shrink-0 z-10">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Resources</h1>
                        <p className="text-sm text-gray-500">
                            Central de arquivos e metadados de todas as integrações.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 w-full sm:w-auto">
                        <form onSubmit={handleSearch} className="relative flex-1 sm:w-64">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Buscar arquivos..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-9 pr-4 py-2 bg-gray-50 border-transparent rounded-lg focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm transition-all"
                            />
                        </form>

                        <button
                            onClick={handleSync}
                            disabled={isSyncing}
                            className="flex items-center justify-center gap-2 px-4 py-2 bg-white border rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors whitespace-nowrap shadow-sm disabled:opacity-50"
                        >
                            <RefreshCw className={`w-4 h-4 ${isSyncing ? 'animate-spin' : ''}`} />
                            <span className="hidden sm:inline">Sincronizar</span>
                        </button>
                    </div>
                </div>

                {/* Main Content Area - Scrollable */}
                <div className="flex-1 overflow-y-auto bg-gray-50/50 p-6">
                    
                    {/* Dashboard Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                        <div className="bg-white p-4 rounded-xl border shadow-sm flex flex-col justify-between">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-gray-500 text-xs font-medium uppercase">Total</span>
                                <HardDrive className="w-4 h-4 text-gray-400" />
                            </div>
                            <div className="text-2xl font-semibold text-gray-900">{dashboard.total}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl border shadow-sm flex flex-col justify-between">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-gray-500 text-xs font-medium uppercase">Pastas</span>
                                <Folder className="w-4 h-4 text-blue-500" />
                            </div>
                            <div className="text-2xl font-semibold text-gray-900">{dashboard.folders}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl border shadow-sm flex flex-col justify-between">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-gray-500 text-xs font-medium uppercase">Docs</span>
                                <FileText className="w-4 h-4 text-blue-600" />
                            </div>
                            <div className="text-2xl font-semibold text-gray-900">{dashboard.documents}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl border shadow-sm flex flex-col justify-between">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-gray-500 text-xs font-medium uppercase">Planilhas</span>
                                <FileSpreadsheet className="w-4 h-4 text-green-600" />
                            </div>
                            <div className="text-2xl font-semibold text-gray-900">{dashboard.spreadsheets}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl border shadow-sm flex flex-col justify-between">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-gray-500 text-xs font-medium uppercase">PDFs</span>
                                <FileIcon className="w-4 h-4 text-red-500" />
                            </div>
                            <div className="text-2xl font-semibold text-gray-900">{dashboard.pdfs}</div>
                        </div>
                        <div className="bg-white p-4 rounded-xl border shadow-sm flex flex-col justify-between">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-gray-500 text-xs font-medium uppercase">Última Sync</span>
                                <Clock className="w-4 h-4 text-orange-500" />
                            </div>
                            <div className="text-sm font-medium text-gray-900 mt-2">
                                {dashboard.last_sync ? formatDistanceToNow(new Date(dashboard.last_sync), { addSuffix: true, locale: ptBR }) : 'Nunca'}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col lg:flex-row gap-8">
                        {/* Sidebar Filters */}
                        <div className="w-full lg:w-64 flex-shrink-0">
                            <div className="bg-white border rounded-xl p-4 shadow-sm sticky top-4">
                                <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 px-2">Filtros</h3>
                                <nav className="space-y-1">
                                    <button 
                                        onClick={() => handleFilterChange('')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${!filters.type && !filters.is_shared ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <Files className="w-4 h-4" /> Todos os Arquivos
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('folder')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${filters.type === 'folder' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <Folder className="w-4 h-4" /> Pastas
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('document')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${filters.type === 'document' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <FileText className="w-4 h-4" /> Documentos
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('spreadsheet')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${filters.type === 'spreadsheet' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <FileSpreadsheet className="w-4 h-4" /> Planilhas
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('presentation')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${filters.type === 'presentation' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <FileIcon className="w-4 h-4" /> Apresentações
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('pdf')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${filters.type === 'pdf' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <FileIcon className="w-4 h-4 text-red-400" /> PDFs
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange('image')}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${filters.type === 'image' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <FileImage className="w-4 h-4" /> Imagens
                                    </button>
                                    <button 
                                        onClick={() => handleFilterChange(undefined, true)}
                                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors mt-4 border-t pt-4 ${filters.is_shared ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'}`}
                                    >
                                        <Users className="w-4 h-4" /> Compartilhados comigo
                                    </button>
                                </nav>
                            </div>
                        </div>

                        {/* Explorer List/Grid */}
                        <div className="flex-1">
                            <div className="flex items-center justify-between mb-4">
                                <div className="text-sm text-gray-500 font-medium">
                                    Mostrando {resources.data.length} de {resources.total} resultados
                                </div>
                                <div className="flex items-center bg-white border rounded-lg p-1 shadow-sm">
                                    <button 
                                        onClick={() => setViewMode('grid')}
                                        className={`p-1.5 rounded-md ${viewMode === 'grid' ? 'bg-gray-100 text-gray-900 shadow-sm' : 'text-gray-400 hover:text-gray-600'}`}
                                    >
                                        <LayoutGrid className="w-4 h-4" />
                                    </button>
                                    <button 
                                        onClick={() => setViewMode('list')}
                                        className={`p-1.5 rounded-md ${viewMode === 'list' ? 'bg-gray-100 text-gray-900 shadow-sm' : 'text-gray-400 hover:text-gray-600'}`}
                                    >
                                        <ListIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {resources.data.length === 0 ? (
                                <div className="bg-white border border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center p-12 text-center">
                                    <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <Files className="w-8 h-8 text-gray-400" />
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-1">Nenhum recurso encontrado</h3>
                                    <p className="text-sm text-gray-500 max-w-sm mb-6">
                                        Não encontramos arquivos correspondentes aos filtros atuais. Tente sincronizar novamente ou alterar a busca.
                                    </p>
                                    <button onClick={handleSync} className="text-blue-600 font-medium text-sm hover:underline">
                                        Sincronizar agora
                                    </button>
                                </div>
                            ) : viewMode === 'grid' ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                                    {resources.data.map((resource) => (
                                        <div key={resource.id} className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow group flex flex-col relative">
                                            <div className="flex justify-between items-start mb-3">
                                                <div className="p-2 bg-gray-50 rounded-lg">
                                                    {getIconForType(resource.resource_type, resource.is_folder)}
                                                </div>
                                                <button className="text-gray-400 hover:text-gray-700 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <MoreHorizontal className="w-5 h-5" />
                                                </button>
                                            </div>
                                            <h4 className="font-medium text-gray-900 truncate mb-1" title={resource.name}>
                                                {resource.name}
                                            </h4>
                                            <div className="text-xs text-gray-500 flex flex-col gap-1 flex-1">
                                                <span className="truncate">{resource.owner_name || 'Desconhecido'}</span>
                                                <span>Modificado: {resource.updated_by_provider_at ? new Date(resource.updated_by_provider_at).toLocaleDateString('pt-BR') : '-'}</span>
                                            </div>
                                            {resource.url && (
                                                <a 
                                                    href={resource.url} 
                                                    target="_blank" 
                                                    rel="noreferrer"
                                                    className="mt-4 flex items-center justify-center gap-2 w-full py-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-sm text-gray-700 font-medium transition-colors"
                                                >
                                                    Abrir <ExternalLink className="w-3 h-3" />
                                                </a>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="bg-white border rounded-xl overflow-hidden shadow-sm">
                                    <table className="w-full text-left text-sm whitespace-nowrap">
                                        <thead className="bg-gray-50 border-b">
                                            <tr>
                                                <th className="px-4 py-3 font-medium text-gray-500">Nome</th>
                                                <th className="px-4 py-3 font-medium text-gray-500">Owner</th>
                                                <th className="px-4 py-3 font-medium text-gray-500">Modificado em</th>
                                                <th className="px-4 py-3 font-medium text-gray-500">Tamanho</th>
                                                <th className="px-4 py-3 font-medium text-gray-500 text-right">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {resources.data.map((resource) => (
                                                <tr key={resource.id} className="hover:bg-gray-50/50 transition-colors group">
                                                    <td className="px-4 py-3 flex items-center gap-3">
                                                        {getIconForType(resource.resource_type, resource.is_folder)}
                                                        <span className="font-medium text-gray-900 truncate max-w-[200px] lg:max-w-[300px]">
                                                            {resource.name}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-500">{resource.owner_name || '-'}</td>
                                                    <td className="px-4 py-3 text-gray-500">
                                                        {resource.updated_by_provider_at ? new Date(resource.updated_by_provider_at).toLocaleDateString('pt-BR') : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-500">
                                                        {resource.is_folder ? '-' : formatBytes(resource.size)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {resource.url && (
                                                            <a 
                                                                href={resource.url} 
                                                                target="_blank" 
                                                                rel="noreferrer"
                                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border shadow-sm hover:bg-gray-50 rounded-md text-xs font-medium text-gray-700 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                                                            >
                                                                Abrir <ExternalLink className="w-3 h-3" />
                                                            </a>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {/* Pagination (Simplified visually) */}
                            {resources.last_page > 1 && (
                                <div className="mt-6 flex justify-center">
                                    <div className="flex items-center gap-1">
                                        {resources.links.map((link, i) => (
                                            <button
                                                key={i}
                                                disabled={!link.url}
                                                onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                                className={`px-3 py-1 text-sm rounded-md transition-colors ${
                                                    link.active 
                                                        ? 'bg-blue-600 text-white font-medium shadow-sm' 
                                                        : link.url 
                                                            ? 'bg-white border text-gray-600 hover:bg-gray-50' 
                                                            : 'text-gray-400 cursor-not-allowed hidden md:block'
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
