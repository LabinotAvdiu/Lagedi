<template>
  <div id="app">
    <AppHeader />
    <router-view />
  </div>
</template>

<script setup>
import AppHeader from "./components/AppHeader.vue";
</script>

<style>
:root {
  --color-primary: #1a1a2e;
  --color-secondary: #e94560;
  --color-secondary-hover: #d63851;

  --color-text: #333333;
  --color-text-light: #ffffff;
  --color-text-muted: #b0bec5;
  --color-text-subtle: #9ca3af;
  --color-text-dark: #222222;
  --color-text-placeholder: #777777;
  --color-text-icon: #999999;
  --color-text-toggle: #6b7280;
  --color-text-error: #dc2626;

  --color-bg: #fafafa;
  --color-bg-page: #f4f4f4;
  --color-bg-white: #ffffff;
  --color-bg-header: var(--color-primary);

  --color-border: #d1d5db;
  --color-border-dark: #000000;
  --color-border-light: rgba(255, 255, 255, 0.2);
  --color-border-hover: rgba(255, 255, 255, 0.7);
  --color-separator: #e5e7eb;

  --color-hover-overlay: rgba(255, 255, 255, 0.1);

  /* PrimeVue InputText tokens */
  --p-inputtext-background: var(--color-bg-white);
  --p-inputtext-color: var(--color-primary);
  --p-inputtext-border-color: var(--color-border-dark);
  --p-inputtext-hover-border-color: var(--color-primary);
  --p-inputtext-focus-border-color: var(--color-primary);
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
  box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
}
</style>
