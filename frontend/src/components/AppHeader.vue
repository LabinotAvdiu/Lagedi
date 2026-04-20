<template>
  <header class="app-header">
    <div class="header-left">
      <router-link to="/" class="app-logo">
        <span class="brand-name">Termini Im</span>
      </router-link>
    </div>
    <nav class="header-center">
      <router-link to="/" class="nav-link">{{ t("nav.home") }}</router-link>
      <router-link v-if="isCompany" to="/dashboard" class="nav-link nav-link--dashboard">
        <i class="pi pi-th-large" />
        Dashboard
      </router-link>
    </nav>
    <div class="header-right">
      <router-link v-if="!isLoggedIn" to="/pro" class="nav-link nav-link--pro">
        <i class="pi pi-briefcase" />
        {{ t("nav.iAmProfessional") }}
      </router-link>
      <Button
        :label="t('nav.myAccount')"
        rounded
        class="account-btn"
        :icon="isLoggedIn ? 'pi pi-user-check' : 'pi pi-user'"
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
import { ref, computed, onMounted, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import Button from "primevue/button";
import Menu from "primevue/menu";
import Select from "primevue/select";
import { isLoggedIn, setLoggedIn } from "../composables/useAuth";

const { t, locale } = useI18n();
const router = useRouter();

const accountMenu = ref();

const isCompany = computed(() => {
  if (!isLoggedIn.value) return false;
  try {
    const user = JSON.parse(localStorage.getItem("user") || "{}");
    return user.role === "company";
  } catch {
    return false;
  }
});

const languages = [
  { label: "🇫🇷 Français", value: "fr" },
  { label: "🇬🇧 English", value: "en" },
  { label: "🇦🇱 Shqip", value: "sq" },
];

onMounted(() => {
  const saved = localStorage.getItem("locale");
  if (saved && ["fr", "en", "sq"].includes(saved)) {
    locale.value = saved;
  }
});

watch(locale, (v) => {
  localStorage.setItem("locale", v);
});

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
  padding: 0.6rem 2rem;
  background: var(--color-black);
  border-bottom: 1px solid var(--color-accent-a25);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  flex: 1;
}

.app-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
}

.brand-name {
  font-family: "Cinzel", serif;
  font-size: 1.6rem;
  font-weight: 900;
  background: linear-gradient(
    135deg,
    var(--color-gold-shine) 0%,
    var(--color-gold-mid) 30%,
    var(--color-accent) 50%,
    var(--color-gold-glow) 70%,
    var(--color-accent-dark) 100%
  );
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: 0.08em;
  filter: drop-shadow(0 1px 6px var(--color-accent-a50));
}

.header-center {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2rem;
}

.nav-link {
  color: var(--color-white);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: color 0.2s;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.nav-link:hover,
.nav-link.router-link-active,
.nav-link.router-link-exact-active {
  color: var(--color-white);
}

.nav-link--pro {
  border: 2px solid var(--color-accent);
  border-radius: 20px;
  padding: 0.3rem 0.9rem;
  color: var(--color-white);
}

.header-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
}

.account-btn {
  font-weight: 600;
  font-size: 0.875rem;
}

.lang-selector {
  --p-select-background: transparent;
  --p-select-border-color: var(--color-accent);
  --p-select-color: var(--color-white);
  --p-select-hover-border-color: var(--color-gold-mid);
  --p-select-focus-border-color: var(--color-gold-mid);
  font-size: 0.85rem;
  color: var(--color-white);
  border: 1px solid var(--color-accent);
  border-radius: 20px;
  overflow: hidden;
}

.lang-selector :deep(.p-select-label),
.lang-selector :deep(.p-select-dropdown) {
  color: var(--color-white);
}

@media (max-width: 768px) {
  .app-header {
    padding: 0.6rem 1rem;
  }

  .header-center {
    display: none;
  }
}
</style>
