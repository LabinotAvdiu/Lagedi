<template>
  <div class="search-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t("dashboard.tabs.search") }}</h1>
        <p class="page-subtitle">{{ t("dashboard.search.subtitle") }}</p>
      </div>
    </header>

    <div class="search-filters">
      <div class="filter-group">
        <label class="filter-label">{{ t("dashboard.search.gender") }}</label>
        <Select
          v-model="filters.gender"
          :options="genderOptions"
          option-label="label"
          option-value="value"
          class="filter-select"
        />
      </div>
      <div class="filter-group filter-group--grow">
        <label class="filter-label">{{ t("dashboard.search.cityOrSalon") }}</label>
        <InputText
          v-model="filters.search"
          :placeholder="t('dashboard.search.cityOrSalon')"
          class="filter-input"
        />
      </div>
      <div class="filter-group">
        <label class="filter-label">{{ t("dashboard.search.when") }}</label>
        <InputText
          v-model="filters.date"
          type="date"
          class="filter-input"
        />
      </div>
      <div class="filter-group filter-group--action">
        <Button
          icon="pi pi-search"
          :label="t('dashboard.search.searchBtn')"
          class="filter-button"
          @click="loadCompanies"
        />
      </div>
    </div>

    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else>
      <p v-if="companies.length" class="results-count">
        {{ companies.length }} {{ t("dashboard.search.salonsNear") }}
      </p>
      <p v-else class="results-count">
        {{ t("dashboard.search.noResults") }}
      </p>

      <div class="salon-grid">
        <article v-for="salon in companies" :key="salon.id" class="salon-card" @click="$router.push(`/dashboard/salon/${salon.id}`)">
          <div class="salon-cover">
            <img
              :src="salon.photoUrl || '/placeholder-salon.jpg'"
              :alt="salon.name"
              class="salon-photo"
            />
            <span v-if="salon.rating" class="badge badge-rating">
              <i class="pi pi-star-fill" /> {{ salon.rating }}
            </span>
            <span v-if="salon.reviewCount" class="badge badge-reviews">
              {{ salon.reviewCount }} {{ t("dashboard.search.reviews") }}
            </span>
          </div>
          <div class="salon-body">
            <h3 class="salon-name">{{ salon.name }}</h3>
            <p class="salon-address">{{ salon.address }}</p>

            <div v-if="salon.availability?.length" class="availability-slots">
              <span
                v-for="day in salon.availability"
                :key="day.date"
                class="slot"
                :class="{ active: day.morning || day.afternoon }"
              >{{ formatDay(day.date) }}</span>
            </div>
          </div>
        </article>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Button from "primevue/button";
import { companyService } from "../../services/companyService";

const { t } = useI18n();

const loading = ref(false);
const companies = ref([]);

const filters = reactive({
  gender: "both",
  search: "",
  date: "",
});

const genderOptions = [
  { label: t("dashboard.search.genderBoth"), value: "both" },
  { label: t("dashboard.search.genderMen"), value: "men" },
  { label: t("dashboard.search.genderWomen"), value: "women" },
];

const dayShort = ["dim.", "lun.", "mar.", "mer.", "jeu.", "ven.", "sam."];
function formatDay(dateStr) {
  const d = new Date(dateStr + "T00:00:00");
  return `${dayShort[d.getDay()]} ${d.getDate()}`;
}

async function loadCompanies() {
  loading.value = true;
  try {
    const params = {};
    if (filters.gender && filters.gender !== "both") params.gender = filters.gender;
    if (filters.search) params.search = filters.search;
    if (filters.date) params.date = filters.date;
    const result = await companyService.list(params);
    companies.value = Array.isArray(result) ? result : result.data ?? [];
  } catch (e) {
    console.error("Failed to load companies", e);
    companies.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(loadCompanies);
</script>

<style scoped>
.search-page {
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

.search-filters {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  padding: 1.25rem 1.5rem;
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  margin-bottom: 2rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.filter-group--grow {
  flex: 1;
}

.filter-group--action {
  align-self: flex-end;
}

.filter-label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
}

.filter-select {
  min-width: 150px;
}

.filter-input {
  min-width: 160px;
}

.filter-button {
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  font-weight: 600;
}

.results-count {
  font-size: 0.9rem;
  color: var(--color-text-subtle);
  margin-bottom: 1.25rem;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 4rem;
  font-size: 2rem;
  color: var(--color-accent);
}

.salon-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.salon-card {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow 0.3s, transform 0.3s;
}

.salon-card:hover {
  box-shadow: 0 12px 32px var(--color-shadow-lg);
  transform: translateY(-4px);
}

.salon-cover {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: var(--color-separator);
}

.salon-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.4s;
}

.salon-card:hover .salon-photo {
  transform: scale(1.04);
}

.badge {
  position: absolute;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.3rem 0.65rem;
  border-radius: 8px;
  backdrop-filter: blur(6px);
}

.badge-rating {
  top: 0.75rem;
  left: 0.75rem;
  background: var(--color-overlay-dark);
  color: var(--color-white);
  display: flex;
  align-items: center;
  gap: 4px;
}

.badge-rating i {
  color: var(--color-warning);
  font-size: 0.7rem;
}

.badge-reviews {
  bottom: 0.75rem;
  left: 0.75rem;
  background: var(--color-overlay-mid);
  color: var(--color-white);
}

.salon-body {
  padding: 1rem 1.25rem 1.25rem;
}

.salon-name {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text-dark);
  margin-bottom: 0.2rem;
}

.salon-address {
  font-size: 0.85rem;
  color: var(--color-text-subtle);
  margin-bottom: 0.75rem;
}

.availability-slots {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.slot {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.25rem 0.55rem;
  border-radius: 6px;
  background: var(--color-bg);
  color: var(--color-text-subtle);
  border: 1px solid var(--color-separator);
  white-space: nowrap;
}

.slot.active {
  background: var(--color-accent-a12);
  color: var(--color-accent);
  border-color: var(--color-accent-a30);
}

@media (max-width: 1200px) {
  .salon-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  .search-page {
    padding: 1.5rem;
  }

  .search-filters {
    flex-direction: column;
    align-items: stretch;
  }

  .filter-group--action {
    align-self: stretch;
  }

  .filter-button {
    width: 100%;
  }

  .salon-grid {
    grid-template-columns: 1fr;
  }
}
</style>
