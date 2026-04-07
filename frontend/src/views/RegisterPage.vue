<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1 class="auth-title">{{ t("auth.register.title") }}</h1>

      <form
        class="auth-form"
        @submit.prevent="onSubmit"
      >
        <div class="field-row">
          <div class="field">
            <label for="name">{{ t("auth.name") }} *</label>
            <InputText
              id="name"
              v-model="form.name"
              :placeholder="t('auth.name')"
              autocomplete="family-name"
              class="w-full"
            />
          </div>
          <div class="field">
            <label for="first_name">
              {{ t("auth.firstName") }}
              <span class="optional">{{ t("auth.optional") }}</span>
            </label>
            <InputText
              id="first_name"
              v-model="form.first_name"
              :placeholder="t('auth.firstName')"
              autocomplete="given-name"
              class="w-full"
            />
          </div>
        </div>

        <div class="field">
          <label for="phone">{{ t("auth.phone") }} *</label>
          <InputText
            id="phone"
            v-model="form.phone"
            type="tel"
            :placeholder="t('auth.phonePlaceholder')"
            autocomplete="tel"
            class="w-full"
          />
        </div>

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

        <p
          v-if="errorMessage"
          class="error-message"
        >
          {{ errorMessage }}
        </p>

        <Button
          type="submit"
          :label="t('auth.register.submit')"
          :loading="loading"
          class="submit-btn"
        />
      </form>

      <div class="separator">
        <span>{{ t("auth.or") }}</span>
      </div>

      <div class="alt-section">
        <h2 class="alt-title">{{ t("auth.login.title") }}</h2>
        <Button
          :label="t('auth.register.loginLink')"
          outlined
          class="alt-btn"
          @click="router.push('/connexion')"
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
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f4f4f4;
  padding: 2rem;
}

.auth-card {
  background: #ffffff;
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
  color: #1a1a2e;
  margin-bottom: 2rem;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
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
  font-size: 0.875rem;
  font-weight: 600;
  color: #333;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.optional {
  font-size: 0.8rem;
  font-weight: 400;
  color: #9ca3af;
}

.field :deep(input) {
  width: 100%;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  padding: 0.65rem 0.875rem;
  font-size: 0.95rem;
  color: #1a1a2e;
  transition: border-color 0.2s;
}

.field :deep(input:focus) {
  border-color: #1a1a2e;
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
  color: #6b7280;
  padding: 0;
  display: flex;
  align-items: center;
}

.error-message {
  font-size: 0.875rem;
  color: #dc2626;
  text-align: center;
}

.submit-btn {
  width: 100%;
  background: #1a1a2e !important;
  border-color: #1a1a2e !important;
  color: #ffffff !important;
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
  color: #9ca3af;
  font-size: 0.875rem;
}

.separator::before,
.separator::after {
  content: "";
  flex: 1;
  height: 1px;
  background: #e5e7eb;
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
  color: #1a1a2e;
  text-align: center;
}

.alt-btn {
  width: 100%;
  border-radius: 8px;
  border-color: #1a1a2e !important;
  color: #1a1a2e !important;
  padding: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
}
</style>
