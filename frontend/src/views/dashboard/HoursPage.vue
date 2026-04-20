<template>
  <div class="hours-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t("dashboard.tabs.hours") }}</h1>
        <p class="page-subtitle">{{ t("dashboard.hours.subtitle") }}</p>
      </div>
    </header>

    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else>
      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-building" /> {{ t("dashboard.hours.companyHours") }}</h2>
          <template v-if="isOwner">
            <Button
              v-if="!editingCompany"
              icon="pi pi-pencil"
              :label="t('dashboard.hours.edit')"
              severity="secondary"
              outlined
              size="small"
              @click="startEditingCompany"
            />
            <div v-else class="header-actions">
              <Button
                icon="pi pi-times"
                severity="secondary"
                text
                rounded
                @click="cancelEditingCompany"
              />
              <Button
                icon="pi pi-check"
                :label="t('dashboard.hours.save')"
                class="accent-button"
                size="small"
                :loading="savingCompany"
                @click="saveCompanyHours"
              />
            </div>
          </template>
        </div>
        <div class="hours-list">
          <div v-for="h in companyHours" :key="'c-' + h.dayOfWeek" class="hour-row">
            <span class="day-name">{{ dayNames[h.dayOfWeek] }}</span>
            <template v-if="!editingCompany">
              <span v-if="h.isClosed" class="closed-badge">{{ t("dashboard.salon.closed") }}</span>
              <span v-else class="hour-time">{{ h.openTime?.substring(0,5) }} – {{ h.closeTime?.substring(0,5) }}</span>
            </template>
            <template v-else>
              <label class="toggle-label">
                <input type="checkbox" :checked="!h.isClosed" @change="h.isClosed = !h.isClosed" />
                {{ t("dashboard.hours.open") }}
              </label>
              <template v-if="!h.isClosed">
                <InputText v-model="h.openTime" type="time" class="time-input" />
                <span class="separator">—</span>
                <InputText v-model="h.closeTime" type="time" class="time-input" />
              </template>
            </template>
          </div>
        </div>
        <div v-if="saveCompanyError" class="error-inline">
          <i class="pi pi-exclamation-triangle" /> {{ saveCompanyError }}
        </div>
      </section>

      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-clock" /> {{ t("dashboard.hours.myHours") }}</h2>
          <Button
            v-if="!editing"
            icon="pi pi-pencil"
            :label="t('dashboard.hours.edit')"
            severity="secondary"
            outlined
            size="small"
            @click="startEditing"
          />
          <div v-else class="header-actions">
            <Button
              icon="pi pi-times"
              severity="secondary"
              text
              rounded
              @click="cancelEditing"
            />
            <Button
              icon="pi pi-check"
              :label="t('dashboard.hours.save')"
              class="accent-button"
              size="small"
              @click="saveHours"
              :loading="saving"
            />
          </div>
        </div>

        <div class="hours-list">
          <div v-for="h in employeeHours" :key="'e-' + h.dayOfWeek" class="hour-row">
            <span class="day-name">{{ dayNames[h.dayOfWeek] }}</span>
            <template v-if="!editing">
              <span v-if="!h.isWorking" class="closed-badge">{{ t("dashboard.hours.off") }}</span>
              <span v-else class="hour-time">{{ h.startTime }} – {{ h.endTime }}</span>
            </template>
            <template v-else>
              <label class="toggle-label">
                <input type="checkbox" v-model="h.isWorking" />
                {{ t("dashboard.hours.working") }}
              </label>
              <template v-if="h.isWorking">
                <InputText v-model="h.startTime" type="time" class="time-input" />
                <span class="separator">—</span>
                <InputText v-model="h.endTime" type="time" class="time-input" />
              </template>
            </template>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import InputText from "primevue/inputtext";
import Button from "primevue/button";
import { scheduleService } from "../../services/scheduleService";
import { myCompanyService } from "../../services/myCompanyService";

const { t } = useI18n();

const loading = ref(true);
const editing = ref(false);
const saving = ref(false);
const companyHours = ref([]);
const employeeHours = ref([]);
let originalHours = [];

const editingCompany = ref(false);
const savingCompany = ref(false);
const saveCompanyError = ref("");
let originalCompanyHours = [];

const dayNames = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];

const isOwner = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem("user") || "{}");
    return user.companyRole === "owner";
  } catch {
    return false;
  }
});

function startEditingCompany() {
  originalCompanyHours = companyHours.value.map((h) => ({ ...h }));
  editingCompany.value = true;
  saveCompanyError.value = "";
}

function cancelEditingCompany() {
  companyHours.value = originalCompanyHours.map((h) => ({ ...h }));
  editingCompany.value = false;
  saveCompanyError.value = "";
}

async function saveCompanyHours() {
  savingCompany.value = true;
  saveCompanyError.value = "";
  try {
    const hours = companyHours.value.map((h) => ({
      day_of_week: h.dayOfWeek,
      open_time: h.isClosed ? null : (h.openTime || null),
      close_time: h.isClosed ? null : (h.closeTime || null),
      is_closed: !!h.isClosed,
    }));
    const result = await myCompanyService.updateHours(hours);
    const updated = Array.isArray(result) ? result : result.data ?? [];
    if (updated.length) {
      companyHours.value = normalizeCompanyHours(updated);
    }
    editingCompany.value = false;
  } catch (e) {
    saveCompanyError.value = t("dashboard.hours.saveError");
    console.error("Failed to save company hours", e);
  } finally {
    savingCompany.value = false;
  }
}

function normalizeCompanyHours(raw) {
  const map = {};
  for (const h of raw) {
    const dayIndex = typeof h.dayOfWeek === "number" ? h.dayOfWeek : h.day_of_week;
    map[dayIndex] = {
      dayOfWeek: dayIndex,
      openTime: h.openTime ?? h.open_time ?? "09:00",
      closeTime: h.closeTime ?? h.close_time ?? "18:00",
      isClosed: h.isClosed ?? h.is_closed ?? false,
    };
  }
  return Array.from({ length: 7 }, (_, i) =>
    map[i] ?? { dayOfWeek: i, openTime: "09:00", closeTime: "18:00", isClosed: true }
  );
}

async function loadSettings() {
  loading.value = true;
  try {
    const settings = await scheduleService.settings();
    const rawCompany = settings.companyHours ?? settings.company_hours ?? [];
    companyHours.value = normalizeCompanyHours(rawCompany);
    employeeHours.value = (settings.employeeHours ?? settings.employee_hours ?? []).map((h) => ({ ...h }));
    for (let i = 0; i < 7; i++) {
      if (!employeeHours.value.find((h) => h.dayOfWeek === i || h.day_of_week === i)) {
        employeeHours.value.push({
          dayOfWeek: i,
          startTime: "09:00",
          endTime: "18:00",
          isWorking: true,
        });
      }
    }
    employeeHours.value.sort((a, b) => (a.dayOfWeek ?? a.day_of_week) - (b.dayOfWeek ?? b.day_of_week));
    employeeHours.value = employeeHours.value.map((h) => ({
      dayOfWeek: h.dayOfWeek ?? h.day_of_week,
      startTime: h.startTime ?? h.start_time ?? "09:00",
      endTime: h.endTime ?? h.end_time ?? "18:00",
      isWorking: h.isWorking ?? h.is_working ?? true,
    }));
  } catch (e) {
    console.error("Failed to load schedule settings", e);
  } finally {
    loading.value = false;
  }
}

function startEditing() {
  originalHours = employeeHours.value.map((h) => ({ ...h }));
  editing.value = true;
}

function cancelEditing() {
  employeeHours.value = originalHours.map((h) => ({ ...h }));
  editing.value = false;
}

async function saveHours() {
  saving.value = true;
  try {
    const hours = employeeHours.value.map((h) => ({
      day_of_week: h.dayOfWeek,
      start_time: h.startTime,
      end_time: h.endTime,
      is_working: h.isWorking,
    }));
    await scheduleService.updateHours(hours);
    editing.value = false;
  } catch (e) {
    console.error("Failed to save hours", e);
  } finally {
    saving.value = false;
  }
}

onMounted(loadSettings);
</script>

<style scoped>
.hours-page {
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

.section {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.75rem 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.section-header h2 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-text-dark);
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.section-header h2 i {
  color: var(--color-accent);
  font-size: 1.1rem;
}

.header-actions {
  display: flex;
  gap: 0.25rem;
}

.hours-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.hour-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.65rem 0;
  border-bottom: 1px solid var(--color-separator);
}

.hour-row:last-child {
  border-bottom: none;
}

.day-name {
  width: 110px;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text-dark);
}

.hour-time {
  font-size: 0.9rem;
  color: var(--color-text);
  font-weight: 500;
}

.closed-badge {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-error);
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  color: var(--color-text-subtle);
  min-width: 100px;
  cursor: pointer;
}

.time-input {
  width: 120px;
}

.separator {
  color: var(--color-text-subtle);
}

.accent-button {
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
}

.error-inline {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: var(--color-text-error);
  margin-top: 0.75rem;
}

@media (max-width: 860px) {
  .hours-page {
    padding: 1.5rem 1.25rem;
  }
}
</style>
