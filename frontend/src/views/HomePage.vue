<template>
  <div class="home-page">
    <section class="hero">
      <div class="hero-content">
        <h1 class="hero-title">{{ t("home.title") }}</h1>
        <p class="hero-subtitle">{{ t("home.subtitle") }}</p>
        <SearchBar />
      </div>
    </section>
  </div>
</template>

<script setup>
import { useI18n } from "vue-i18n";
import SearchBar from "../components/SearchBar.vue";

const { t } = useI18n();
</script>

<style scoped>
.home-page {
  display: flex;
  flex-direction: column;
}

.hero {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 2rem;
}

.hero-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  max-width: 700px;
  text-align: center;
}

.hero-title {
  font-size: 3rem;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.15;
  letter-spacing: -0.02em;
}

.hero-subtitle {
  font-size: 1.25rem;
  color: #b0bec5;
  line-height: 1.6;
  max-width: 550px;
}

@media (max-width: 768px) {
  .hero-title {
    font-size: 2rem;
  }

  .hero-subtitle {
    font-size: 1.05rem;
  }
}
</style>
