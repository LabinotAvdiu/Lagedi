<template>
  <div class="salon-detail-page">
    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else-if="salon">
      <header class="page-header">
        <button class="back-btn" @click="$router.back()">
          <i class="pi pi-arrow-left" />
        </button>
        <div>
          <h1 class="page-title">{{ salon.name }}</h1>
          <p class="page-subtitle">{{ salon.address }}<span v-if="salon.city"> · {{ salon.city }}</span></p>
        </div>
      </header>

      <section v-if="salon.photos?.length" class="gallery">
        <img
          v-for="(photo, i) in salon.photos.slice(0, 4)"
          :key="i"
          :src="photo"
          :alt="`${salon.name} photo ${i + 1}`"
          class="gallery-img"
          :class="{ 'gallery-main': i === 0 }"
        />
      </section>

      <div class="content-grid">
        <div class="col-main">
          <section v-if="salon.description" class="section">
            <h2 class="section-title">{{ t("detail.about") }}</h2>
            <p class="description">{{ salon.description }}</p>
          </section>

          <section class="section">
            <h2 class="section-title">{{ t("detail.services") }}</h2>
            <div v-for="cat in salon.categories" :key="cat.id" class="service-category">
              <h3 class="cat-name">{{ cat.name }}</h3>
              <div class="service-list">
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
                  <div class="service-price">{{ svc.price.toFixed(2) }} €</div>
                  <i v-if="selectedService?.id === svc.id" class="pi pi-check-circle svc-check" />
                </div>
              </div>
            </div>
          </section>

          <section v-if="salon.employees?.length" class="section">
            <h2 class="section-title">{{ t("detail.team") }}</h2>
            <div class="team-grid">
              <div
                v-for="emp in salon.employees"
                :key="emp.id"
                class="team-card"
                :class="{ selected: selectedEmployee?.id === emp.id }"
                @click="selectEmployee(emp)"
              >
                <div class="team-avatar">
                  <img v-if="emp.photoUrl" :src="emp.photoUrl" :alt="emp.name" />
                  <span v-else>{{ emp.name?.charAt(0) }}</span>
                </div>
                <span class="team-name">{{ emp.name }}</span>
                <i v-if="selectedEmployee?.id === emp.id" class="pi pi-check-circle emp-check" />
              </div>
            </div>
          </section>

          <section class="section">
            <h2 class="section-title">{{ t("detail.hours") }}</h2>
            <div class="hours-list">
              <div v-for="h in salon.openingHours" :key="h.dayOfWeek" class="hour-row">
                <span class="day-name">{{ dayNames[h.dayOfWeek] }}</span>
                <span v-if="h.isClosed" class="closed-badge">{{ t("detail.closed") }}</span>
                <span v-else class="hour-time">{{ fmt(h.openTime) }} – {{ fmt(h.closeTime) }}</span>
              </div>
            </div>
          </section>
        </div>

        <aside class="col-sidebar">
          <div class="booking-card">
            <h2 class="booking-title">{{ t("detail.bookNow") }}</h2>

            <div v-if="selectedService" class="booking-recap">
              <div class="recap-row">
                <span class="recap-label">{{ t("detail.service") }}</span>
                <span class="recap-value">{{ selectedService.name }}</span>
              </div>
              <div class="recap-row">
                <span class="recap-label">{{ t("detail.duration") }}</span>
                <span class="recap-value">{{ selectedService.durationMinutes }} min</span>
              </div>
              <div class="recap-row">
                <span class="recap-label">{{ t("detail.price") }}</span>
                <span class="recap-value recap-price">{{ selectedService.price.toFixed(2) }} €</span>
              </div>
              <div v-if="selectedEmployee" class="recap-row">
                <span class="recap-label">{{ t("detail.with") }}</span>
                <span class="recap-value">{{ selectedEmployee.name }}</span>
              </div>
            </div>
            <p v-else class="booking-hint">{{ t("detail.selectServiceFirst") }}</p>

            <Button
              :label="t('detail.chooseSlot')"
              class="book-btn"
              :disabled="!selectedService"
              @click="goToBooking"
            />
          </div>

          <div class="info-card">
            <div class="info-row" v-if="salon.phone">
              <i class="pi pi-phone" />
              <span>{{ salon.phone }}</span>
            </div>
            <div class="info-row" v-if="salon.email">
              <i class="pi pi-envelope" />
              <span>{{ salon.email }}</span>
            </div>
            <div class="info-row">
              <i class="pi pi-star-fill star-icon" />
              <span>{{ salon.rating.toFixed(1) }} <span class="muted">({{ salon.reviewCount }} {{ t("detail.reviews") }})</span></span>
            </div>
          </div>
        </aside>
      </div>
    </template>

    <div v-else class="empty-state">
      <i class="pi pi-exclamation-circle" />
      <p>{{ t("detail.notFound") }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import { companyService } from "../../services/companyService";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const loading = ref(true);
const salon = ref(null);
const selectedService = ref(null);
const selectedEmployee = ref(null);

const dayNames = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];

function fmt(time) {
  return time ? time.substring(0, 5) : "";
}

function selectService(svc) {
  selectedService.value = selectedService.value?.id === svc.id ? null : svc;
}

function selectEmployee(emp) {
  selectedEmployee.value = selectedEmployee.value?.id === emp.id ? null : emp;
}

function goToBooking() {
  router.push({
    name: "dashboard-booking",
    params: { id: route.params.id },
    query: {
      service: selectedService.value.id,
      ...(selectedEmployee.value ? { employee: selectedEmployee.value.id } : {}),
    },
  });
}

async function loadSalon() {
  loading.value = true;
  try {
    salon.value = await companyService.show(route.params.id);
  } catch (e) {
    console.error("Failed to load salon", e);
  } finally {
    loading.value = false;
  }
}

onMounted(loadSalon);
</script>

<style scoped>
.salon-detail-page {
  padding: 2rem 2.5rem;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 4rem;
  font-size: 2rem;
  color: var(--color-accent);
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

.gallery {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  grid-template-rows: 200px 200px;
  gap: 0.5rem;
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 2rem;
}

.gallery-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gallery-main {
  grid-row: 1 / -1;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 2rem;
  align-items: start;
}

.section {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.5rem 1.75rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.section-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-text-dark);
  margin-bottom: 1rem;
}

.description {
  font-size: 0.9rem;
  color: var(--color-text);
  line-height: 1.6;
}

.service-category {
  margin-bottom: 1.25rem;
}

.service-category:last-child {
  margin-bottom: 0;
}

.cat-name {
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
  margin-bottom: 0.5rem;
}

.service-list {
  display: flex;
  flex-direction: column;
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
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--color-accent);
}

.svc-check {
  color: var(--color-accent);
  font-size: 1.1rem;
}

/* ── Team ── */
.team-grid {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.team-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.15s;
  position: relative;
  min-width: 90px;
}

.team-card:hover {
  background: var(--color-bg);
}

.team-card.selected {
  border-color: var(--color-accent);
  background: var(--color-accent-a08);
}

.team-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--color-accent-a20);
  color: var(--color-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
  overflow: hidden;
}

.team-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.team-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-dark);
  text-align: center;
}

.emp-check {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  color: var(--color-accent);
  font-size: 0.85rem;
}

.hours-list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.hour-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.4rem 0;
}

.day-name {
  width: 100px;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text-dark);
}

.hour-time {
  font-size: 0.9rem;
  color: var(--color-text);
}

.closed-badge {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-error);
}

.booking-card {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.5rem;
  box-shadow: 0 2px 12px var(--color-shadow-md);
  margin-bottom: 1rem;
  position: sticky;
  top: 2rem;
}

.booking-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-text-dark);
  margin-bottom: 1rem;
}

.booking-recap {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
}

.recap-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.recap-label {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.recap-value {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-text-dark);
}

.recap-price {
  color: var(--color-accent);
  font-weight: 700;
}

.booking-hint {
  font-size: 0.85rem;
  color: var(--color-text-subtle);
  margin-bottom: 1rem;
  text-align: center;
  padding: 1rem 0;
}

.book-btn {
  width: 100%;
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  font-weight: 700;
  font-size: 0.95rem;
}

.info-card {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.85rem;
  color: var(--color-text);
}

.info-row i {
  color: var(--color-text-subtle);
  width: 18px;
  text-align: center;
}

.star-icon {
  color: var(--color-warning) !important;
}

.muted {
  color: var(--color-text-subtle);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 5rem 2rem;
  text-align: center;
}

.empty-state i {
  font-size: 3rem;
  color: var(--color-text-subtle);
}

@media (max-width: 1100px) {
  .content-grid {
    grid-template-columns: 1fr;
  }

  .booking-card {
    position: static;
  }
}

@media (max-width: 860px) {
  .salon-detail-page {
    padding: 1.5rem 1.25rem;
  }

  .gallery {
    grid-template-columns: 1fr;
    grid-template-rows: 250px;
  }

  .gallery-main {
    grid-row: auto;
  }

  .gallery-img:not(.gallery-main) {
    display: none;
  }
}
</style>
