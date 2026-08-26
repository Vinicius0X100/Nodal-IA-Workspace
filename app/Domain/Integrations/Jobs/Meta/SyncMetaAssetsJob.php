<?php

namespace App\Domain\Integrations\Jobs\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Services\Meta\MetaAdAccountsService;
use App\Domain\Integrations\Services\Meta\MetaAdsService;
use App\Domain\Integrations\Services\Meta\MetaAdSetsService;
use App\Domain\Integrations\Services\Meta\MetaCampaignsService;
use App\Domain\Integrations\Services\Meta\MetaPagesService;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncMetaAssetsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora máximo
    public $uniqueFor = 3600;

    public function __construct(
        public Integration $integration,
        public ?string $userId = null
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->integration->id;
    }

    public function handle(
        MetaAdAccountsService $adAccountsService,
        MetaPagesService $pagesService,
        MetaCampaignsService $campaignsService,
        MetaAdSetsService $adSetsService,
        MetaAdsService $adsService
    ): void {
        $this->log('meta_sync_job_started', 'Iniciando sincronização assíncrona de ativos Meta.');
        
        try {
            $hasErrors = false;
            $adAccountExternalIds = [];
            $adAccountsSuccess = false;

            // 1. Sync Ad Accounts
            try {
                $adAccountsCount = $adAccountsService->syncAdAccounts($this->integration);
                $this->log('meta_ad_accounts_sync_completed', "{$adAccountsCount} conta(s) de anúncio sincronizada(s).", 'success');
                
                $adAccountExternalIds = IntegrationResource::where('integration_id', $this->integration->id)
                    ->where('resource_type', 'ad_account')
                    ->pluck('external_id')
                    ->toArray();
                $adAccountsSuccess = true;
            } catch (\Exception $e) {
                $hasErrors = true;
                $this->log('meta_ad_accounts_sync_failed', 'Falha ao sincronizar contas de anúncio: ' . $e->getMessage(), 'error');
            }

            // 2. Sync Pages e Instagram (Independente)
            try {
                $pagesCounts = $pagesService->syncPagesAndInstagram($this->integration);
                $this->log('meta_pages_sync_completed', "{$pagesCounts['pages']} página(s) e {$pagesCounts['instagram']} conta(s) IG sincronizada(s).", 'success');
            } catch (\Exception $e) {
                $hasErrors = true;
                $this->log('meta_pages_sync_failed', 'Falha ao sincronizar páginas/Instagram: ' . $e->getMessage(), 'error');
            }

            if (empty($adAccountExternalIds) && !$hasErrors) {
                $this->log('meta_sync_job_completed', 'Sincronização concluída. Nenhuma conta de anúncios encontrada.', 'success');
                return;
            }

            // 3. Sync Hierárquico (Campaigns -> AdSets -> Ads)
            if ($adAccountsSuccess && !empty($adAccountExternalIds)) {
                $campaignsSuccess = false;
                try {
                    $campaignsCount = $campaignsService->syncCampaigns($this->integration, $adAccountExternalIds);
                    $this->log('meta_campaigns_sync_completed', "{$campaignsCount} campanha(s) sincronizada(s).", 'success');
                    $campaignsSuccess = true;
                } catch (\Exception $e) {
                    $hasErrors = true;
                    $this->log('meta_campaigns_sync_failed', 'Falha ao sincronizar campanhas: ' . $e->getMessage(), 'error');
                }

                if ($campaignsSuccess) {
                    $adSetsSuccess = false;
                    try {
                        $adSetsCount = $adSetsService->syncAdSets($this->integration, $adAccountExternalIds);
                        $this->log('meta_adsets_sync_completed', "{$adSetsCount} conjunto(s) de anúncios sincronizado(s).", 'success');
                        $adSetsSuccess = true;
                    } catch (\Exception $e) {
                        $hasErrors = true;
                        $this->log('meta_adsets_sync_failed', 'Falha ao sincronizar conjuntos de anúncios: ' . $e->getMessage(), 'error');
                    }

                    if ($adSetsSuccess) {
                        try {
                            $adsCount = $adsService->syncAds($this->integration, $adAccountExternalIds);
                            $this->log('meta_ads_sync_completed', "{$adsCount} anúncio(s) sincronizado(s).", 'success');
                        } catch (\Exception $e) {
                            $hasErrors = true;
                            $this->log('meta_ads_sync_failed', 'Falha ao sincronizar anúncios: ' . $e->getMessage(), 'error');
                        }
                    } else {
                        $this->log('meta_ads_sync_skipped', 'Sincronização de anúncios ignorada porque os conjuntos de anúncios falharam.', 'warning');
                    }
                } else {
                    $this->log('meta_adsets_sync_skipped', 'Sincronização de conjuntos de anúncios e anúncios ignorada porque as campanhas falharam.', 'warning');
                }
            } elseif (!$adAccountsSuccess) {
                 $this->log('meta_campaigns_sync_skipped', 'Sincronização de Campanhas ignorada porque Ad Accounts falhou.', 'warning');
            }

            if ($hasErrors) {
                $this->log('meta_sync_job_finished_with_errors', 'Sincronização concluída com falhas parciais.', 'warning');
            } else {
                $this->log('meta_sync_job_completed', 'Sincronização concluída com sucesso.', 'success');
            }
        } finally {
            Cache::forget("meta_sync_{$this->integration->id}");
        }
    }

    public function failed(\Throwable $exception)
    {
        Cache::forget("meta_sync_{$this->integration->id}");
    }

    private function log(string $event, string $message, string $status = 'info'): void
    {
        IntegrationLog::create([
            'integration_id' => $this->integration->id,
            'user_id'        => $this->userId,
            'event'          => $event,
            'status'         => $status,
            'message'        => $message,
        ]);
    }
}
