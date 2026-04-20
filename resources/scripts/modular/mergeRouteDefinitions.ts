import type { RouteDefinition } from './routeTypes';

export default function mergeRouteDefinitions<T extends RouteDefinition>(coreRoutes: readonly T[], moduleRoutes: readonly T[]) {
    return [...coreRoutes, ...moduleRoutes];
}
