<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1 class="auth-title">{{ t("auth.login.title") }}</h1>

      <form class="auth-form" @submit.prevent="onSubmit">
        <div class="field">
          <label for="email">{{ t("auth.email") }} *</label>
          <InputText
            id="email"
            v-model="form.email"
            type="email"
            :placeholder="t('auth.email')"
            autocomplete="email"
            class="w-full"
          />
        </div>

        <div class="field">
          <label for="password">{{ t("auth.password") }} *</label>
          <div class="password-wrapper">
            <InputText
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              :placeholder="t('auth.password')"
              autocomplete="current-password"
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
          <router-link to="/mot-de-passe-oublie" class="forgot-link">
            {{ t("auth.login.forgotPassword") }}
          </router-link>
        </div>

        <p v-if="errorMessage" class="error-message">
          {{ errorMessage }}
        </p>

        <Button
          type="submit"
          :label="t('auth.login.submit')"
          :loading="loading"
          class="submit-btn"
        />
      </form>

      <div class="separator">
        <span>{{ t("auth.or") }}</span>
      </div>

      <div class="alt-section">
        <h2 class="alt-title">{{ t("auth.register.title") }}</h2>
        <Button
          :label="t('auth.login.createAccount')"
          outlined
          class="alt-btn"
          @click="router.push('/inscription')"
        />
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
import { setLoggedIn } from "../composables/useAuth";

const { t } = useI18n();
const router = useRouter();

const form = ref({ email: "", password: "" });
const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref("");

const onSubmit = async () => {
  errorMessage.value = "";
  loading.value = true;

  try {
    const data = await authService.login({
      email: form.value.email,
      password: form.value.password,
    });

    localStorage.setItem("token", data.token);
    localStorage.setItem("user", JSON.stringify(data.user));
    setLoggedIn(true);

    router.push("/");
  } catch (err) {
    errorMessage.value =
      t(`auth.error.${err?.errors?.email?.[0]}`) ?? t("auth.error.generic");
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg-page);
  padding: 2rem;
}

.auth-card {
  background: var(--color-bg-white);
  border-radius: 12px;
  padding: 3rem 2.5rem;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
}

.auth-title {
  font-size: 1.4rem;
  font-weight: 700;
  text-align: center;
  color: var(--color-primary);
  margin-bottom: 2rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.field label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.field :deep(input) {
  width: 100%;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  padding: 0.65rem 0.875rem;
  font-size: 0.95rem;
  color: var(--color-primary);
  transition: border-color 0.2s;
}

.field :deep(input:focus) {
  border-color: var(--color-primary);
  outline: none;
  box-shadow: none;
}

.password-wrapper {
  position: relative;
}

.password-wrapper :deep(input) {
  padding-right: 2.8rem;
}

.toggle-password {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-toggle);
  padding: 0;
  display: flex;
  align-items: center;
}

.forgot-link {
  font-size: 0.85rem;
  color: var(--color-primary);
  text-decoration: underline;
  align-self: flex-start;
  margin-top: 0.25rem;
}

.error-message {
  font-size: 0.875rem;
  color: var(--color-text-error);
  text-align: center;
}

.submit-btn {
  width: 100%;
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: var(--color-text-light);
  border-radius: 8px;
  padding: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  margin-top: 0.5rem;
}

.separator {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 2rem 0;
  color: var(--color-text-subtle);
  font-size: 0.875rem;
}

.separator::before,
.separator::after {
  content: "";
  flex: 1;
  height: 1px;
  background: var(--color-separator);
}

.alt-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.alt-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-primary);
  text-align: center;
}

.alt-btn {
  width: 100%;
  border-radius: 8px;
  border-color: var(--color-primary);
  color: var(--color-primary);
  padding: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
}
</style>
