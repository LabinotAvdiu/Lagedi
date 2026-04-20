<template>
  <div class="dashboard">
    <aside class="sidebar">
      <router-link to="/" class="sidebar-brand">
        <span class="brand-dot" />
        <span class="brand-name">Termini Im</span>
      </router-link>

      <nav class="sidebar-nav">
        <router-link
          v-for="tab in tabs"
          :key="tab.name"
          :to="tab.to"
          class="sidebar-link"
          :class="{ active: $route.name === tab.name }"
        >
          <i :class="tab.icon" />
          <span>{{ t(`dashboard.tabs.${tab.key}`) }}</span>
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <router-link to="/dashboard/profil" class="sidebar-user">
          <div class="user-avatar">{{ userInitials }}</div>
          <div class="user-info">
            <span class="user-name">{{ userName }}</span>
            <span class="user-role">{{ t("dashboard.sidebar.company") }}</span>
          </div>
        </router-link>
        <button class="logout-btn" @click="onLogout">
          <i class="pi pi-sign-out" />
        </button>
      </div>
    </aside>

    <main class="dashboard-main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import { setLoggedIn } from "../../composables/useAuth";

const { t } = useI18n();
const router = useRouter();

const tabs = [
  { key: "search", name: "dashboard-search", to: "/dashboard/rechercher", icon: "pi pi-search" },
  { key: "mySalon", name: "dashboard-salon", to: "/dashboard/mon-salon", icon: "pi pi-building" },
  { key: "planning", name: "dashboard-planning", to: "/dashboard/planning", icon: "pi pi-calendar" },
  { key: "hours", name: "dashboard-hours", to: "/dashboard/horaires", icon: "pi pi-clock" },
  { key: "appointments", name: "dashboard-appointments", to: "/dashboard/mes-rdv", icon: "pi pi-calendar-plus" },
];

const user = computed(() => {
  try {
    return JSON.parse(localStorage.getItem("user") || "{}");
  } catch {
    return {};
  }
});

const userName = computed(() =>
  [user.value.firstName, user.value.lastName].filter(Boolean).join(" ") || "—"
);

const userInitials = computed(() => {
  const f = user.value.firstName?.charAt(0) || "";
  const l = user.value.lastName?.charAt(0) || "";
  return (f + l).toUpperCase() || "?";
});

function onLogout() {
  setLoggedIn(false);
  router.push("/connexion");
}
</script>

<style scoped>
.dashboard {
  display: flex;
  min-height: 100vh;
  background: var(--color-bg-page);
}

.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 260px;
  background: var(--color-black);
  display: flex;
  flex-direction: column;
  z-index: 120;
  border-right: 1px solid var(--color-white-a06);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 1.5rem 1.5rem 2rem;
  text-decoration: none;
}

.brand-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--color-accent);
  box-shadow: 0 0 8px var(--color-accent-a40);
}

.brand-name {
  font-family: "Cinzel", serif;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-white);
  letter-spacing: 0.04em;
}

.sidebar-nav {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0 0.75rem;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  text-decoration: none;
  color: var(--color-text-muted);
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s;
}

.sidebar-link i {
  font-size: 1.15rem;
  width: 22px;
  text-align: center;
}

.sidebar-link:hover {
    background: var(--color-hover-overlay);
  color: var(--color-white);
}

.sidebar-link.active,
.sidebar-link.router-link-exact-active {
  background: var(--color-accent-a12);
  color: var(--color-accent);
  font-weight: 600;
}

.sidebar-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--color-white-a06);
}

.sidebar-user {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
  text-decoration: none;
  border-radius: 10px;
  padding: 0.35rem 0.5rem;
  margin: -0.35rem -0.5rem;
  transition: background 0.15s;
}

.sidebar-user:hover {
background: var(--color-white-a06);
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-accent-a25);
  color: var(--color-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.8rem;
  flex-shrink: 0;
}

.user-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-white);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.7rem;
  color: var(--color-text-muted);
}

.logout-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: all 0.2s;
}

.logout-btn:hover {
  background: var(--color-danger-hover-bg);
  color: var(--color-danger-light);
}

.dashboard-main {
  margin-left: 260px;
  flex: 1;
  min-height: 100vh;
}

@media (max-width: 860px) {
  .sidebar {
    width: 72px;
  }

  .sidebar-brand {
    justify-content: center;
    padding: 1.25rem 0.5rem 1.5rem;
  }

  .brand-name,
  .sidebar-link span,
  .user-info {
    display: none;
  }

  .sidebar-link {
    justify-content: center;
    padding: 0.75rem;
  }

  .sidebar-footer {
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem;
  }

  .dashboard-main {
    margin-left: 72px;
  }
}
</style>
