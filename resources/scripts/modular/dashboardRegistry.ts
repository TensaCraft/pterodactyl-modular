import discoveredModuleRouteComponents from './discoverModuleRouteComponents';
import { getFrontendModules } from './frontendRegistry';
import type {
    DashboardExtensionRegistry,
    ModuleRouteComponentRegistries,
    ModularFrontendRegistryPayload,
} from './routeTypes';

const generatedRoutes = discoveredModuleRouteComponents as ModuleRouteComponentRegistries;

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
