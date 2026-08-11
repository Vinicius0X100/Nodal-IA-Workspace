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
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->index('role_id'); // Ensure foreign key has an index before dropping the composite unique index
            $table->dropUnique(['role_id', 'permission_id']);
            $table->string('scope')->default('organization')->after('permission_id');
        });

        // Aplicar least privilege para permissões de recursos e agenda
        $selfSlugs = [
            'resources.search',
            'resources.read',
            'calendar.events.read',
            'calendar.freebusy.read'
        ];

        $permissions = \App\Domain\Permissions\Models\Permission::whereIn('slug', $selfSlugs)->get();
        foreach ($permissions as $permission) {
            \Illuminate\Support\Facades\DB::table('role_permissions')
                ->where('permission_id', $permission->id)
                ->update(['scope' => 'self']);
        }

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->unique(['role_id', 'permission_id', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropUnique(['role_id', 'permission_id', 'scope']);
            $table->dropColumn('scope');
            
            // Note: Data loss of duplicate role_id/permission_id may prevent adding the old unique constraint back directly 
            // if a role was granted the same permission with multiple scopes. 
            // We'll assume down() drops the duplicates for rollback safety.
            $duplicates = \Illuminate\Support\Facades\DB::select("
                SELECT MIN(id) as keep_id, role_id, permission_id 
                FROM role_permissions 
                GROUP BY role_id, permission_id 
                HAVING COUNT(*) > 1
            ");
            
            foreach ($duplicates as $dup) {
                \Illuminate\Support\Facades\DB::table('role_permissions')
                    ->where('role_id', $dup->role_id)
                    ->where('permission_id', $dup->permission_id)
                    ->where('id', '!=', $dup->keep_id)
                    ->delete();
            }

            $table->unique(['role_id', 'permission_id']);
            $table->dropIndex(['role_id']); // Remove the temporary index we added in up()
        });
    }
};
