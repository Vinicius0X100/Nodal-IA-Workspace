/**
 * Nodal — TypeScript Type Definitions
 * ====================================
 * Tipos globais da aplicação para uso com Inertia.js
 */

declare module '@inertiajs/react' {
    interface PageProps {
        auth: {
            user: App.Data.UserData | null;
        };
        flash: {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
        };
        organization?: App.Data.OrganizationData;
    }
}

declare namespace App.Data {
    interface UserData {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        phone: string | null;
        status: 'active' | 'inactive' | 'suspended';
        email_verified_at: string | null;
        created_at: string;
        updated_at: string;
    }

    interface OrganizationData {
        id: number;
        name: string;
        slug: string;
        logo: string | null;
        created_at: string;
        updated_at: string;
    }

    interface RoleData {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        is_system: boolean;
        permissions: PermissionData[];
    }

    interface PermissionData {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        group: string;
    }

    interface IntegrationData {
        id: number;
        provider: string;
        status: 'not_connected' | 'connected' | 'error' | 'coming_soon';
        connected_at: string | null;
    }

    interface AuditLogData {
        id: number;
        user: UserData | null;
        action: string;
        entity_type: string;
        entity_id: number | null;
        metadata: Record<string, unknown>;
        ip_address: string | null;
        created_at: string;
    }

    interface SettingData {
        id: number;
        key: string;
        value: unknown;
        type: string;
    }
}

export {};
