<template>
  <div class="auth-page">
    <div class="auth-brand">
      <div class="brand-glow" />
      <img src="../assets/Logo.svg" alt="Termini Im" class="brand-logo" />
    </div>

    <div class="auth-form-panel">
      <div class="auth-card">
        <h1 class="auth-title">{{ t("auth.register.title") }}</h1>
        <p class="auth-subtitle">{{ t("home.heroDesc") }}</p>

        <form class="auth-form" @submit.prevent="onSubmit">
          <div class="field-row">
            <div class="field">
              <label for="name">{{ t("auth.name") }}</label>
              <div class="input-wrapper">
                <i class="pi pi-user input-icon" />
                <InputText
                  id="name"
                  v-model="form.name"
                  :placeholder="t('auth.name')"
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

          <p v-if="errorMessage" class="error-message">
            {{ errorMessage }}
          </p>

          <Button
            type="submit"
            :label="t('auth.register.submit')"
            :loading="loading"
            class="submit-btn"
            icon="pi pi-user-plus"
            icon-pos="right"
          />
        </form>

        <div class="separator">
          <span>{{ t("auth.or") }}</span>
        </div>

        <div class="alt-section">
          <p class="alt-text">{{ t("auth.login.title") }}</p>
          <Button
            :label="t('auth.register.loginLink')"
            outlined
            class="alt-btn"
            icon="pi pi-sign-in"
            icon-pos="right"
            @click="router.push('/connexion')"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import { authService } from "../services/authService";

const { t } = useI18n();
const router = useRouter();

const form = ref({
  name: "",
  first_name: "",
  email: "",
  password: "",
  phone: "",
});
const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref("");

const onSubmit = async () => {
  errorMessage.value = "";
  loading.value = true;

  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      phone: form.value.phone,
    };

    if (form.value.first_name.trim()) {
      payload.first_name = form.value.first_name.trim();
    }

    const data = await authService.register(payload);

    localStorage.setItem("token", data.token);
    localStorage.setItem("user", JSON.stringify(data.user));

    router.push("/");
  } catch (err) {
    const firstError = err?.errors
      ? Object.values(err.errors)[0]?.[0]
      : null;
    errorMessage.value = firstError ?? err?.message ?? t("auth.error.generic");
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
  max-width: 480px;
  padding: 2.5rem;
  border-radius: 20px;
  background: var(--color-bg-dark);
  border: 1px solid var(--color-accent-a25);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
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
  line-height: 1.5;
}

.auth-form {
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

.submit-btn {
  width: 100%;
  border-radius: 10px;
  padding: 0.8rem;
  font-size: 1rem;
  font-weight: 700;
  margin-top: 0.5rem;
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  --p-button-hover-color: var(--color-primary);
}

.separator {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 1.75rem 0;
  color: var(--color-text-muted);
  font-size: 0.85rem;
}

.separator::before,
.separator::after {
  content: "";
  flex: 1;
  height: 1px;
  background: var(--color-accent-a25);
}

.alt-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.alt-text {
  font-size: 0.9rem;
  color: var(--color-text-muted);
}

.alt-btn {
  width: 100%;
  border-radius: 10px;
  padding: 0.75rem;
  font-size: 0.95rem;
  font-weight: 600;
  --p-button-outlined-border-color: var(--color-accent-a40);
  --p-button-outlined-color: var(--color-accent);
  --p-button-outlined-hover-background: var(--color-accent-a12);
  border-color: var(--color-accent-a40);
  color: var(--color-accent);
}

.alt-btn:hover {
  background: var(--color-accent-a12);
}

@media (max-width: 900px) {
  .auth-page {
    grid-template-columns: 1fr;
  }

  .auth-brand {
    display: none;
  }

  .auth-form-panel {
    min-height: 100vh;
  }

  .field-row {
    grid-template-columns: 1fr;
  }
}
</style>
