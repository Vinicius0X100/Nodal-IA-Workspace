<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Services\ConversationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
        private \App\Domain\AI\Services\MessageService $messageService,
        private \App\Domain\AI\Contracts\AIProviderInterface $aiProvider
    ) {}

    /**
     * Exibe a tela principal do AI Assistant (sem conversa selecionada).
     */
    public function index(Request $request): Response
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        $groups = $this->conversationService->listGrouped($organizationId, $userId);

        return Inertia::render('Assistant/Index', [
            'conversation' => null,
            'messages' => [],
            'groups' => $groups,
        ]);
    }

    /**
     * Cria uma nova conversa e redireciona para ela.
     */
    public function store(Request $request): RedirectResponse
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        $maxFiles = config('nodal.max_chat_attachments', 5);
        $maxSizeKilobytes = (int) config('nodal.max_upload_size_mb', 50) * 1024;

        $request->validate([
            'message' => 'nullable|string|max:32000',
            'attachments' => ['nullable', 'array', 'max:' . $maxFiles],
            'attachments.*' => ['file', 'max:' . $maxSizeKilobytes],
        ]);

        // Generate title if initial message is provided
        $initialMessage = $request->input('message');
        $title = $initialMessage ? (mb_strlen($initialMessage) > 30 ? mb_substr($initialMessage, 0, 30) . '...' : $initialMessage) : 'Nova Conversa';

        $conversation = $this->conversationService->create($organizationId, $userId, $title);

        if ($initialMessage || $request->hasFile('attachments')) {
            $attachments = $request->file('attachments', []);
            $userMessage = $this->messageService->addUserMessage($conversation, $initialMessage ?? '', $attachments);
            
            // Disparar AI Gateway
            if ($this->aiProvider->isAvailable()) {
                $result = $this->aiProvider->chat($conversation, $userMessage);
                
                $validArtifacts = $this->validateAndNormalizeArtifacts($result->artifacts, $organizationId, $request->user());
                $metadata = [];
                if (!empty($validArtifacts)) {
                    $metadata['artifacts'] = $validArtifacts;
                }

                $this->messageService->addAssistantMessage($conversation, $result->content, $metadata);
            } else {
                $this->messageService->addAssistantMessage($conversation, "O Cérebro da Inteligência Artificial não está configurado ou disponível no momento.");
            }
        }

        return redirect()->route('assistant.show', $conversation->uuid);
    }

    private function validateAndNormalizeArtifacts(array $artifacts, int $organizationId, \App\Domain\Identity\Models\User $user): array
    {
        $valid = [];
        $authService = app(\App\Domain\Permissions\Services\AuthorizationService::class);
        $organization = \App\Domain\Organizations\Models\Organization::find($organizationId);

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) continue;

            $uuid = $artifact['resource_uuid'] ?? null;
            if (!$uuid || !\Illuminate\Support\Str::isUuid($uuid)) {
                continue;
            }

            // Localiza IntegrationResource
            $resource = \App\Domain\Resources\Models\IntegrationResource::where('uuid', $uuid)
                ->whereHas('integration', function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId);
                })
                ->first();

            if (!$resource) {
                continue;
            }

            // Verifica permissão (opcional dependendo de como canAccessResource funciona)
            // if (!$authService->canAccessResource($user, $organization, $resource)) continue;
            // The user rule says: "garantir que o usuário/contexto atual pode acessar o Resource"
            if (!$authService->canAccessResource($user, $organization, $resource)) {
                continue;
            }

            // Normaliza title e type
            $valid[] = [
                'type' => $resource->resource_type instanceof \App\Domain\Resources\Enums\ResourceType ? $resource->resource_type->value : $resource->resource_type,
                'resource_uuid' => $resource->uuid,
                'title' => $resource->name,
            ];
        }

        return $valid;
    }

    /**
     * Exibe uma conversa específica com todas as suas mensagens.
     */
    public function show(Request $request, string $uuid): Response
    {
        $organizationId = session('active_organization_id');
        $userId = $request->user()->id;

        $conversation = $this->conversationService->findOrFail($organizationId, $uuid);
        $groups = $this->conversationService->listGrouped($organizationId, $userId);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'uuid' => $m->uuid,
                'role' => $m->role->value,
                'content' => $m->content,
                'attachments' => $m->metadata_json['attachments'] ?? [],
                'artifacts' => $m->metadata_json['artifacts'] ?? [],
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('Assistant/Index', [
            'conversation' => [
                'uuid' => $conversation->uuid,
                'title' => $conversation->title,
                'status' => $conversation->status->value,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'messages' => $messages,
            'groups' => $groups,
        ]);
    }

    /**
     * Atualiza uma conversa (Renomear ou Fixar).
     */
    public function update(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'is_pinned' => 'sometimes|required|boolean',
        ]);

        $organizationId = session('active_organization_id');
        $conversation = $this->conversationService->findOrFail($organizationId, $uuid);

        if ($request->has('title')) {
            $conversation->title = $request->input('title');
        }

        if ($request->has('is_pinned')) {
            $conversation->is_pinned = $request->input('is_pinned');
        }

        $conversation->save();

        return back();
    }

    /**
     * Exclui uma conversa.
     */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $organizationId = session('active_organization_id');

        $this->conversationService->delete($organizationId, $uuid);

        return redirect()->route('assistant.index');
    }
}
