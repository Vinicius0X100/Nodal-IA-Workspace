import axios from 'axios';
import { useState, useEffect, useCallback } from 'react';

export interface CommitProgress {
    processed_batches: number;
    total_batches: number | null;
}

export interface CommitStatusResponse {
    success: boolean;
    data: {
        artifact_uuid: string;
        commit_uuid?: string;
        status: 'draft' | 'committing' | 'committed' | 'failed';
        stage?: string;
        progress?: CommitProgress;
        resource_uuid?: string;
    };
    message?: string;
}

export interface UseArtifactCommitPollingProps {
    artifactUuid: string;
    initialStatus: string;
    onCommitted: (resourceUuid: string) => void;
    onFailed: (error: string) => void;
}

export function useArtifactCommitPolling({
    artifactUuid,
    initialStatus,
    onCommitted,
    onFailed,
}: UseArtifactCommitPollingProps) {
    const [status, setStatus] = useState<string>(initialStatus);
    const [stage, setStage] = useState<string | undefined>();
    const [progress, setProgress] = useState<CommitProgress | undefined>();
    const [isPolling, setIsPolling] = useState(initialStatus === 'committing');

    const startCommit = async () => {
        setIsPolling(true);
        setStatus('committing');
        setStage('preflight');
        
        try {
            const response = await axios.post(`/artifacts/${artifactUuid}/commit`, {}, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (response.data?.success && response.data?.data?.status === 'committing') {
                // Polling starts automatically because isPolling is true
                setStatus('committing');
            } else if (response.data?.success && response.data?.data?.status === 'committed') {
                setIsPolling(false);
                setStatus('committed');
                onCommitted(response.data.data.resource_uuid);
            } else {
                throw new Error(response.data?.message || 'Falha ao iniciar o commit.');
            }
        } catch (error: any) {
            setIsPolling(false);
            setStatus('failed');
            const msg = error.response?.data?.message || error.message || 'Falha ao iniciar o commit.';
            onFailed(msg);
        }
    };

    const pollStatus = useCallback(async (abortController: AbortController) => {
        try {
            const response = await axios.get(`/artifacts/${artifactUuid}/commit-status`, {
                signal: abortController.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            const result = response.data as CommitStatusResponse;

            if (result.success) {
                setStatus(result.data.status);
                setStage(result.data.stage);
                setProgress(result.data.progress);

                if (result.data.status === 'committed' && result.data.resource_uuid) {
                    setIsPolling(false);
                    onCommitted(result.data.resource_uuid);
                } else if (result.data.status === 'failed') {
                    setIsPolling(false);
                    onFailed(result.message || 'Ocorreu um erro ao salvar a planilha.');
                }
            } else {
                setIsPolling(false);
                setStatus('failed');
                onFailed(result.message || 'Falha ao verificar o status.');
            }
        } catch (error: any) {
            if (error.name === 'CanceledError' || error.message === 'canceled' || abortController.signal.aborted) {
                return; // ignored
            }
            console.error('[ARTIFACT_COMMIT_POLLING] Polling error:', error);
            // We don't stop polling on a simple network error immediately, we can let it try again
        }
    }, [artifactUuid, onCommitted, onFailed]);

    useEffect(() => {
        // Automatically start polling if the initial status is committing
        if (initialStatus === 'committing') {
            setIsPolling(true);
            setStatus('committing');
        }
    }, [initialStatus]);

    useEffect(() => {
        let timeoutId: NodeJS.Timeout;
        let abortController: AbortController | null = null;

        const runPoll = async () => {
            if (!isPolling) return;
            
            abortController = new AbortController();
            await pollStatus(abortController);
            
            if (isPolling && !abortController.signal.aborted) {
                timeoutId = setTimeout(runPoll, 2000);
            }
        };

        if (isPolling) {
            runPoll();
        }

        return () => {
            if (timeoutId) clearTimeout(timeoutId);
            if (abortController) abortController.abort();
        };
    }, [isPolling, pollStatus]);

    // Format stage for human readability
    let displayStage = 'Salvando...';
    if (stage === 'preflight') displayStage = 'Preparando...';
    if (stage === 'create_file') displayStage = 'Criando arquivo...';
    if (stage === 'prepare_structure') displayStage = 'Preparando abas...';
    if (stage === 'write_values') displayStage = 'Salvando dados...';
    if (stage === 'apply_formats') displayStage = 'Aplicando formatação...';
    if (stage === 'finalize') displayStage = 'Finalizando...';

    return {
        status,
        stage: displayStage,
        progress,
        isPolling,
        startCommit
    };
}
