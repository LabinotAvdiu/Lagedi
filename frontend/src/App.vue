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
  color: #333;
  background: #fafafa;
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
  background: #ffffff !important;
  color: #000000 !important;
  border: 1px solid #000000 !important;
  box-shadow: none !important;
  border-radius: 8px !important;
}

.p-inputtext::placeholder,
.p-password input::placeholder,
.p-inputtextarea::placeholder {
  color: #6b6b6b !important;
  opacity: 1 !important;
}

.p-inputtext:focus,
.p-password input:focus,
.p-inputtextarea:focus {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(0,0,0,0.05) !important;
}
</style>
