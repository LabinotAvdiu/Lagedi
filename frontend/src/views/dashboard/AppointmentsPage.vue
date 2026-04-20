<template>
  <div class="appointments-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t("dashboard.appointments.title") }}</h1>
        <p class="page-subtitle">{{ t("dashboard.appointments.subtitle") }}</p>
      </div>
    </header>

    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else>

      <div v-if="appointments.length" class="appointments-list">
        <article
          v-for="apt in appointments"
          :key="apt.id"
          class="appointment-card"
          :class="statusClass(apt.status)"
        >
          <div class="apt-left">
            <div class="apt-date">
              <span class="apt-day">{{ formatDay(apt.dateTime) }}</span>
              <span class="apt-time">{{ formatTime(apt.dateTime) }}</span>
            </div>
          </div>
          <div class="apt-center">
            <span class="apt-service">{{ apt.serviceName }}</span>
            <span v-if="apt.clientName || apt.walkInFirstName" class="apt-client">
              <i class="pi pi-user" />
              {{ apt.clientName || apt.walkInFirstName }}
            </span>
            <span v-if="apt.employeeName" class="apt-employee">
              <i class="pi pi-briefcase" />
              {{ apt.employeeName }}
            </span>
            <span class="apt-meta">
              {{ apt.durationMinutes }}min · {{ apt.price }}€
            </span>
          </div>
          <div class="apt-right">
            <span class="apt-status" :class="statusClass(apt.status)">
              {{ statusLabel(apt.status) }}
            </span>
            <div class="apt-actions" v-if="apt.status === 'pending' || apt.status === 'confirmed'">
              <Button
                v-if="apt.status === 'pending'"
                icon="pi pi-check"
                :label="t('appointments.confirm')"
                size="small"
                class="action-confirm"
                :loading="updatingId === apt.id"
                @click.stop="changeStatus(apt, 'confirmed')"
              />
              <Button
                v-if="apt.status === 'confirmed'"
                icon="pi pi-flag"
                :label="t('appointments.complete')"
                size="small"
                class="action-complete"
                :loading="updatingId === apt.id"
                @click.stop="changeStatus(apt, 'completed')"
              />
              <Button
                icon="pi pi-times"
                :label="t('appointments.cancel')"
                size="small"
                severity="danger"
                outlined
                :loading="updatingId === apt.id"
                @click.stop="changeStatus(apt, 'cancelled')"
              />
            </div>
          </div>
        </article>
      </div>

      <div v-else class="empty-state">
        <i class="pi pi-calendar-plus" />
        <p>{{ t("dashboard.appointments.empty") }}</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import { bookingService } from "../../services/bookingService";

const { t } = useI18n();

const loading = ref(true);
const appointments = ref([]);
const updatingId = ref(null);

async function changeStatus(apt, status) {
  updatingId.value = apt.id;
  try {
    await bookingService.updateStatus(apt.id, status);
    apt.status = status;
  } catch (e) {
    console.error("Failed to update status", e);
  } finally {
    updatingId.value = null;
  }
}

const statusLabels = {
  pending: "En attente",
  confirmed: "Confirmé",
  cancelled: "Annulé",
  completed: "Terminé",
};

function statusLabel(status) {
  return statusLabels[status] || status;
}

function statusClass(status) {
  return `status-${status}`;
}

function formatDay(dateTime) {
  const d = new Date(dateTime);
  return d.toLocaleDateString("fr-FR", {
    weekday: "short",
    day: "numeric",
    month: "short",
  });
}

function formatTime(dateTime) {
  const d = new Date(dateTime);
  return d.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
}

async function loadAppointments() {
  loading.value = true;
  try {
    const result = await bookingService.list();
    appointments.value = Array.isArray(result) ? result : result.data ?? [];
  } catch (e) {
    console.error("Failed to load appointments", e);
    appointments.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(loadAppointments);
</script>

<style scoped>
.appointments-page {
  padding: 2rem 2.5rem;
}

.page-header {
  margin-bottom: 1.75rem;
}

.page-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--color-text-dark);
  letter-spacing: -0.02em;
}

.page-subtitle {
  font-size: 0.9rem;
  color: var(--color-text-subtle);
  margin-top: 0.25rem;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 3rem;
  font-size: 2rem;
  color: var(--color-accent);
}

.appointments-list {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  overflow: hidden;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.appointment-card {
  display: grid;
  grid-template-columns: 100px 1fr auto;
  align-items: center;
  gap: 1.5rem;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--color-separator);
  transition: background 0.15s;
}

.appointment-card:last-child {
  border-bottom: none;
}

.appointment-card:hover {
  background: var(--color-bg);
}

.apt-left {
  flex-shrink: 0;
}

.apt-date {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.apt-day {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-text-dark);
  text-transform: capitalize;
}

.apt-time {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-accent);
}

.apt-center {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.apt-service {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--color-text-dark);
}

.apt-client,
.apt-employee {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.apt-meta {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.apt-right {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}

.apt-actions {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.action-confirm {
  --p-button-background: var(--color-success-bright);
  --p-button-border-color: var(--color-success-bright);
  --p-button-color: var(--color-white);
  --p-button-hover-background: var(--color-success-mid);
  --p-button-hover-border-color: var(--color-success-mid);
}

.action-complete {
  --p-button-background: var(--color-info);
  --p-button-border-color: var(--color-info);
  --p-button-color: var(--color-white);
  --p-button-hover-background: var(--color-info-hover);
  --p-button-hover-border-color: var(--color-info-hover);
}

.apt-status {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.3rem 0.85rem;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.apt-status.status-pending {
  background: var(--color-warning-bg);
  color: var(--color-warning-text);
}

.apt-status.status-confirmed {
  background: var(--color-success-bg-mid);
  color: var(--color-success);
}

.apt-status.status-cancelled {
  background: var(--color-danger-bg);
  color: var(--color-text-error);
}

.apt-status.status-completed {
  background: var(--color-info-bg);
  color: var(--color-info-text);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 5rem 2rem;
  text-align: center;
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.empty-state i {
  font-size: 3rem;
  color: var(--color-text-subtle);
}

.empty-state p {
  font-size: 1rem;
  color: var(--color-text-subtle);
}

@media (max-width: 860px) {
  .appointments-page {
    padding: 1.5rem 1.25rem;
  }

  .appointment-card {
    grid-template-columns: 1fr;
    gap: 0.75rem;
    padding: 1rem;
  }

  .apt-date {
    flex-direction: row;
    gap: 0.5rem;
  }
}
</style>
