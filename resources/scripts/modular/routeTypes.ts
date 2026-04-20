import type { ComponentType } from 'react';
import type { PaginatedResult } from '@/api/http';
import type { Server } from '@/api/server/getServer';

export interface RouteDefinition {
    path: string;
    name: string | undefined;
    component: ComponentType;
    exact?: boolean;
}

export interface ServerRouteDefinition extends RouteDefinition {
    permission: string | string[] | null;
}

export interface RouteRegistry {
    account: RouteDefinition[];
    server: ServerRouteDefinition[];
}

export interface ModuleRouteDefinition {
    path: string;
    name?: string | null;
    exact?: boolean;
}

export interface ModuleServerRouteDefinition extends ModuleRouteDefinition {
    permission?: string | string[] | null;
}

export interface ModuleFrontendRegistryModule {
    slug: string;
    has_frontend: boolean;
    entrypoint: string | null;
    zones: string[];
    routes?: {
        account?: ModuleRouteDefinition[];
        server?: ModuleServerRouteDefinition[];
    };
}

export interface ModularFrontendRegistryPayload {
    modules: ModuleFrontendRegistryModule[];
}

export type ModuleRouteComponentMap = Record<string, ComponentType>;

export interface ModuleDashboardComponentRegistry {
    serverList?: ComponentType<DashboardServerListProps>;
}

export interface ModuleRouteComponentRegistry {
    account?: ModuleRouteComponentMap;
    server?: ModuleRouteComponentMap;
    dashboard?: ModuleDashboardComponentRegistry;
}

export type ModuleRouteComponentRegistries = Record<string, ModuleRouteComponentRegistry>;

export interface DashboardExtensionRegistry {
    serverList?: ComponentType<DashboardServerListProps>;
}

export interface DashboardServerListProps {
    servers?: PaginatedResult<Server>;
    page: number;
    setPage: (page: number) => void;
    rootAdmin: boolean;
    showOnlyAdmin: boolean;
    ServerRowComponent: ComponentType<{ server: Server; className?: string }>;
}
