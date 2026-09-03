import type { Auth } from './auth';

export type EmpresaOption = {
    id: number;
    ruc: string;
    razon_social: string;
};

export type EmpresaRol = 'super_admin' | 'owner' | 'simple' | 'cajero' | null;

/** Contexto de la empresa activa + permisos del usuario (para gating de UI). */
export type EmpresaShared = {
    rol: EmpresaRol;
    esSuperAdmin: boolean;
    disponibles: EmpresaOption[];
    can: string[];
};

export type TenantShared = {
    id: number;
    ruc: string;
    razon_social: string;
    environment: string;
    sol_configurado: boolean;
} | null;

export type FlashShared = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
    nuevoCajero?: { email: string; password: string } | null;
    [key: string]: unknown;
};

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    tenant: TenantShared;
    empresa: EmpresaShared;
    flash?: FlashShared;
    [key: string]: unknown;
};
