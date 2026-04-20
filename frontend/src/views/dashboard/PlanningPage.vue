<template>
  <div class="planning-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t("dashboard.tabs.planning") }}</h1>
        <p class="page-subtitle">{{ t("dashboard.planning.subtitle") }}</p>
      </div>
    </header>

    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else>
      <section class="summary-bar" v-if="schedule">
        <div class="summary-item">
          <i class="pi pi-clock" />
          <span v-if="schedule.workHours">{{ schedule.workHours.startTime || schedule.workHours.start }} – {{ schedule.workHours.endTime || schedule.workHours.end }}</span>
          <span v-else class="closed-label">{{ t("dashboard.planning.notWorking") }}</span>
        </div>
        <div class="summary-item">
          <i class="pi pi-calendar" />
          <span>{{ (schedule.appointments || []).length }} {{ t("dashboard.planning.rdv") }}</span>
        </div>
        <div class="summary-item">
          <i class="pi pi-pause-circle" />
          <span>{{ (schedule.breaks || []).length }} {{ t("dashboard.planning.breaks") }}</span>
        </div>
        <div class="summary-item" v-if="schedule.isDayOff">
          <i class="pi pi-ban" />
          <span>{{ schedule.dayOffReason || t("dashboard.planning.daysOff") }}</span>
        </div>
      </section>

      <section class="section" v-if="schedule && schedule.workHours && !schedule.isDayOff">
        <div class="section-header">
          <h2><i class="pi pi-calendar" /> {{ t("dashboard.planning.today") }}</h2>
        </div>
        <div class="timeline">
          <div
            v-for="hour in timelineHours"
            :key="hour"
            class="timeline-row"
          >
            <span class="timeline-hour">{{ String(hour).padStart(2, '0') }}:00</span>
            <div class="timeline-slot">
              <div
                v-for="apt in appointmentsAtHour(hour)"
                :key="apt.id"
                class="timeline-block apt-block"
                :style="blockStyle(apt, hour)"
              >
                <div class="block-content">
                  <span class="block-time">{{ apt.startTime?.substring(0,5) }} – {{ apt.endTime?.substring(0,5) }}</span>
                  <span class="block-title">{{ apt.serviceName }}</span>
                  <span class="block-client">{{ apt.clientFirstName }} {{ apt.clientLastName }}</span>
                  <span v-if="apt.isWalkIn" class="walk-in-badge">Walk-in</span>
                </div>
                <span class="block-price">{{ apt.price }}€</span>
              </div>
              <div
                v-for="brk in breaksAtHour(hour)"
                :key="'brk-' + brk.startTime"
                class="timeline-block break-block"
                :style="breakBlockStyle(brk, hour)"
              >
                <span class="break-text">{{ brk.label || t("dashboard.planning.breaks") }}</span>
                <span class="break-time-label">{{ brk.startTime?.substring(0,5) }} – {{ brk.endTime?.substring(0,5) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section v-else-if="schedule && schedule.isDayOff" class="section day-off-notice">
        <i class="pi pi-ban" />
        <h2>{{ t("dashboard.planning.notWorking") }}</h2>
        <p v-if="schedule.dayOffReason">{{ schedule.dayOffReason }}</p>
      </section>

      <section v-else-if="!schedule || !schedule.workHours" class="section day-off-notice">
        <i class="pi pi-calendar-minus" />
        <h2>{{ t("dashboard.planning.notWorking") }}</h2>
      </section>

      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-ban" /> {{ t("dashboard.planning.daysOff") }}</h2>
          <Button
            icon="pi pi-plus"
            :label="t('dashboard.planning.addDayOff')"
            severity="secondary"
            outlined
            size="small"
            @click="showAddDayOff = true"
          />
        </div>

        <div v-if="showAddDayOff" class="add-form">
          <InputText v-model="newDayOff.date" type="date" />
          <InputText
            v-model="newDayOff.reason"
            :placeholder="t('dashboard.planning.reason')"
          />
          <Button
            icon="pi pi-check"
            size="small"
            @click="addDayOff"
            class="accent-button"
          />
          <Button
            icon="pi pi-times"
            severity="secondary"
            text
            size="small"
            @click="showAddDayOff = false"
          />
        </div>

        <div v-if="daysOff.length" class="days-off-list">
          <div v-for="d in daysOff" :key="d.id" class="day-off-item">
            <div class="day-off-info">
              <span class="day-off-date">{{ formatDate(d.date) }}</span>
              <span v-if="d.reason" class="day-off-reason">{{ d.reason }}</span>
            </div>
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              size="small"
              @click="removeDayOff(d.id)"
            />
          </div>
        </div>
        <p v-else class="empty-text">{{ t("dashboard.planning.noDaysOff") }}</p>
      </section>

      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-pause-circle" /> {{ t("dashboard.planning.breaks") }}</h2>
          <Button
            icon="pi pi-plus"
            :label="t('dashboard.planning.addBreak')"
            severity="secondary"
            outlined
            size="small"
            @click="showAddBreak = true"
          />
        </div>

        <Dialog
          v-model:visible="showAddBreak"
          :header="t('dashboard.planning.addBreak')"
          modal
          :style="{ width: '380px' }"
        >
          <div class="dialog-form">
            <div class="form-row">
              <label>{{ t("dashboard.planning.day") }}</label>
              <Select
                v-model="newBreak.day_of_week"
                :options="dayOptions"
                option-label="label"
                option-value="value"
              />
            </div>
            <div class="form-row">
              <label>{{ t("dashboard.planning.start") }}</label>
              <InputText v-model="newBreak.start_time" type="time" />
            </div>
            <div class="form-row">
              <label>{{ t("dashboard.planning.end") }}</label>
              <InputText v-model="newBreak.end_time" type="time" />
            </div>
            <div class="form-row">
              <label>{{ t("dashboard.planning.label") }}</label>
              <InputText v-model="newBreak.label" :placeholder="t('dashboard.planning.breakLabel')" />
            </div>
          </div>
          <template #footer>
            <Button
              :label="t('dashboard.salon.save')"
              @click="addBreak"
              class="accent-button"
            />
          </template>
        </Dialog>

        <div v-if="breaks.length" class="breaks-grid">
          <div v-for="b in breaks" :key="b.id" class="break-card">
            <div class="break-info">
              <span class="break-day">{{ b.dayOfWeek !== null && b.dayOfWeek !== undefined ? dayNames[b.dayOfWeek] : t("dashboard.planning.everyDay") }}</span>
              <span class="break-time">{{ b.startTime }} - {{ b.endTime }}</span>
              <span v-if="b.label" class="break-name">{{ b.label }}</span>
            </div>
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              size="small"
              @click="removeBreak(b.id)"
            />
          </div>
        </div>
        <p v-else class="empty-text">{{ t("dashboard.planning.noBreaks") }}</p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import { scheduleService } from "../../services/scheduleService";

const { t } = useI18n();

const loading = ref(true);
const schedule = ref(null);
const daysOff = ref([]);
const breaks = ref([]);

const showAddDayOff = ref(false);
const newDayOff = reactive({ date: "", reason: "" });

const showAddBreak = ref(false);
const newBreak = reactive({ day_of_week: null, start_time: "", end_time: "", label: "" });

const dayNames = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];
const dayOptions = [
  { label: "Tous les jours", value: null },
  ...dayNames.map((name, i) => ({ label: name, value: i })),
];

const timelineHours = computed(() => {
  if (!schedule.value?.workHours) return [];
  const startStr = schedule.value.workHours.startTime || schedule.value.workHours.start || "08:00";
  const endStr = schedule.value.workHours.endTime || schedule.value.workHours.end || "19:00";
  const startH = parseInt(startStr.split(":")[0], 10);
  const endH = parseInt(endStr.split(":")[0], 10);
  const hours = [];
  for (let h = startH; h <= endH; h++) hours.push(h);
  return hours;
});

function parseHM(timeStr) {
  if (!timeStr) return { h: 0, m: 0 };
  const [h, m] = timeStr.split(":").map(Number);
  return { h, m: m || 0 };
}

function appointmentsAtHour(hour) {
  return (schedule.value?.appointments || []).filter((a) => {
    const start = parseHM(a.startTime);
    return start.h === hour;
  });
}

function breaksAtHour(hour) {
  return (schedule.value?.breaks || []).filter((b) => {
    const start = parseHM(b.startTime);
    return start.h === hour;
  });
}

function blockStyle(apt) {
  const start = parseHM(apt.startTime);
  const end = parseHM(apt.endTime);
  const topOffset = (start.m / 60) * 100;
  const totalMin = (end.h - start.h) * 60 + (end.m - start.m);
  const heightPct = Math.max((totalMin / 60) * 100, 40);
  return { top: topOffset + '%', height: heightPct + '%', minHeight: '48px' };
}

function breakBlockStyle(brk) {
  const start = parseHM(brk.startTime);
  const end = parseHM(brk.endTime);
  const topOffset = (start.m / 60) * 100;
  const totalMin = (end.h - start.h) * 60 + (end.m - start.m);
  const heightPct = Math.max((totalMin / 60) * 100, 30);
  return { top: topOffset + '%', height: heightPct + '%', minHeight: '32px' };
}

function formatDate(dateStr) {
  const d = new Date(dateStr + "T00:00:00");
  return d.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
}

async function loadAll() {
  loading.value = true;
  try {
    const [sched, settings] = await Promise.all([
      scheduleService.show(),
      scheduleService.settings(),
    ]);
    schedule.value = sched;
    daysOff.value = settings.daysOff ?? settings.days_off ?? [];
    breaks.value = settings.breaks ?? [];
  } catch (e) {
    console.error("Failed to load planning", e);
  } finally {
    loading.value = false;
  }
}

async function addDayOff() {
  if (!newDayOff.date) return;
  try {
    await scheduleService.createDayOff(newDayOff);
    showAddDayOff.value = false;
    newDayOff.date = "";
    newDayOff.reason = "";
    const settings = await scheduleService.settings();
    daysOff.value = settings.daysOff ?? settings.days_off ?? [];
  } catch (e) {
    console.error("Failed to add day off", e);
  }
}

async function removeDayOff(id) {
  try {
    await scheduleService.deleteDayOff(id);
    daysOff.value = daysOff.value.filter((d) => d.id !== id);
  } catch (e) {
    console.error("Failed to delete day off", e);
  }
}

async function addBreak() {
  if (!newBreak.start_time || !newBreak.end_time) return;
  try {
    await scheduleService.createBreak(newBreak);
    showAddBreak.value = false;
    newBreak.day_of_week = null;
    newBreak.start_time = "";
    newBreak.end_time = "";
    newBreak.label = "";
    const settings = await scheduleService.settings();
    breaks.value = settings.breaks ?? [];
  } catch (e) {
    console.error("Failed to add break", e);
  }
}

async function removeBreak(id) {
  try {
    await scheduleService.deleteBreak(id);
    breaks.value = breaks.value.filter((b) => b.id !== id);
  } catch (e) {
    console.error("Failed to delete break", e);
  }
}

onMounted(loadAll);
</script>

<style scoped>
.planning-page {
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

.summary-bar {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
  padding: 1rem 1.5rem;
  background: var(--color-bg-white);
  border-radius: 14px;
  border: 1px solid var(--color-separator);
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-text-dark);
}

.summary-item i {
  color: var(--color-accent);
  font-size: 1rem;
}

.closed-label {
  color: var(--color-text-error);
}

.timeline {
  display: flex;
  flex-direction: column;
}

.timeline-row {
  display: flex;
  min-height: 72px;
  border-bottom: 1px solid var(--color-separator);
  position: relative;
}

.timeline-row:last-child {
  border-bottom: none;
}

.timeline-hour {
  width: 65px;
  padding: 0.5rem 0;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--color-text-subtle);
  flex-shrink: 0;
  text-align: right;
  padding-right: 1rem;
  padding-top: 0.2rem;
}

.timeline-slot {
  flex: 1;
  position: relative;
  min-height: 72px;
  border-left: 2px solid var(--color-separator);
  padding-left: 0.75rem;
}

.timeline-block {
  position: absolute;
  left: 0.75rem;
  right: 0.5rem;
  border-radius: 10px;
  padding: 0.5rem 0.75rem;
  overflow: hidden;
  z-index: 1;
}

.apt-block {
  background: var(--color-accent-a20);
  border-left: 4px solid var(--color-accent);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}

.block-content {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.block-time {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--color-accent);
}

.block-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--color-text-dark);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.block-client {
  font-size: 0.75rem;
  color: var(--color-text-subtle);
}

.block-price {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--color-accent);
  white-space: nowrap;
}

.walk-in-badge {
  font-size: 0.65rem;
  font-weight: 700;
  background: var(--color-warning);
  color: var(--color-white);
  padding: 0.1rem 0.4rem;
  border-radius: 6px;
  width: fit-content;
}

.break-block {
  background: var(--color-neutral-100);
  border-left: 4px solid var(--color-neutral-400);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.break-text {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-neutral-500);
}

.break-time-label {
  font-size: 0.7rem;
  color: var(--color-neutral-400);
}

.day-off-notice {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem 2rem;
  text-align: center;
}

.day-off-notice i {
  font-size: 2.5rem;
  color: var(--color-text-subtle);
}

.day-off-notice h2 {
  font-size: 1.2rem;
  color: var(--color-text-dark);
}

.day-off-notice p {
  font-size: 0.9rem;
  color: var(--color-text-subtle);
}

.section {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.75rem 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
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

.add-form {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.dialog-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-row label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-subtle);
}

.days-off-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 0.75rem;
}

.day-off-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: var(--color-bg);
  transition: box-shadow 0.2s;
}

.day-off-item:hover {
  box-shadow: 0 2px 8px var(--color-shadow-md);
}

.day-off-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.day-off-date {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text-dark);
  text-transform: capitalize;
}

.day-off-reason {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.breaks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
}

.break-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: var(--color-bg);
  transition: box-shadow 0.2s;
}

.break-card:hover {
  box-shadow: 0 2px 8px var(--color-shadow-md);
}

.break-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.break-day {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text-dark);
}

.break-time {
  font-size: 0.85rem;
  color: var(--color-text);
}

.break-name {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.empty-text {
  font-size: 0.85rem;
  color: var(--color-text-subtle);
  padding: 0.75rem 0;
}

.accent-button {
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
}

@media (max-width: 860px) {
  .planning-page {
    padding: 1.5rem 1.25rem;
  }

  .summary-bar {
    flex-direction: column;
    gap: 0.75rem;
  }

  .days-off-list,
  .breaks-grid {
    grid-template-columns: 1fr;
  }
}
</style>
