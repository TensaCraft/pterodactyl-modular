import discoveredModuleRouteComponents from './discoverModuleRouteComponents';
import type {
    DashboardExtensionRegistry,
    ModuleFrontendRegistryModule,
    ModuleRouteComponentRegistries,
    ModularFrontendRegistryPayload,
} from './routeTypes';

const generatedRoutes = discoveredModuleRouteComponents as ModuleRouteComponentRegistries;
const isBrowser = typeof window !== 'undefined';

const getFrontendModules = (frontendRegistry?: ModularFrontendRegistryPayload): ModuleFrontendRegistryModule[] => {
    if (frontendRegistry?.modules) {
        return frontendRegistry.modules.filter((module) => module.has_frontend);
    }

    if (!isBrowser || !window.ModularFrontendRegistry?.modules) {
        return [];
    }

    return window.ModularFrontendRegistry.modules.filter((module) => module.has_frontend);
};

export const buildDashboardExtensionRegistry = (
    frontendRegistry?: ModularFrontendRegistryPayload,
    moduleRouteComponents: ModuleRouteComponentRegistries = generatedRoutes
): DashboardExtensionRegistry => {
    const modules = getFrontendModules(frontendRegistry);
    const registry: DashboardExtensionRegistry = {};

    modules.forEach((module) => {
        if (!module.zones.includes('dashboard.server-list')) {
            return;
        }

        if (registry.serverList) {
            return;
        }

        registry.serverList = moduleRouteComponents[module.slug]?.dashboard?.serverList;
    });

    return registry;
};

export const dashboardRegistry = buildDashboardExtensionRegistry();

export default dashboardRegistry;
