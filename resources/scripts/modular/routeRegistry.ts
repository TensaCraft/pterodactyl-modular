import coreRoutes from '@/routers/routes';
import discoveredModuleRouteComponents from './discoverModuleRouteComponents';
import mergeRouteDefinitions from './mergeRouteDefinitions';
import { ScreenPlaceholder } from './ScreenPlaceholder';
import type {
    ModuleFrontendRegistryModule,
    ModuleRouteComponentRegistries,
    ModularFrontendRegistryPayload,
    RouteDefinition,
    RouteRegistry,
    ServerRouteDefinition,
} from './routeTypes';

const generatedRoutes = discoveredModuleRouteComponents as ModuleRouteComponentRegistries;

const accountPrefixes = ['/account'];
const serverPrefixes = ['/server/:id', '/server/{server}', '/servers/{server}', '/servers/:server'];

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

const normalizeAccountPath = (path: string) => {
    for (const prefix of accountPrefixes) {
        if (path === prefix) {
            return '/';
        }

        if (path.startsWith(`${prefix}/`)) {
            return path.slice(prefix.length) || '/';
        }
    }

    return path;
};

const normalizeServerPath = (path: string) => {
    for (const prefix of serverPrefixes) {
        if (path === prefix) {
            return '/';
        }

        if (path.startsWith(`${prefix}/`)) {
            return path.slice(prefix.length) || '/';
        }
    }

    return path;
};

const resolveComponent = (
    slug: string,
    routeType: 'account' | 'server',
    rawPath: string,
    localPath: string,
    modules: ModuleRouteComponentRegistries
) => {
    const componentRegistry = modules[slug]?.[routeType];
    return componentRegistry?.[rawPath] ?? componentRegistry?.[localPath] ?? ScreenPlaceholder;
};

const buildAccountRoutes = (
    modules: ModuleFrontendRegistryModule[],
    moduleRouteComponents: ModuleRouteComponentRegistries
): RouteDefinition[] => {
    const routes: RouteDefinition[] = [];

    modules.forEach((module) => {
        module.routes?.account?.forEach((route) => {
            const path = normalizeAccountPath(route.path);

            routes.push({
                path,
                name: route.name ?? undefined,
                exact: route.exact,
                component: resolveComponent(module.slug, 'account', route.path, path, moduleRouteComponents),
            });
        });
    });

    return routes;
};

const buildServerRoutes = (
    modules: ModuleFrontendRegistryModule[],
    moduleRouteComponents: ModuleRouteComponentRegistries
): ServerRouteDefinition[] => {
    const routes: ServerRouteDefinition[] = [];

    modules.forEach((module) => {
        module.routes?.server?.forEach((route) => {
            const path = normalizeServerPath(route.path);

            routes.push({
                path,
                name: route.name ?? undefined,
                exact: route.exact,
                permission: route.permission ?? null,
                component: resolveComponent(module.slug, 'server', route.path, path, moduleRouteComponents),
            });
        });
    });

    return routes;
};

export const buildRouteRegistry = (
    frontendRegistry?: ModularFrontendRegistryPayload,
    moduleRouteComponents: ModuleRouteComponentRegistries = generatedRoutes
): RouteRegistry => {
    const modules = getFrontendModules(frontendRegistry);

    return {
        account: mergeRouteDefinitions(coreRoutes.account, buildAccountRoutes(modules, moduleRouteComponents)),
        server: mergeRouteDefinitions(coreRoutes.server, buildServerRoutes(modules, moduleRouteComponents)),
    };
};

export const routeRegistry = buildRouteRegistry();

export default routeRegistry;
