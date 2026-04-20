<template>
  <div class="booking-page">
    <header class="page-header">
      <button class="back-btn" @click="goBack">
        <i class="pi pi-arrow-left" />
      </button>
      <div>
        <h1 class="page-title">{{ t("booking.title") }}</h1>
        <p class="page-subtitle" v-if="salon">{{ salon.name }}</p>
      </div>
    </header>

    <div v-if="initialLoading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else-if="salon">
      <div class="stepper">
        <div
          v-for="(step, i) in steps"
          :key="i"
          class="step"
          :class="{ active: currentStep === i, done: currentStep > i }"
          @click="goToStep(i)"
        >
          <span class="step-num">{{ currentStep > i ? '✓' : i + 1 }}</span>
          <span class="step-label">{{ step }}</span>
        </div>
      </div>

      <section v-if="currentStep === 0" class="step-content">
        <h2 class="step-title">{{ t("booking.chooseService") }}</h2>
        <div v-for="cat in salon.categories" :key="cat.id" class="service-category">
          <h3 class="cat-name">{{ cat.name }}</h3>
          <div
            v-for="svc in cat.services"
            :key="svc.id"
            class="service-row"
            :class="{ selected: selectedService?.id === svc.id }"
            @click="selectService(svc)"
          >
            <div class="service-info">
              <span class="svc-name">{{ svc.name }}</span>
              <span class="svc-meta">{{ svc.durationMinutes }} min</span>
            </div>
            <span class="service-price">{{ svc.price.toFixed(2) }} €</span>
            <i v-if="selectedService?.id === svc.id" class="pi pi-check-circle svc-check" />
          </div>
        </div>
        <div class="step-actions">
          <Button :label="t('booking.next')" class="accent-btn" :disabled="!selectedService" @click="nextStep" />
        </div>
      </section>

      <section v-if="currentStep === 1" class="step-content">
        <h2 class="step-title">{{ t("booking.chooseEmployee") }}</h2>
        <p class="step-hint">{{ t("booking.employeeOptional") }}</p>
        <div class="employee-grid">
          <div
            class="employee-card"
            :class="{ selected: selectedEmployee === null }"
            @click="selectedEmployee = null"
          >
            <div class="emp-avatar any"><i class="pi pi-users" /></div>
            <span class="emp-name">{{ t("booking.anyEmployee") }}</span>
          </div>
          <div
            v-for="emp in salon.employees"
            :key="emp.id"
            class="employee-card"
            :class="{ selected: selectedEmployee?.id === emp.id }"
            @click="selectedEmployee = emp"
          >
            <div class="emp-avatar">
              <img v-if="emp.photoUrl" :src="emp.photoUrl" :alt="emp.name" />
              <span v-else>{{ emp.name?.charAt(0) }}</span>
            </div>
            <span class="emp-name">{{ emp.name }}</span>
          </div>
        </div>
        <div class="step-actions">
          <Button :label="t('booking.back')" severity="secondary" outlined @click="prevStep" />
          <Button :label="t('booking.next')" class="accent-btn" @click="nextStep" />
        </div>
      </section>

      <section v-if="currentStep === 2" class="step-content">
        <h2 class="step-title">{{ t("booking.chooseDate") }}</h2>
        <div v-if="loadingAvailability" class="loading-small"><i class="pi pi-spin pi-spinner" /></div>
        <div v-else class="date-grid">
          <div
            v-for="day in availability"
            :key="day.date"
            class="date-card"
            :class="{
              selected: selectedDate === day.date,
              unavailable: day.status !== 'available',
            }"
            @click="day.status === 'available' && selectDate(day.date)"
          >
            <span class="date-day-name">{{ day.dayName }}</span>
            <span class="date-num">{{ formatDateShort(day.date) }}</span>
            <span v-if="day.status === 'available'" class="date-slots">{{ day.slotsCount }} {{ t("booking.slots") }}</span>
            <span v-else class="date-status-label">{{ dayStatusLabel(day.status) }}</span>
          </div>
        </div>
        <div class="step-actions">
          <Button :label="t('booking.back')" severity="secondary" outlined @click="prevStep" />
          <Button :label="t('booking.next')" class="accent-btn" :disabled="!selectedDate" @click="nextStep" />
        </div>
      </section>

      <section v-if="currentStep === 3" class="step-content">
        <h2 class="step-title">{{ t("booking.chooseTime") }}</h2>
        <div v-if="loadingSlots" class="loading-small"><i class="pi pi-spin pi-spinner" /></div>
        <div v-else-if="slots.length" class="time-grid">
          <button
            v-for="slot in slots"
            :key="slot.dateTime"
            class="time-btn"
            :class="{ selected: selectedSlot?.dateTime === slot.dateTime }"
            @click="selectedSlot = slot"
          >
            {{ formatSlotTime(slot.dateTime) }}
          </button>
        </div>
        <p v-else class="no-slots">{{ t("booking.noSlots") }}</p>
        <div class="step-actions">
          <Button :label="t('booking.back')" severity="secondary" outlined @click="prevStep" />
          <Button :label="t('booking.next')" class="accent-btn" :disabled="!selectedSlot" @click="nextStep" />
        </div>
      </section>

      <section v-if="currentStep === 4" class="step-content">
        <h2 class="step-title">{{ t("booking.confirm") }}</h2>
        <div class="recap-card">
          <div class="recap-row">
            <span class="recap-label">{{ t("booking.salon") }}</span>
            <span class="recap-value">{{ salon.name }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">{{ t("booking.service") }}</span>
            <span class="recap-value">{{ selectedService.name }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">{{ t("booking.employee") }}</span>
            <span class="recap-value">{{ selectedEmployee?.name || t("booking.anyEmployee") }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">{{ t("booking.dateTime") }}</span>
            <span class="recap-value">{{ formatRecapDate(selectedSlot.dateTime) }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">{{ t("booking.duration") }}</span>
            <span class="recap-value">{{ selectedService.durationMinutes }} min</span>
          </div>
          <div class="recap-row total">
            <span class="recap-label">{{ t("booking.price") }}</span>
            <span class="recap-value recap-price">{{ selectedService.price.toFixed(2) }} €</span>
          </div>
        </div>
        <div class="step-actions">
          <Button :label="t('booking.back')" severity="secondary" outlined @click="prevStep" />
          <Button
            :label="t('booking.confirmBooking')"
            class="accent-btn"
            :loading="submitting"
            @click="submitBooking"
          />
        </div>
      </section>

      <section v-if="currentStep === 5" class="step-content success-content">
        <i class="pi pi-check-circle success-icon" />
        <h2 class="success-title">{{ t("booking.success") }}</h2>
        <p class="success-text">{{ t("booking.successMessage") }}</p>
        <div class="step-actions">
          <Button :label="t('booking.viewAppointments')" class="accent-btn" @click="$router.push({ name: 'dashboard-appointments' })" />
          <Button :label="t('booking.backToSalon')" severity="secondary" outlined @click="$router.push({ name: 'dashboard-salon-detail', params: { id: route.params.id } })" />
        </div>
      </section>

      <div v-if="errorMessage" class="error-banner">
        <i class="pi pi-exclamation-triangle" />
        {{ errorMessage }}
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import { companyService } from "../../services/companyService";
import { bookingService } from "../../services/bookingService";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const steps = computed(() => [
  t("booking.stepService"),
  t("booking.stepEmployee"),
  t("booking.stepDate"),
  t("booking.stepTime"),
  t("booking.stepConfirm"),
]);

const initialLoading = ref(true);
const loadingAvailability = ref(false);
const loadingSlots = ref(false);
const submitting = ref(false);
const errorMessage = ref("");

const salon = ref(null);
const currentStep = ref(0);
const selectedService = ref(null);
const selectedEmployee = ref(null);
const selectedDate = ref(null);
const selectedSlot = ref(null);
const availability = ref([]);
const slots = ref([]);

function applyQueryPreselection() {
  const qService = route.query.service;
  const qEmployee = route.query.employee;
  if (qService && salon.value) {
    for (const cat of salon.value.categories || []) {
      const found = cat.services?.find((s) => String(s.id) === String(qService));
      if (found) {
        selectedService.value = found;
        currentStep.value = 1;
        break;
      }
    }
  }
  if (qEmployee && salon.value) {
    const found = salon.value.employees?.find((e) => String(e.id) === String(qEmployee));
    if (found) selectedEmployee.value = found;
  }
}

function goBack() {
  if (currentStep.value > 0) {
    prevStep();
  } else {
    router.back();
  }
}

function goToStep(i) {
  if (i < currentStep.value) currentStep.value = i;
}

function selectService(svc) {
  selectedService.value = svc;
}

function selectDate(date) {
  selectedDate.value = date;
  selectedSlot.value = null;
}

function prevStep() {
  currentStep.value = Math.max(0, currentStep.value - 1);
}

async function nextStep() {
  errorMessage.value = "";
  if (currentStep.value === 1) {
    await loadAvailability();
  }
  if (currentStep.value === 2) {
    await loadSlots();
  }
  currentStep.value++;
}

async function loadAvailability() {
  loadingAvailability.value = true;
  try {
    const params = { service_id: selectedService.value.id };
    if (selectedEmployee.value) params.employee_id = selectedEmployee.value.id;
    const result = await companyService.availability(route.params.id, params);
    availability.value = Array.isArray(result) ? result : result.data ?? [];
  } catch (e) {
    console.error("Failed to load availability", e);
  } finally {
    loadingAvailability.value = false;
  }
}

async function loadSlots() {
  loadingSlots.value = true;
  try {
    const params = {
      date: selectedDate.value,
      service_id: selectedService.value.id,
    };
    if (selectedEmployee.value) params.employee_id = selectedEmployee.value.id;
    const result = await companyService.slots(route.params.id, params);
    slots.value = Array.isArray(result) ? result : result.data ?? [];
  } catch (e) {
    console.error("Failed to load slots", e);
  } finally {
    loadingSlots.value = false;
  }
}

async function submitBooking() {
  submitting.value = true;
  errorMessage.value = "";
  try {
    await bookingService.create({
      company_id: Number(route.params.id),
      service_id: selectedService.value.id,
      employee_id: selectedEmployee.value?.id || null,
      date_time: selectedSlot.value.dateTime,
    });
    currentStep.value = 5;
  } catch (e) {
    errorMessage.value = e?.message || t("booking.error");
    console.error("Booking failed", e);
  } finally {
    submitting.value = false;
  }
}

function formatDateShort(dateStr) {
  const d = new Date(dateStr + "T00:00:00");
  return d.toLocaleDateString("fr-FR", { day: "numeric", month: "short" });
}

function formatSlotTime(dt) {
  return dt.substring(11, 16);
}

function formatRecapDate(dt) {
  const d = new Date(dt);
  return d.toLocaleDateString("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
  }) + " à " + dt.substring(11, 16);
}

function dayStatusLabel(status) {
  const map = { closed: "Fermé", full: "Complet", day_off: "Congé", not_working: "Repos" };
  return map[status] || status;
}

async function loadSalon() {
  initialLoading.value = true;
  try {
    salon.value = await companyService.show(route.params.id);
    applyQueryPreselection();
  } catch (e) {
    console.error("Failed to load salon", e);
  } finally {
    initialLoading.value = false;
  }
}

onMounted(loadSalon);
</script>

<style scoped>
.booking-page {
  padding: 2rem 2.5rem;
  max-width: 900px;
}

.loading,
.loading-small {
  display: flex;
  justify-content: center;
  padding: 3rem;
  font-size: 2rem;
  color: var(--color-accent);
}

.loading-small {
  padding: 2rem;
  font-size: 1.5rem;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: 1px solid var(--color-separator);
  background: var(--color-bg-white);
  color: var(--color-text-dark);
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.back-btn:hover {
  background: var(--color-bg);
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
  margin-top: 0.15rem;
}

.stepper {
  display: flex;
  gap: 0.25rem;
  margin-bottom: 2rem;
  background: var(--color-bg-white);
  border-radius: 14px;
  border: 1px solid var(--color-separator);
  padding: 0.65rem;
  overflow-x: auto;
}

.step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
  flex: 1;
  justify-content: center;
}

.step.active {
  background: var(--color-accent);
  color: var(--color-primary);
}

.step.done {
  color: var(--color-accent);
}

.step-num {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  background: var(--color-bg);
  color: var(--color-text-subtle);
  flex-shrink: 0;
}

.step.active .step-num {
  background: var(--color-primary);
  color: var(--color-accent);
}

.step.done .step-num {
  background: var(--color-accent-a20);
  color: var(--color-accent);
}

.step-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-subtle);
}

.step.active .step-label {
  color: var(--color-primary);
}

.step.done .step-label {
  color: var(--color-accent);
}

.step-content {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.75rem 2rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.step-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-text-dark);
  margin-bottom: 1.25rem;
}

.step-hint {
  font-size: 0.85rem;
  color: var(--color-text-subtle);
  margin-bottom: 1rem;
}

.step-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--color-separator);
}

.service-category {
  margin-bottom: 1.25rem;
}

.cat-name {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
  margin-bottom: 0.5rem;
}

.service-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 0.85rem;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  border: 2px solid transparent;
}

.service-row:hover {
  background: var(--color-bg);
}

.service-row.selected {
  border-color: var(--color-accent);
  background: var(--color-accent-a08);
}

.service-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.svc-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-dark);
}

.svc-meta {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.service-price {
  font-weight: 700;
  color: var(--color-accent);
}

.svc-check {
  color: var(--color-accent);
  font-size: 1.1rem;
}

.employee-grid {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.employee-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.25rem;
  border-radius: 12px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.15s;
  min-width: 100px;
}

.employee-card:hover {
  background: var(--color-bg);
}

.employee-card.selected {
  border-color: var(--color-accent);
  background: var(--color-accent-a08);
}

.emp-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--color-accent-a20);
  color: var(--color-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
  overflow: hidden;
}

.emp-avatar.any {
  background: var(--color-bg);
  color: var(--color-text-subtle);
}

.emp-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.emp-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-text-dark);
  text-align: center;
}

.date-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 0.6rem;
}

.date-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
  padding: 0.85rem 0.5rem;
  border-radius: 12px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.15s;
  text-align: center;
}

.date-card:hover:not(.unavailable) {
  background: var(--color-bg);
}

.date-card.selected {
  border-color: var(--color-accent);
  background: var(--color-accent-a08);
}

.date-card.unavailable {
  opacity: 0.4;
  cursor: not-allowed;
}

.date-day-name {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
  color: var(--color-text-subtle);
}

.date-num {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text-dark);
}

.date-slots {
  font-size: 0.7rem;
  color: var(--color-accent);
  font-weight: 600;
}

.date-status-label {
  font-size: 0.7rem;
  color: var(--color-text-subtle);
}

.time-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 0.5rem;
}

.time-btn {
  padding: 0.6rem;
  border-radius: 10px;
  border: 2px solid var(--color-separator);
  background: var(--color-bg-white);
  color: var(--color-text-dark);
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.15s;
  text-align: center;
}

.time-btn:hover {
  border-color: var(--color-accent);
  background: var(--color-bg);
}

.time-btn.selected {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-primary);
}

.no-slots {
  font-size: 0.9rem;
  color: var(--color-text-subtle);
  text-align: center;
  padding: 2rem;
}

.recap-card {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.recap-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
}

.recap-row.total {
  border-top: 2px solid var(--color-separator);
  padding-top: 0.85rem;
  margin-top: 0.25rem;
}

.recap-label {
  font-size: 0.85rem;
  color: var(--color-text-subtle);
}

.recap-value {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-dark);
}

.recap-price {
  color: var(--color-accent);
  font-weight: 800;
  font-size: 1.1rem;
}

.success-content {
  text-align: center;
  padding: 3rem 2rem;
}

.success-icon {
  font-size: 3.5rem;
  color: var(--color-success-bright);
  margin-bottom: 1rem;
}

.success-title {
  font-size: 1.4rem;
  color: var(--color-text-dark);
  margin-bottom: 0.5rem;
}

.success-text {
  font-size: 0.95rem;
  color: var(--color-text-subtle);
  margin-bottom: 0;
}

.success-content .step-actions {
  justify-content: center;
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  color: var(--color-text-error);
  padding: 0.85rem 1.25rem;
  border-radius: 12px;
  margin-top: 1rem;
  font-size: 0.9rem;
  font-weight: 500;
}

.accent-btn {
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  font-weight: 700;
}

@media (max-width: 860px) {
  .booking-page {
    padding: 1.5rem 1.25rem;
  }

  .stepper {
    padding: 0.5rem;
    gap: 0.15rem;
  }

  .step-label {
    display: none;
  }

  .date-grid {
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  }
}
</style>
