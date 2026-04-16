<template>
  <div class="home-page">
    <section class="hero">
      <div class="hero-overlay" />
      <div class="hero-content">
        <span class="hero-badge">
          <i class="pi pi-star-fill" />
          {{ t("home.title") }}
        </span>
        <h1 class="hero-title">{{ t("home.subtitle") }}</h1>
        <p class="hero-desc">{{ t("home.heroDesc") }}</p>
        <SearchBar />
        <div class="hero-stats">
          <div v-for="stat in stats" :key="stat.key" class="hero-stat">
            <span class="hero-stat-number">{{ stat.value }}</span>
            <span class="hero-stat-label">{{
              t(`home.stats.${stat.key}`)
            }}</span>
          </div>
        </div>
      </div>
    </section>

    <section class="features">
      <span class="section-badge">{{ t("home.features.sectionTitle") }}</span>
      <h2 class="section-title section-title--light">
        {{ t("home.features.heading") }}
      </h2>
      <div class="features-grid">
        <article
          v-for="feature in features"
          :key="feature.key"
          class="feature-card"
        >
          <div class="feature-icon">
            <i :class="feature.icon" />
          </div>
          <h3>{{ t(`home.features.${feature.key}.title`) }}</h3>
          <p>{{ t(`home.features.${feature.key}.description`) }}</p>
        </article>
      </div>
    </section>

    <section class="how-it-works">
      <span class="section-badge section-badge--dark">
        {{ t("home.howItWorks.sectionTitle") }}
      </span>
      <h2 class="section-title">{{ t("home.howItWorks.heading") }}</h2>
      <div class="steps">
        <div v-for="(step, i) in steps" :key="step" class="step">
          <div v-if="i < steps.length - 1" class="step-connector" />
          <span class="step-number">{{ i + 1 }}</span>
          <h3>{{ t(`home.howItWorks.step${i + 1}.title`) }}</h3>
          <p>{{ t(`home.howItWorks.step${i + 1}.description`) }}</p>
        </div>
      </div>
    </section>

    <section class="cta">
      <div class="cta-glow" />
      <div class="cta-content">
        <h2>{{ t("home.cta.title") }}</h2>
        <p>{{ t("home.cta.subtitle") }}</p>
        <Button
          :label="t('home.cta.button')"
          icon="pi pi-arrow-right"
          icon-pos="right"
          size="large"
          class="cta-button"
          @click="$router.push('/inscription')"
        />
      </div>
    </section>
  </div>
</template>

<script setup>
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import SearchBar from "../components/SearchBar.vue";

const { t } = useI18n();

const features = [
  { key: "booking", icon: "pi pi-calendar-clock" },
  { key: "barbers", icon: "pi pi-verified" },
  { key: "available", icon: "pi pi-clock" },
];

const steps = ["search", "compare", "book"];

const stats = [
  { key: "barbers", value: "500+" },
  { key: "bookings", value: "10K+" },
  { key: "cities", value: "50+" },
];
</script>

<style scoped>
.hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 120px);
  background: url("https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&w=1920&q=80")
    center / cover no-repeat;
  padding: 4rem 2rem;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    var(--color-overlay-75) 0%,
    var(--color-overlay-60) 50%,
    var(--color-overlay-95) 100%
  );
}

.hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  max-width: 750px;
  text-align: center;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--color-accent-a12);
  color: var(--color-accent);
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.5rem 1.25rem;
  border-radius: 50px;
  border: 1px solid var(--color-accent-a20);
  letter-spacing: 0.02em;
}

.hero-title {
  font-size: 3.5rem;
  font-weight: 800;
  color: var(--color-white);
  line-height: 1.1;
  letter-spacing: -0.03em;
}

.hero-desc {
  font-size: 1.15rem;
  color: var(--color-white-a75);
  line-height: 1.6;
  max-width: 560px;
}

.hero-stats {
  display: flex;
  gap: 3rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--color-white-a08);
}

.hero-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}

.hero-stat-number {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--color-white);
}

.hero-stat-label {
  font-size: 0.8rem;
  color: var(--color-white-a60);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.section-badge {
  display: inline-block;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-accent);
  margin-bottom: 0.75rem;
}

.section-badge--dark {
  color: var(--color-accent);
}

.section-title {
  font-size: 2.25rem;
  font-weight: 700;
  color: var(--color-black);
  text-align: center;
  margin-bottom: 3.5rem;
  letter-spacing: -0.02em;
}

.section-title--light {
  color: var(--color-white);
}

.features {
  padding: 6rem 2rem;
  background: var(--color-bg-dark);
  text-align: center;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  max-width: 1040px;
  margin: 0 auto;
}

.feature-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 2.5rem 2rem;
  border-radius: 20px;
  background: var(--color-bg-dark-light);
  border: 1px solid var(--color-accent-a35);
  box-shadow: 0 0 20px var(--color-accent-a06);
  transition:
    border-color 0.3s,
    transform 0.3s,
    box-shadow 0.3s;
}

.feature-card:hover {
  transform: translateY(-6px);
  border-color: var(--color-accent);
  box-shadow: 0 8px 32px var(--color-accent-a20);
}

.feature-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--color-accent-a12);
  border: 1px solid var(--color-accent-a30);
  color: var(--color-accent);
  font-size: 1.6rem;
  margin-bottom: 1.5rem;
}

.feature-card h3 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-white);
  margin-bottom: 0.75rem;
}

.feature-card p {
  font-size: 0.95rem;
  color: var(--color-text-secondary);
  line-height: 1.65;
}

.how-it-works {
  padding: 6rem 2rem;
  background: var(--color-white);
  text-align: center;
  color: var(--color-black);
}

.steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  max-width: 920px;
  margin: 0 auto;
}

.step {
  position: relative;
  text-align: center;
  padding: 0 1rem;
}

.step-connector {
  display: none;
}

.step-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(
    135deg,
    var(--color-accent) 0%,
    var(--color-accent-dark) 100%
  );
  color: var(--color-primary);
  font-size: 1.25rem;
  font-weight: 800;
  margin-bottom: 1.25rem;
  box-shadow: 0 4px 20px var(--color-accent-a30);
}

.step h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-black);
  margin-bottom: 0.5rem;
}

.step p {
  font-size: 0.95rem;
  color: var(--color-text);
  line-height: 1.6;
  max-width: 280px;
  margin: 0 auto;
}

.cta {
  position: relative;
  display: flex;
  justify-content: center;
  padding: 6rem 2rem;
  background: var(--color-bg-dark);
  overflow: hidden;
}

.cta-glow {
  position: absolute;
  width: 500px;
  height: 500px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--color-accent-a12), transparent 70%);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
}

.cta-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 1rem;
}

.cta h2 {
  font-size: 2.25rem;
  font-weight: 700;
  color: var(--color-white);
  letter-spacing: -0.02em;
}

.cta p {
  font-size: 1.1rem;
  color: var(--color-text-light-muted);
  max-width: 480px;
  line-height: 1.6;
}

.cta-button {
  margin-top: 0.5rem;
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  --p-button-hover-color: var(--color-primary);
  font-weight: 700;
}

@media (max-width: 768px) {
  .hero {
    min-height: calc(100vh - 70px);
    padding: 3rem 1.5rem;
  }

  .hero-title {
    font-size: 2.25rem;
  }

  .hero-desc {
    font-size: 1rem;
  }

  .hero-stats {
    gap: 1.5rem;
  }

  .section-title {
    font-size: 1.75rem;
    margin-bottom: 2.5rem;
  }

  .features-grid,
  .steps {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .features,
  .how-it-works,
  .cta {
    padding: 4rem 1.5rem;
  }
}
</style>
