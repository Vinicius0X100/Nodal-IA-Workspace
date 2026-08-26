import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Button } from '@/Components/ui/button';
import { Activity, RefreshCcw, BarChart3, Clock, CheckCircle2, AlertCircle } from 'lucide-react';

export default function MetaPerformanceTab({ adAccounts = [] }: { adAccounts: any[] }) {
    const [selectedAdAccount, setSelectedAdAccount] = useState<string | null>(adAccounts.length > 0 ? adAccounts[0].uuid : null);
    const [period, setPeriod] = useState<string>('last_7d');
    
    const [isLoading, setIsLoading] = useState(false);
    const [data, setData] = useState<any[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    
    const [pollingReportUuid, setPollingReportUuid] = useState<string | null>(null);
    const [pollingProgress, setPollingProgress] = useState<number>(0);
    const [pollingStatus, setPollingStatus] = useState<string | null>(null);

    const fetchPerformance = async () => {
        if (!selectedAdAccount) return;
        
        setIsLoading(true);
        setError(null);
        setData(null);
        setPollingReportUuid(null);
        setPollingStatus(null);
        
        try {
            const res = await axios.post('/integrations/meta/insights', {
                resource_uuid: selectedAdAccount,
                level: 'campaign', // Por padrão vamos listar as campanhas
                period: period
            });
            
            if (res.data.async) {
                // Relatório pesado foi pra fila
                setPollingReportUuid(res.data.data.report_uuid);
                setPollingStatus(res.data.data.status);
            } else {
                // Síncrono (com cache)
                setData(res.data.data);
                setIsLoading(false);
            }
        } catch (err: any) {
            setError(err.response?.data?.error || 'Erro ao carregar performance.');
            setIsLoading(false);
        }
    };

    useEffect(() => {
        if (!pollingReportUuid) return;
        
        const interval = setInterval(async () => {
            try {
                const res = await axios.get(`/api/reports/${pollingReportUuid}`);
                const r = res.data.data;
                
                setPollingStatus(r.status);
                setPollingProgress(r.progress);
                
                if (r.status === 'completed' || r.status === 'partial') {
                    setData(r.result);
                    setIsLoading(false);
                    setPollingReportUuid(null);
                    clearInterval(interval);
                } else if (r.status === 'failed') {
                    setError(r.error_message || 'Erro ao gerar relatório.');
                    setIsLoading(false);
                    setPollingReportUuid(null);
                    clearInterval(interval);
                }
            } catch (e) {
                // Falha silenciosa no polling, tenta na proxima
            }
        }, 3000);
        
        return () => clearInterval(interval);
    }, [pollingReportUuid]);

    useEffect(() => {
        fetchPerformance();
    }, [selectedAdAccount, period]);

    return (
        <div className="bg-white border border-neutral-200 rounded-2xl p-8">
            <div className="flex items-center justify-between mb-8 pb-6 border-b border-neutral-100">
                <div>
                    <h3 className="text-lg font-bold text-neutral-900 mb-1 flex items-center gap-2">
                        <BarChart3 className="w-5 h-5 text-primary-600" /> Performance
                    </h3>
                    <p className="text-neutral-500 text-sm">Visualize as métricas consolidadas das suas Campanhas.</p>
                </div>
                <div className="flex items-center gap-4">
                    <select 
                        value={selectedAdAccount || ''}
                        onChange={(e) => setSelectedAdAccount(e.target.value)}
                        className="text-sm border-neutral-200 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="" disabled>Selecione uma Conta</option>
                        {adAccounts.map(acc => (
                            <option key={acc.uuid} value={acc.uuid}>{acc.name}</option>
                        ))}
                    </select>

                    <select 
                        value={period}
                        onChange={(e) => setPeriod(e.target.value)}
                        className="text-sm border-neutral-200 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="today">Hoje</option>
                        <option value="yesterday">Ontem</option>
                        <option value="last_7d">Últimos 7 dias</option>
                        <option value="last_14d">Últimos 14 dias</option>
                        <option value="last_30d">Últimos 30 dias</option>
                    </select>
                    
                    <Button variant="outline" onClick={fetchPerformance} disabled={isLoading || !!pollingReportUuid}>
                        <RefreshCcw className={`w-4 h-4 mr-2 ${isLoading && !pollingReportUuid ? 'animate-spin' : ''}`} />
                        Atualizar
                    </Button>
                </div>
            </div>

            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg flex items-center gap-3 mb-6">
                    <AlertCircle className="w-5 h-5" />
                    <span>{error}</span>
                </div>
            )}

            {pollingReportUuid && (
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center mb-6">
                    <Clock className="w-8 h-8 text-blue-500 mx-auto mb-4 animate-pulse" />
                    <h4 className="font-bold text-blue-900 mb-2">Relatório Longo em Processamento</h4>
                    <p className="text-blue-700 text-sm mb-4">Sua requisição foi para a fila de processamento devido ao volume de dados.</p>
                    <div className="w-full bg-blue-200 rounded-full h-2.5 max-w-md mx-auto mb-2">
                        <div className="bg-blue-600 h-2.5 rounded-full transition-all duration-500" style={{ width: `${pollingProgress}%` }}></div>
                    </div>
                    <span className="text-xs font-bold text-blue-800 uppercase tracking-wider">Status: {pollingStatus}</span>
                </div>
            )}

            {!isLoading && !pollingReportUuid && data && data.length > 0 && (
                <div className="border border-neutral-200 rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-neutral-200">
                        <thead className="bg-neutral-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Campanha</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Investimento</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Leads</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">CPL</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Cliques</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">CTR</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-neutral-200">
                            {data.map((row: any) => {
                                const m = row.metrics;
                                const formatCurrency = (val: number | null) => val !== null ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: m.currency }).format(val) : '-';
                                
                                return (
                                    <tr key={row.resource_uuid} className="hover:bg-neutral-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900">
                                            {/* Idealmente a API deveria trazer o nome, mas como lemos insights do ID, precisamos cruzar com a tabela local ou a Meta nos dá campaign_name */}
                                            {row.resource_uuid}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-neutral-700">
                                            {formatCurrency(m.spend)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-neutral-700">
                                            {m.leads}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-neutral-700">
                                            {formatCurrency(m.cpl)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-neutral-700">
                                            {m.clicks}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-neutral-700">
                                            {m.ctr ? `${m.ctr}%` : '-'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            
            {!isLoading && !pollingReportUuid && data && data.length === 0 && (
                <div className="p-12 text-center border border-neutral-100 bg-neutral-50 rounded-xl">
                    <p className="text-neutral-500 font-medium">Nenhum dado de performance encontrado para o período.</p>
                </div>
            )}
        </div>
    );
}
