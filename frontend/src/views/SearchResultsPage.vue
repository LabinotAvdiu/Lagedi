<template>
  <div class="results-page">
    <aside class="results-list">
      <header class="results-header">
        <h1 class="results-title">{{ t("search.title") }}</h1>
        <p class="results-subtitle">
          {{
            isLoading
              ? t("search.loading")
              : t("search.subtitle", { city: cityName, count: salons.length })
          }}
        </p>
      </header>

      <div v-if="isLoading" class="state-center">
        <i class="pi pi-spin pi-spinner state-spinner" />
      </div>

      <div v-else-if="errorMsg" class="state-center state-error">
        <i class="pi pi-exclamation-triangle" />
        <p>{{ errorMsg }}</p>
      </div>

      <div v-else-if="salons.length === 0 && cityName" class="state-center">
        <p>{{ t("search.noResults", { city: cityName }) }}</p>
      </div>

      <ul v-else class="salon-list">
        <li
          v-for="salon in salons"
          :key="salon.id"
          class="salon-card"
          :class="{ 'salon-card--active': activeSalonId === salon.id }"
          @mouseenter="activeSalonId = salon.id"
          @mouseleave="activeSalonId = null"
        >
          <img
            :src="salon.photoRef ? photoUrl(salon.photoRef) : FALLBACK_PHOTO"
            :alt="salon.name"
            class="salon-photo"
          />

          <div class="salon-info">
            <h2 class="salon-name">{{ salon.name }}</h2>

            <p class="salon-address">
              <i class="pi pi-map-marker" />
              {{ salon.address }}
            </p>

            <p v-if="salon.rating" class="salon-rating">
              <i class="pi pi-star-fill" />
              {{ salon.rating.toFixed(1) }}
              <span class="salon-reviews"
                >({{ salon.reviewCount }} {{ t("search.reviews") }})</span
              >
              <span v-if="salon.priceRange" class="salon-price"
                >· {{ salon.priceRange }}</span
              >
            </p>

            <span
              v-if="salon.openNow !== null"
              :class="[
                'open-badge',
                salon.openNow ? 'open-badge--open' : 'open-badge--closed',
              ]"
            >
              {{ salon.openNow ? t("search.openNow") : t("search.closedNow") }}
            </span>

            <div class="salon-actions">
              <a href="#" class="link-more">{{ t("search.moreInfo") }}</a>
              <button class="btn-book">{{ t("search.book") }}</button>
            </div>
          </div>
        </li>
      </ul>
    </aside>

    <div ref="mapContainer" class="results-map" />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { Loader } from "@googlemaps/js-api-loader";
import { salonService } from "../services/salonService.js";

const FALLBACK_PHOTO =
  "https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=400&h=200&fit=crop";

const loader = new Loader({
  apiKey: import.meta.env.VITE_GOOGLE_MAPS_KEY,
  version: "weekly",
});

const { t } = useI18n();
const route = useRoute();

const cityName = ref(route.query.city || "");
const salons = ref([]);
const isLoading = ref(false);
const errorMsg = ref("");
const activeSalonId = ref(null);

const mapContainer = ref(null);
let map = null;
let Marker = null;
let InfoWindow = null;
const markers = {};
let openInfoWindow = null;

function photoUrl(photoRef) {
  return salonService.photoUrl(photoRef);
}

function clearMarkers() {
  Object.values(markers).forEach(({ marker }) => marker.setMap(null));
  Object.keys(markers).forEach((k) => delete markers[k]);
  if (openInfoWindow) {
    openInfoWindow.close();
    openInfoWindow = null;
  }
}

async function fetchSalons(city) {
  if (!city) return;

  isLoading.value = true;
  errorMsg.value = "";
  salons.value = [];
  clearMarkers();

  try {
    const data = await salonService.search(city);
    salons.value = data.salons;

    if (map && data.salons.length > 0) {
      const first = data.salons[0];
      map.setCenter({ lat: first.lat, lng: first.lng });
      map.setZoom(14);

      data.salons.forEach((salon) => {
        const marker = new Marker({
          position: { lat: salon.lat, lng: salon.lng },
          map,
          title: salon.name,
        });
        const infoWindow = new InfoWindow({
          content: `<strong>${salon.name}</strong><br>${salon.address}`,
        });
        marker.addListener("click", () => {
          if (openInfoWindow) openInfoWindow.close();
          infoWindow.open(map, marker);
          openInfoWindow = infoWindow;
        });
        markers[salon.id] = { marker, infoWindow };
      });
    }
  } catch {
    errorMsg.value = t("search.error");
  } finally {
    isLoading.value = false;
  }
}

onMounted(async () => {
  const { Map, InfoWindow: IW } = await loader.importLibrary("maps");
  const { Marker: Mkr } = await loader.importLibrary("marker");
  Marker = Mkr;
  InfoWindow = IW;
  map = new Map(mapContainer.value, {
    center: { lat: 46.603354, lng: 1.888334 },
    zoom: 6,
  });
  fetchSalons(cityName.value);
});

watch(
  () => route.query.city,
  (newCity) => {
    cityName.value = newCity || "";
    fetchSalons(cityName.value);
  }
);

watch(activeSalonId, (newId, oldId) => {
  if (oldId && markers[oldId]) markers[oldId].infoWindow.close();
  if (newId && markers[newId]) {
    markers[newId].infoWindow.open(map, markers[newId].marker);
    openInfoWindow = markers[newId].infoWindow;
  }
});
</script>

<style scoped>
.results-page {
  display: flex;
  height: calc(100vh - 64px);
}

.results-list {
  width: 680px;
  flex-shrink: 0;
  overflow-y: auto;
  padding: 1.5rem 1.25rem;
  background: #fff;
  border-right: 1px solid #e5e7eb;
}

.results-header {
  margin-bottom: 1.25rem;
}

.results-title {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
}

.results-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

.state-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  color: #6b7280;
  font-size: 0.9rem;
  text-align: center;
}

.state-spinner {
  font-size: 2rem;
  color: #4f46e5;
}

.state-error {
  color: #dc2626;
}

.salon-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.salon-card {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  transition: box-shadow 0.2s;
  cursor: pointer;
}

.salon-card--active,
.salon-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  border-color: #d1d5db;
}

.salon-photo {
  width: 140px;
  height: 110px;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
}

.salon-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.salon-name {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
}

.salon-address {
  font-size: 0.8rem;
  color: #6b7280;
  display: flex;
  align-items: flex-start;
  gap: 0.3rem;
}

.salon-rating {
  font-size: 0.8rem;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.salon-rating .pi-star-fill {
  color: #f59e0b;
}

.salon-reviews {
  color: #9ca3af;
}

.salon-price {
  color: #9ca3af;
}

.open-badge {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  width: fit-content;
}

.open-badge--open {
  background: #dcfce7;
  color: #15803d;
}

.open-badge--closed {
  background: #fee2e2;
  color: #b91c1c;
}

.salon-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 0.5rem;
}

.link-more {
  font-size: 0.8rem;
  color: #4f46e5;
  text-decoration: underline;
}

.btn-book {
  padding: 0.5rem 1.25rem;
  background: #111827;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-book:hover {
  background: #1f2937;
}

.results-map {
  flex: 1;
  height: 100%;
}
</style>
