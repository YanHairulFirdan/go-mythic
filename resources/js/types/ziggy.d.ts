// Ziggy's `route()` helper is exposed as a global by the ZiggyVue plugin
// (resources/js/app.js) and the `@routes` Blade directive.
declare global {
    function route(
        name?: string,
        params?: Record<string, unknown> | Array<unknown> | string | number,
        absolute?: boolean,
    ): string;
}

export {};
