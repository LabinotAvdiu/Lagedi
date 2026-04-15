<template>
  <header class="app-header">
    <div class="header-left">
      <router-link to="/" class="app-logo">
        {{ t("app.name") }}
      </router-link>
    </div>
    <div class="header-right">
      <Button :label="t('nav.iAmProfessional')" outlined class="nav-btn" />
      <Button
        :label="t('nav.myAccount')"
        severity="contrast"
        rounded
        class="account-btn"
        :icon="isLoggedIn ? 'pi pi-check-circle' : undefined"
        icon-pos="right"
        @click="onAccountClick"
      />
      <Menu ref="accountMenu" :model="accountMenuItems" popup />
      <Select
        v-model="locale"
        :options="languages"
        option-label="label"
        option-value="value"
        class="lang-selector"
      />
    </div>
  </header>
</template>

<script setup>
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import Button from "primevue/button";
import Menu from "primevue/menu";
import Select from "primevue/select";
import { isLoggedIn, setLoggedIn } from "../composables/useAuth";

const { t, locale } = useI18n();
const router = useRouter();

const accountMenu = ref();

const languages = [
  { label: "FR", value: "fr" },
  { label: "EN", value: "en" },
];

const accountMenuItems = computed(() => [
  {
    label: t("nav.logout"),
    icon: "pi pi-sign-out",
    command: () => {
      setLoggedIn(false);
      router.push("/connexion");
    },
  },
]);

const onAccountClick = (event) => {
  if (isLoggedIn.value) {
    accountMenu.value.toggle(event);
  } else {
    router.push("/connexion");
  }
};
</script>

<style scoped>
.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 2rem;
  background: var(--color-bg-header);
  position: sticky;
  top: 0;
  z-index: 100;
}

.app-logo {
  font-size: 1.6rem;
  font-weight: 800;
  text-decoration: none;
  color: var(--color-text-light);
  letter-spacing: -0.02em;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nav-btn {
  color: var(--color-text-light);
  border-color: var(--color-border-hover);
  font-weight: 500;
}

.nav-btn:hover {
  background: var(--color-hover-overlay);
  border-color: var(--color-text-light);
}

.account-btn {
  font-weight: 600;
}

.lang-selector {
  --p-select-background: transparent;
  --p-select-border-color: var(--color-border-light);
  --p-select-color: var(--color-text-muted);
  --p-select-hover-border-color: var(--color-text-light);
  font-size: 0.85rem;
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
