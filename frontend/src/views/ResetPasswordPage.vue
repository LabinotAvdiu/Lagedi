<template>
  <div class="auth-page">
    <div class="auth-brand">
      <div class="brand-glow" />
      <img src="../assets/Logo.svg" alt="Termini Im" class="brand-logo" />
    </div>

    <div class="auth-form-panel">
      <div class="auth-card">

        <template v-if="resetDone">
          <div class="success-icon-wrap">
            <i class="pi pi-check" />
          </div>
          <h1 class="auth-title">{{ t("auth.reset.title") }}</h1>
          <p class="auth-subtitle success-text">{{ t("auth.reset.success") }}</p>
          <router-link to="/connexion" class="back-btn-link">
            <i class="pi pi-arrow-left" />
            {{ t("auth.reset.backToLogin") }}
          </router-link>
        </template>

        <template v-else>
          <div class="icon-wrap">
            <i class="pi pi-key" />
          </div>
          <h1 class="auth-title">{{ t("auth.reset.title") }}</h1>
          <p class="auth-subtitle">{{ t("auth.reset.subtitle") }}</p>

          <form class="auth-form" @submit.prevent="onSubmit">
            <div class="field">
              <label for="password">{{ t("auth.reset.passwordLabel") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-lock input-icon" />
                <InputText
                  id="password"
                  v-model="password"
                  type="password"
                  :placeholder="t('auth.reset.passwordLabel')"
                  autocomplete="new-password"
                  class="w-full"
                  required
                />
              </div>
            </div>

            <div class="field">
              <label for="confirm">{{ t("auth.reset.confirmLabel") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-lock input-icon" />
                <InputText
                  id="confirm"
                  v-model="confirm"
                  type="password"
                  :placeholder="t('auth.reset.confirmLabel')"
                  autocomplete="new-password"
                  class="w-full"
                  required
                />
              </div>
            </div>

            <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>

            <Button
              type="submit"
              :label="t('auth.reset.submit')"
              :loading="loading"
              class="submit-btn"
              icon="pi pi-check"
              icon-pos="right"
            />
          </form>

          <router-link to="/connexion" class="back-btn-link">
            <i class="pi pi-arrow-left" />
            {{ t("auth.reset.backToLogin") }}
          </router-link>
        </template>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import { authService } from "../services/authService";

const { t } = useI18n();
const route = useRoute();

const password = ref("");
const confirm = ref("");
const loading = ref(false);
const resetDone = ref(false);
const errorMessage = ref("");

const token = ref("");
const email = ref("");

onMounted(() => {
  token.value = route.query.token || "";
  email.value = route.query.email || "";
});

const onSubmit = async () => {
  errorMessage.value = "";

  if (password.value !== confirm.value) {
    errorMessage.value = t("auth.reset.mismatch");
    return;
  }

  loading.value = true;
  try {
    await authService.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirm.value,
    });
    resetDone.value = true;
  } catch (err) {
    const status = err?.response?.status;
    if (status === 422) {
      errorMessage.value = t("auth.reset.invalidToken");
    } else {
      errorMessage.value = t("auth.error.generic");
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--color-bg-dark);
}

.auth-brand {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-dark);
  overflow: hidden;
  border-right: 1px solid var(--color-accent-a25);
}

.brand-glow {
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

.brand-logo {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 780px;
  height: auto;
  filter: drop-shadow(0 4px 40px var(--color-accent-a30));
}

.auth-form-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: var(--color-bg-dark-light);
}

.auth-card {
  width: 100%;
  max-width: 440px;
  padding: 2.5rem;
  border-radius: 20px;
  background: var(--color-bg-dark);
  border: 1px solid var(--color-accent-a25);
  box-shadow: 0 8px 40px var(--color-shadow-dark);
  display: flex;
  flex-direction: column;
  gap: 0;
}

.icon-wrap,
.success-icon-wrap {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: var(--color-accent-a12);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.25rem;
}

.icon-wrap i,
.success-icon-wrap i {
  font-size: 1.5rem;
  color: var(--color-accent);
}

.success-icon-wrap {
  background: var(--color-success-bg);
}

.success-icon-wrap i {
  color: var(--color-success);
}

.auth-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--color-white);
  margin-bottom: 0.5rem;
}

.auth-subtitle {
  font-size: 0.9rem;
  color: var(--color-text-muted);
  margin-bottom: 2rem;
  line-height: 1.55;
}

.success-text {
  color: var(--color-text-muted);
  margin-bottom: 1.5rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin-bottom: 1.5rem;
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
  background: var(--color-bg-dark-light);
  padding: 0.75rem 0.875rem 0.75rem 2.5rem;
  font-size: 0.95rem;
  color: var(--color-white);
  transition: border-color 0.2s, box-shadow 0.2s;
}

.input-wrapper :deep(input::placeholder) {
  color: var(--color-text-muted);
}

.input-wrapper :deep(input:focus) {
  border-color: var(--color-accent);
  outline: none;
  box-shadow: 0 0 0 3px var(--color-accent-a12);
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

.submit-btn {
  width: 100%;
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  font-weight: 700;
  padding: 0.75rem;
  border-radius: 10px;
}

.back-btn-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--color-text-muted);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  transition: color 0.2s;
  margin-top: 0.5rem;
}

.back-btn-link:hover {
  color: var(--color-accent);
}

@media (max-width: 860px) {
  .auth-page {
    grid-template-columns: 1fr;
  }

  .auth-brand {
    display: none;
  }

  .auth-form-panel {
    padding: 1.5rem 1rem;
  }
}
</style>
