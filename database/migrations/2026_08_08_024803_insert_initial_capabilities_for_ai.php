<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'slug')) {
                $table->dropUnique(['module', 'action']);
                $table->dropColumn(['module', 'action']);
                
                $table->string('slug')->unique()->after('id');
                $table->string('name')->after('slug');
                $table->string('group')->after('name')->nullable();
                // We keep description if it exists, otherwise add it
                if (!Schema::hasColumn('permissions', 'description')) {
                    $table->string('description')->nullable();
                }
            }
        });

        // Seed initial capabilities
        $permissions = [
            ['name' => 'Visualizar Organização', 'slug' => 'organization.read', 'group' => 'Geral', 'description' => 'Permite visualizar dados básicos da organização.'],
            ['name' => 'Visualizar Usuários', 'slug' => 'directory.users.read', 'group' => 'Diretório', 'description' => 'Permite listar e visualizar perfis de usuários.'],
            ['name' => 'Visualizar Grupos', 'slug' => 'directory.groups.read', 'group' => 'Diretório', 'description' => 'Permite visualizar grupos e organograma.'],
            ['name' => 'Pesquisar Recursos', 'slug' => 'resources.search', 'group' => 'Arquivos e Recursos', 'description' => 'Permite pesquisar arquivos e pastas no banco de dados da IA.'],
            ['name' => 'Ler Recursos', 'slug' => 'resources.read', 'group' => 'Arquivos e Recursos', 'description' => 'Permite acessar e baixar o conteúdo e metadados completos de recursos.'],
            ['name' => 'Visualizar Integrações', 'slug' => 'integrations.read', 'group' => 'Integrações', 'description' => 'Permite ver o status das integrações conectadas.'],
            ['name' => 'Acesso às Ferramentas (IA)', 'slug' => 'tools.read', 'group' => 'Inteligência Artificial', 'description' => 'Permite listar as ferramentas ativas de IA.'],
        ];

        foreach ($permissions as $perm) {
            \App\Domain\Permissions\Models\Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }
    }

    public function down(): void
    {
        $slugs = ['organization.read', 'directory.users.read', 'directory.groups.read', 'resources.search', 'resources.read', 'integrations.read', 'tools.read'];
        \App\Domain\Permissions\Models\Permission::whereIn('slug', $slugs)->delete();
    }
};
