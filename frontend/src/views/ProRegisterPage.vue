<template>
  <div class="pro-page">
    <header class="pro-hero">
      <div class="hero-glow" />
      <div class="pro-badge">
        <i class="pi pi-briefcase" />
        {{ t("pro.badge") }}
      </div>
      <h1 class="hero-title">{{ t("pro.title") }}</h1>
      <p class="hero-subtitle">{{ t("pro.subtitle") }}</p>
    </header>

    <main class="pro-main">
      <div class="pro-card">
        <template v-if="step === 0">
          <h2 class="card-heading">{{ t("pro.activityQuestion") }}</h2>

          <div class="choice-grid">
            <button
              type="button"
              class="choice-card"
              :class="{ selected: activityType === 'local' }"
              @click="activityType = 'local'"
            >
              <i class="pi pi-shop choice-icon" />
              <span class="choice-title">{{ t("pro.activityLocal") }}</span>
              <span class="choice-desc">{{ t("pro.activityLocalDesc") }}</span>
            </button>

            <button
              type="button"
              class="choice-card"
              :class="{ selected: activityType === 'home' }"
              @click="activityType = 'home'"
            >
              <i class="pi pi-home choice-icon" />
              <span class="choice-title">{{ t("pro.activityHome") }}</span>
              <span class="choice-desc">{{ t("pro.activityHomeDesc") }}</span>
            </button>
          </div>

          <Button
            type="button"
            :label="t('pro.next')"
            class="submit-btn"
            icon="pi pi-arrow-right"
            icon-pos="right"
            :disabled="!activityType"
            @click="step = 1"
          />
        </template>

        <template v-if="step === 1">
          <div class="step-header">
            <div class="steps-indicator">
              <div class="step-dot active" />
              <div class="step-line" />
              <div class="step-dot" />
            </div>
            <p class="step-label">{{ t("pro.stepInfo") }}</p>
          </div>

          <form class="pro-form" @submit.prevent="goToStep2">
            <div class="field-row">
              <div class="field">
                <label for="last_name">{{ t("auth.lastName") }}</label>
                <div class="input-wrapper">
                  <i class="pi pi-user input-icon" />
                  <InputText
                    id="last_name"
                    v-model="form.last_name"
                    :placeholder="t('auth.lastName')"
                    autocomplete="family-name"
                    class="w-full"
                  />
                </div>
              </div>
              <div class="field">
                <label for="first_name">
                  {{ t("auth.firstName") }}
                  <span class="optional">{{ t("auth.optional") }}</span>
                </label>
                <div class="input-wrapper">
                  <i class="pi pi-user input-icon" />
                  <InputText
                    id="first_name"
                    v-model="form.first_name"
                    :placeholder="t('auth.firstName')"
                    autocomplete="given-name"
                    class="w-full"
                  />
                </div>
              </div>
            </div>

            <div class="field">
              <label for="email">{{ t("auth.email") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-envelope input-icon" />
                <InputText
                  id="email"
                  v-model="form.email"
                  type="email"
                  :placeholder="t('auth.email')"
                  autocomplete="email"
                  class="w-full"
                />
              </div>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="phone">{{ t("auth.phone") }}</label>
                <div class="input-wrapper">
                  <i class="pi pi-phone input-icon" />
                  <InputText
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    :placeholder="t('auth.phonePlaceholder')"
                    autocomplete="tel"
                    class="w-full"
                  />
                </div>
              </div>
              <div class="field">
                <label for="city">{{ t("pro.city") }}</label>
                <div class="input-wrapper">
                  <i class="pi pi-map-marker input-icon" />
                  <InputText
                    id="city"
                    v-model="form.city"
                    :placeholder="t('pro.cityPlaceholder')"
                    autocomplete="address-level2"
                    class="w-full"
                  />
                </div>
              </div>
            </div>

            <p v-if="errorMessage" class="error-message">
              {{ errorMessage }}
            </p>

            <div class="btn-row">
              <Button
                type="button"
                :label="t('pro.back')"
                outlined
                class="back-btn"
                icon="pi pi-arrow-left"
                @click="step = 0"
              />
              <Button
                type="submit"
                :label="t('pro.next')"
                class="submit-btn"
                icon="pi pi-arrow-right"
                icon-pos="right"
              />
            </div>
          </form>
        </template>

        <template v-if="step === 2">
          <div class="step-header">
            <div class="steps-indicator">
              <div class="step-dot active" />
              <div class="step-line active" />
              <div class="step-dot active" />
            </div>
            <p class="step-label">{{ t("pro.stepCompany") }}</p>
          </div>

          <form class="pro-form" @submit.prevent="onSubmit">
            <div class="field">
              <label for="company_name">{{ t("pro.companyName") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-building input-icon" />
                <InputText
                  id="company_name"
                  v-model="form.company_name"
                  :placeholder="t('pro.companyNamePlaceholder')"
                  class="w-full"
                />
              </div>
            </div>

            <div class="field">
              <label for="address">{{ t("pro.address") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-map input-icon" />
                <InputText
                  id="address"
                  v-model="form.address"
                  :placeholder="
                    activityType === 'home'
                      ? t('pro.addressHomePlaceholder')
                      : t('pro.addressPlaceholder')
                  "
                  autocomplete="street-address"
                  class="w-full"
                />
              </div>
            </div>

            <div class="field">
              <label for="password">{{ t("auth.password") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-lock input-icon" />
                <InputText
                  id="password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  :placeholder="t('auth.password')"
                  autocomplete="new-password"
                  class="w-full"
                />
                <button
                  type="button"
                  class="toggle-password"
                  :aria-label="t('auth.togglePassword')"
                  @click="showPassword = !showPassword"
                >
                  <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'" />
                </button>
              </div>
            </div>

            <div class="field">
              <label for="password_confirmation">{{
                t("pro.confirmPassword")
              }}</label>
              <div class="input-wrapper">
                <i class="pi pi-lock input-icon" />
                <InputText
                  id="password_confirmation"
                  v-model="form.password_confirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  :placeholder="t('pro.confirmPassword')"
                  autocomplete="new-password"
                  class="w-full"
                />
                <button
                  type="button"
                  class="toggle-password"
                  :aria-label="t('auth.togglePassword')"
                  @click="showConfirmPassword = !showConfirmPassword"
                >
                  <i
                    :class="
                      showConfirmPassword ? 'pi pi-eye-slash' : 'pi pi-eye'
                    "
                  />
                </button>
              </div>
            </div>

            <p v-if="errorMessage" class="error-message">
              {{ errorMessage }}
            </p>

            <div class="btn-row">
              <Button
                type="button"
                :label="t('pro.back')"
                outlined
                class="back-btn"
                icon="pi pi-arrow-left"
                @click="step = 1"
              />
              <Button
                type="submit"
                :label="t('pro.submit')"
                :loading="loading"
                class="submit-btn"
                icon="pi pi-check"
                icon-pos="right"
              />
            </div>
          </form>
        </template>
      </div>

      <div class="pro-features">
        <div class="feature">
          <i class="pi pi-calendar feature-icon" />
          <span class="feature-title">{{ t("pro.feature1") }}</span>
          <span class="feature-desc">{{ t("pro.feature1Desc") }}</span>
        </div>
        <div class="feature">
          <i class="pi pi-eye feature-icon" />
          <span class="feature-title">{{ t("pro.feature2") }}</span>
          <span class="feature-desc">{{ t("pro.feature2Desc") }}</span>
        </div>
        <div class="feature">
          <i class="pi pi-chart-bar feature-icon" />
          <span class="feature-title">{{ t("pro.feature3") }}</span>
          <span class="feature-desc">{{ t("pro.feature3Desc") }}</span>
        </div>
      </div>

      <div class="pro-alt">
        <span class="alt-text">{{ t("auth.login.title") }}</span>
        <router-link to="/connexion" class="alt-link">{{
          t("auth.register.loginLink")
        }}</router-link>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import { authService } from "../services/authService";
import { setLoggedIn } from "../composables/useAuth";

const { t } = useI18n();
const router = useRouter();

const step = ref(0);
const activityType = ref("");
const form = ref({
  first_name: "",
  last_name: "",
  email: "",
  phone: "",
  city: "",
  company_name: "",
  address: "",
  password: "",
  password_confirmation: "",
});
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const loading = ref(false);
const errorMessage = ref("");

const goToStep2 = () => {
  if (!form.value.last_name.trim() || !form.value.email.trim()) {
    errorMessage.value = t("pro.error.fillRequired");
    return;
  }
  errorMessage.value = "";
  step.value = 2;
};

const onSubmit = async () => {
  if (form.value.password !== form.value.password_confirmation) {
    errorMessage.value = t("pro.error.passwordMismatch");
    return;
  }

  errorMessage.value = "";
  loading.value = true;

  try {
    const data = await authService.register({
      first_name: form.value.first_name,
      last_name: form.value.last_name,
      email: form.value.email,
      phone: form.value.phone || null,
      city: form.value.city || null,
      role: "company",
      company_name: form.value.company_name,
      address: form.value.address,
      password: form.value.password,
    });

    localStorage.setItem("token", data.token);
    localStorage.setItem("user", JSON.stringify(data.user));
    setLoggedIn(true);

    router.push("/dashboard");
  } catch (err) {
    const firstError = err?.errors ? Object.values(err.errors)[0]?.[0] : null;
    errorMessage.value = firstError ?? err?.message ?? t("auth.error.generic");
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.pro-page {
  min-height: 100vh;
  background: var(--color-bg-dark);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.pro-hero {
  position: relative;
  width: 100%;
  text-align: center;
  padding: 4rem 2rem 3rem;
  overflow: hidden;
}

.hero-glow {
  position: absolute;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--color-accent-a12), transparent 70%);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
}

.pro-badge {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--color-accent-a12);
  color: var(--color-accent);
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.4rem 1rem;
  border-radius: 50px;
  border: 1px solid var(--color-accent-a25);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 1.25rem;
}

.hero-title {
  position: relative;
  z-index: 1;
  font-size: 2rem;
  font-weight: 800;
  color: var(--color-white);
  margin-bottom: 0.5rem;
}

.hero-subtitle {
  position: relative;
  z-index: 1;
  font-size: 1rem;
  color: var(--color-text-muted);
  max-width: 500px;
  margin: 0 auto;
  line-height: 1.6;
}

.pro-main {
  width: 100%;
  max-width: 580px;
  padding: 0 1.5rem 3rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.pro-card {
  padding: 2.5rem;
  border-radius: 20px;
  background: var(--color-bg-dark-light);
  border: 1px solid var(--color-accent-a25);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.card-heading {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-white);
  text-align: center;
  margin-bottom: 1.5rem;
}

.choice-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.choice-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  padding: 1.5rem 1rem;
  border-radius: 16px;
  background: var(--color-bg-dark);
  border: 2px solid var(--color-accent-a25);
  cursor: pointer;
  transition: all 0.25s;
  text-align: center;
}

.choice-card:hover {
  border-color: var(--color-accent-a40);
  background: rgba(201, 168, 76, 0.04);
}

.choice-card.selected {
  border-color: var(--color-accent);
  background: var(--color-accent-a12);
  box-shadow: 0 0 20px var(--color-accent-a12);
}

.choice-icon {
  font-size: 1.75rem;
  color: var(--color-accent);
}

.choice-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--color-white);
}

.choice-desc {
  font-size: 0.8rem;
  color: var(--color-text-muted);
  line-height: 1.4;
}

.step-header {
  margin-bottom: 1.5rem;
}

.steps-indicator {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 0.5rem;
}

.step-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--color-accent-a25);
  transition: background 0.3s;
}

.step-dot.active {
  background: var(--color-accent);
  box-shadow: 0 0 8px var(--color-accent-a40);
}

.step-line {
  flex: 1;
  height: 2px;
  background: var(--color-accent-a25);
  transition: background 0.3s;
}

.step-line.active {
  background: var(--color-accent);
}

.step-label {
  font-size: 0.8rem;
  color: var(--color-accent);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.pro-form {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--color-white-a75);
  letter-spacing: 0.02em;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.optional {
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--color-text-muted);
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-muted);
  font-size: 0.9rem;
  z-index: 1;
  pointer-events: none;
}

.input-wrapper :deep(input) {
  width: 100%;
  border-radius: 10px;
  border: 1px solid var(--color-accent-a25);
  background: var(--color-bg-dark);
  padding: 0.75rem 0.875rem 0.75rem 2.5rem;
  font-size: 0.95rem;
  color: var(--color-white);
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.input-wrapper :deep(input::placeholder) {
  color: var(--color-text-muted);
}

.input-wrapper :deep(input:focus) {
  border-color: var(--color-accent);
  outline: none;
  box-shadow: 0 0 0 3px var(--color-accent-a12);
}

.toggle-password {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-muted);
  padding: 0;
  display: flex;
  align-items: center;
  transition: color 0.2s;
}

.toggle-password:hover {
  color: var(--color-accent);
}

.error-message {
  font-size: 0.875rem;
  color: var(--color-text-error);
  background: rgba(220, 38, 38, 0.08);
  border: 1px solid rgba(220, 38, 38, 0.2);
  border-radius: 8px;
  padding: 0.6rem 0.875rem;
  text-align: center;
}

.btn-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.75rem;
  margin-top: 0.25rem;
}

.back-btn {
  border-radius: 10px;
  padding: 0.8rem 1.25rem;
  font-size: 0.95rem;
  font-weight: 600;
  border-color: var(--color-accent-a40);
  color: var(--color-accent);
}

.back-btn:hover {
  background: var(--color-accent-a12);
}

.submit-btn {
  width: 100%;
  border-radius: 10px;
  padding: 0.8rem;
  font-size: 1rem;
  font-weight: 700;
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  --p-button-hover-color: var(--color-primary);
}

.pro-features {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.feature {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  text-align: center;
  padding: 1.25rem 0.75rem;
  border-radius: 14px;
  background: var(--color-bg-dark-light);
  border: 1px solid var(--color-accent-a25);
}

.feature-icon {
  font-size: 1.5rem;
  color: var(--color-accent);
  margin-bottom: 0.25rem;
}

.feature-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--color-white);
}

.feature-desc {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  line-height: 1.4;
}

.pro-alt {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding-bottom: 1rem;
}

.alt-text {
  font-size: 0.9rem;
  color: var(--color-text-muted);
}

.alt-link {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-accent);
  text-decoration: none;
  transition: opacity 0.2s;
}

.alt-link:hover {
  opacity: 0.8;
}

@media (max-width: 640px) {
  .hero-title {
    font-size: 1.5rem;
  }

  .pro-card {
    padding: 1.5rem;
  }

  .choice-grid,
  .field-row {
    grid-template-columns: 1fr;
  }

  .pro-features {
    grid-template-columns: 1fr;
  }
}
</style>
