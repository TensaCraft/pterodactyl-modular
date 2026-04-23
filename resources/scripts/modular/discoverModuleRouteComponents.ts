import type { ModuleRouteComponentRegistries, ModuleRouteComponentRegistry } from './routeTypes';

type ModuleRoutesModule = {
    default?: ModuleRouteComponentRegistry;
    slug?: string;
};

declare const require: NodeRequire & {
    context?: (
        path: string,
        deep?: boolean,
        filter?: RegExp
    ) => __WebpackModuleApi.RequireContext;
};

const loadRoutesContext = (): __WebpackModuleApi.RequireContext | null => {
    try {
        return require.context('../../../Modules', true, /Resources\/scripts\/(?:modular\/)?routes\.tsx?$/);
    } catch {
        return null;
    }
};

const routesContext = loadRoutesContext();

const pascalCaseToKebabCase = (value: string) =>
    value
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
        .toLowerCase();

const deriveSlugFromContextKey = (key: string): string | null => {
    const match = key.match(/^\.\/([^/]+)\/Resources\/scripts\/(?:modular\/)?routes\.tsx?$/);

    if (!match) {
        return null;
    }

    return pascalCaseToKebabCase(match[1]);
};

const discoverModuleRouteComponents = (): ModuleRouteComponentRegistries => {
    const registries: ModuleRouteComponentRegistries = {};

    if (!routesContext) {
        return registries;
    }

    routesContext.keys().forEach((key) => {
        const moduleDefinition = routesContext(key) as ModuleRoutesModule;
        const slug = moduleDefinition.slug ?? deriveSlugFromContextKey(key);

        if (!slug || !moduleDefinition.default) {
            return;
        }

        registries[slug] = moduleDefinition.default;
    });

    return registries;
};

export const discoveredModuleRouteComponents = discoverModuleRouteComponents();

export default discoveredModuleRouteComponents;
