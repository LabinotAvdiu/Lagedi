<template>
  <header class="app-header">
    <div class="header-left">
      <router-link
        to="/"
        class="app-logo"
      >
        {{ t("app.name") }}
      </router-link>
    </div>
    <div class="header-right">
      <Button
        :label="t('nav.iAmProfessional')"
        text
        class="nav-btn"
      />
      <Button
        :label="t('nav.myAccount')"
        severity="contrast"
        rounded
        class="account-btn"
      />
      <select
        :value="locale"
        class="lang-selector"
        @change="onLangChange"
      >
        <option value="fr">FR</option>
        <option value="en">EN</option>
      </select>
    </div>
  </header>
</template>

<script setup>
import { useI18n } from "vue-i18n";
import Button from "primevue/button";

const { t, locale } = useI18n();

const onLangChange = (event) => {
  locale.value = event.target.value;
};
</script>

<style scoped>
.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 2rem;
  background: #1a1a2e;
  position: sticky;
  top: 0;
  z-index: 100;
}

.app-logo {
  font-size: 1.6rem;
  font-weight: 800;
  text-decoration: none;
  color: #ffffff;
  letter-spacing: -0.02em;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nav-btn {
  color: #b0bec5 !important;
  font-weight: 500;
}

.nav-btn:hover {
  color: #ffffff !important;
}

.account-btn {
  font-weight: 600;
}

.lang-selector {
  padding: 0.35rem 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 6px;
  cursor: pointer;
  background: transparent;
  color: #b0bec5;
  font-size: 0.85rem;
}

.lang-selector option {
  background: #1a1a2e;
  color: #ffffff;
}

@media (max-width: 768px) {
  .app-header {
    padding: 0.75rem 1rem;
  }

  .nav-btn {
    display: none;
  }
}
</style>
