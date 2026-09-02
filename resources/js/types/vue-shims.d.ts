declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    // Fallback type for .vue files not covered by tsconfig `include`
    // (the plain-JS layouts/components still on `<script setup>` without lang="ts").
    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;

    export default component;
}
