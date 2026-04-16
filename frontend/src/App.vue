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
