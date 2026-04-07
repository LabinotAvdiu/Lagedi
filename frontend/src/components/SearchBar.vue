<template>
  <div class="search-bar">
    <span class="search-input-wrapper">
      <i class="pi pi-map-marker search-icon" />
      <InputText
        v-model="city"
        :placeholder="t('home.searchPlaceholder')"
        class="search-input"
      />
    </span>
    <Button
      :label="t('home.searchButton')"
      icon="pi pi-search"
      class="search-button"
      @click="onSearch"
    />
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import InputText from "primevue/inputtext";
import Button from "primevue/button";

const { t } = useI18n();
const city = ref("");

const onSearch = () => {
  console.log("search:", city.value);
};
</script>

<style scoped>
.search-bar {
  display: flex;
  gap: 0;
  max-width: 560px;
  width: 100%;
  border-radius: 50px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.search-input-wrapper {
  display: flex;
  align-items: center;
  flex: 1;
  background: #ffffff;
  padding-left: 1.25rem;
}

.search-icon {
  color: #999;
  font-size: 1.1rem;
}

.search-input {
  flex: 1;
  border: none !important;
  box-shadow: none !important;
  padding: 1rem 0.75rem;
  font-size: 1rem;
  background: transparent;
  color: #222;
}

.search-input input {
  color: #222 !important;
}

.search-input::placeholder,
.search-input input::placeholder {
  color: #777 !important;
}

.search-button {
  border-radius: 0 !important;
  padding: 1rem 2rem !important;
  font-weight: 600;
  font-size: 1rem;
  background: #e94560 !important;
  border-color: #e94560 !important;
}

.search-button:hover {
  background: #d63851 !important;
  border-color: #d63851 !important;
}
</style>
