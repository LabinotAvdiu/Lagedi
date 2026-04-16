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
  box-shadow: 0 4px 20px var(--color-shadow-dark);
}

.search-input-wrapper {
  display: flex;
  align-items: center;
  flex: 1;
  background: var(--color-bg-white);
  padding-left: 1.25rem;
}

.search-icon {
  color: var(--color-text-icon);
  font-size: 1.1rem;
}

.search-input {
  flex: 1;
  border: none;
  box-shadow: none;
  padding: 1rem 0.75rem;
  font-size: 1rem;
  background: transparent;
  color: var(--color-text-dark);
}

.search-input input {
  color: var(--color-text-dark);
}

.search-input::placeholder,
.search-input input::placeholder {
  color: var(--color-text-placeholder);
}

.search-button {
  border-radius: 0;
  padding: 1rem 2rem;
  font-weight: 600;
  font-size: 1rem;
}
</style>
