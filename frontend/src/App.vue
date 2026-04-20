<template>
  <div id="app">
    <AppHeader v-if="!isDashboard" />
    <router-view />
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import AppHeader from "./components/AppHeader.vue";

const route = useRoute();
const isDashboard = computed(() => route.path.startsWith("/dashboard"));
</script>

<style>
:root {
  --color-primary: #0a0a0a;
  --color-primary-light: #1a1a1a;
  --color-accent: #c9a84c;
  --color-accent-light: #f5e27a;
  --color-accent-dark: #8b6914;
  --color-accent-hover: #d4aa50;
  --color-secondary: #c9a84c;
  --color-secondary-hover: #a87b2e;
  --color-white: #ffffff;
  --color-black: #0a0a0a;

  --color-gold-shine: #bfa14a;
  --color-gold-mid: #f0d060;
  --color-gold-glow: #f5e27a;

  --color-accent-a10: rgba(201, 168, 76, 0.1);
  --color-accent-a12: rgba(201, 168, 76, 0.12);
  --color-accent-a20: rgba(201, 168, 76, 0.2);
  --color-accent-a25: rgba(201, 168, 76, 0.25);
  --color-accent-a30: rgba(201, 168, 76, 0.3);
  --color-accent-a40: rgba(201, 168, 76, 0.4);
  --color-accent-a50: rgba(201, 168, 76, 0.5);
  --color-accent-a06: rgba(201, 168, 76, 0.06);
  --color-accent-a35: rgba(201, 168, 76, 0.35);

  --color-text: #333333;
  --color-text-light: #ffffff;
  --color-text-muted: #bbbbbb;
  --color-text-subtle: #9ca3af;
  --color-text-dark: #222222;
  --color-text-placeholder: #777777;
  --color-text-icon: #999999;
  --color-text-toggle: #6b7280;
  --color-text-error: #dc2626;
  --color-text-gold: #c9a84c;
  --color-text-secondary: #cccccc;
  --color-text-light-muted: #dddddd;

  --color-white-a75: rgba(255, 255, 255, 0.75);
  --color-white-a60: rgba(255, 255, 255, 0.6);
  --color-white-a08: rgba(255, 255, 255, 0.08);

  --color-shadow-dark: rgba(0, 0, 0, 0.25);
  --color-shadow-sm: rgba(0, 0, 0, 0.04);
  --color-shadow-md: rgba(0, 0, 0, 0.06);
  --color-shadow-lg: rgba(0, 0, 0, 0.1);
  --color-shadow-xl: rgba(0, 0, 0, 0.12);

  --color-overlay-dark: rgba(0, 0, 0, 0.65);
  --color-overlay-mid: rgba(0, 0, 0, 0.55);

  --color-near-black: #111111;
  --color-accent-gold-light: #e8c96a;

  --color-white-a06: rgba(255, 255, 255, 0.06);
  --color-white-a45: rgba(255, 255, 255, 0.45);

  --color-danger-bg: #fef2f2;
  --color-danger-border: #fecaca;
  --color-danger-light: #f87171;
  --color-danger-hover-bg: rgba(220, 38, 38, 0.12);

  --color-success: #15803d;
  --color-success-mid: #16a34a;
  --color-success-bright: #22c55e;
  --color-success-bg: #f0fdf4;
  --color-success-bg-mid: #dcfce7;
  --color-success-border: #bbf7d0;

  --color-warning: #f59e0b;
  --color-warning-bg: #fef3c7;
  --color-warning-text: #92400e;

  --color-info: #3b82f6;
  --color-info-hover: #2563eb;
  --color-info-bg: #eff6ff;
  --color-info-text: #1d4ed8;

  --color-neutral-100: #f1f5f9;
  --color-neutral-400: #94a3b8;
  --color-neutral-500: #64748b;

  --color-bg: #fafafa;
  --color-bg-page: #f4f4f4;
  --color-bg-white: #ffffff;
  --color-bg-dark: #0a0a0a;
  --color-bg-dark-light: #141414;
  --color-bg-header: rgba(10, 10, 10, 0.7);

  --color-border: #d1d5db;
  --color-border-dark: #000000;
  --color-border-light: rgba(255, 255, 255, 0.12);
  --color-border-hover: rgba(201, 168, 76, 0.6);
  --color-separator: #e5e7eb;

  --color-hover-overlay: rgba(255, 255, 255, 0.05);

  --p-inputtext-background: var(--color-bg-white);
  --p-inputtext-color: var(--color-primary);
  --p-inputtext-border-color: var(--color-border-dark);
  --p-inputtext-hover-border-color: var(--color-accent);
  --p-inputtext-focus-border-color: var(--color-accent);
  --p-inputtext-placeholder-color: var(--color-text-placeholder);
  --p-inputtext-shadow: none;
}

*,
*::before,
*::after {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family:
    "Inter",
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    Roboto,
    sans-serif;
  -webkit-font-smoothing: antialiased;
  color: var(--color-text);
  background: var(--color-bg);
}

#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.p-inputtext,
.p-password input,
.p-inputtextarea,
.p-dropdown .p-dropdown-label {
  background: var(--color-bg-white);
  color: var(--color-text-dark);
  border: 1px solid var(--color-border-dark);
  box-shadow: none;
  border-radius: 8px;
}

.p-inputtext::placeholder,
.p-password input::placeholder,
.p-inputtextarea::placeholder {
  color: var(--color-text-placeholder);
  opacity: 1;
}

.p-inputtext:focus,
.p-password input:focus,
.p-inputtextarea:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
}
</style>
