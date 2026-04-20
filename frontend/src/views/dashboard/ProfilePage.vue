<template>
  <div class="profile-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t("profile.title") }}</h1>
        <p class="page-subtitle">{{ t("profile.subtitle") }}</p>
      </div>
    </header>

    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div v-else class="profile-layout">

      <aside class="profile-sidebar">
        <div class="identity-card">
          <div class="avatar-ring">
            <div class="avatar">{{ initials }}</div>
          </div>
          <h2 class="sid-name">{{ fullName }}</h2>
          <p class="sid-email">{{ profile.email }}</p>
          <span class="role-badge" :class="roleClass">{{ roleLabel }}</span>
        </div>

        <div class="meta-list">
          <div v-if="profile.phone" class="meta-item">
            <span class="meta-icon"><i class="pi pi-phone" /></span>
            <div class="meta-body">
              <span class="meta-label">Téléphone</span>
              <span class="meta-value">{{ profile.phone }}</span>
            </div>
          </div>
          <div v-if="profile.city" class="meta-item">
            <span class="meta-icon"><i class="pi pi-map-marker" /></span>
            <div class="meta-body">
              <span class="meta-label">Ville</span>
              <span class="meta-value">{{ profile.city }}</span>
            </div>
          </div>
          <div class="meta-item">
            <span class="meta-icon"><i class="pi pi-envelope" /></span>
            <div class="meta-body">
              <span class="meta-label">Email vérifié</span>
              <span class="meta-value verified" :class="{ unverified: !profile.emailVerified }">
                <i :class="profile.emailVerified ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
                {{ profile.emailVerified ? "Oui" : "Non" }}
              </span>
            </div>
          </div>
        </div>
      </aside>

      <main class="profile-main">

        <section class="section">
          <div class="section-header">
            <h2 class="section-title">
              <i class="pi pi-user" />
              {{ t("profile.personalInfo") }}
            </h2>
            <div class="header-actions">
              <template v-if="!editingInfo">
                <Button
                  icon="pi pi-pencil"
                  :label="t('profile.edit')"
                  severity="secondary"
                  outlined
                  size="small"
                  @click="startEditInfo"
                />
              </template>
              <template v-else>
                <Button
                  icon="pi pi-times"
                  severity="secondary"
                  text
                  rounded
                  @click="cancelEditInfo"
                />
                <Button
                  icon="pi pi-check"
                  :label="t('profile.save')"
                  class="accent-btn"
                  size="small"
                  :loading="savingInfo"
                  @click="saveInfo"
                />
              </template>
            </div>
          </div>

          <div class="fields-grid">
            <div class="field">
              <label class="field-label">{{ t("profile.firstName") }}</label>
              <InputText v-if="editingInfo" v-model="editForm.first_name" class="field-input" />
              <span v-else class="field-value">{{ profile.firstName || "—" }}</span>
            </div>

            <div class="field">
              <label class="field-label">{{ t("profile.lastName") }}</label>
              <InputText v-if="editingInfo" v-model="editForm.last_name" class="field-input" />
              <span v-else class="field-value">{{ profile.lastName || "—" }}</span>
            </div>

            <div class="field">
              <label class="field-label">{{ t("profile.email") }}</label>
              <span class="field-value">{{ profile.email }}</span>
              <span class="field-readonly-hint">{{ t("profile.emailReadonly") }}</span>
            </div>

            <div class="field">
              <label class="field-label">{{ t("profile.phone") }}</label>
              <InputText
                v-if="editingInfo"
                v-model="editForm.phone"
                :placeholder="t('profile.phonePlaceholder')"
                class="field-input"
              />
              <span v-else class="field-value">{{ profile.phone || "—" }}</span>
            </div>

            <div class="field">
              <label class="field-label">{{ t("profile.city") }}</label>
              <InputText
                v-if="editingInfo"
                v-model="editForm.city"
                :placeholder="t('profile.cityPlaceholder')"
                class="field-input"
              />
              <span v-else class="field-value">{{ profile.city || "—" }}</span>
            </div>
          </div>

          <div v-if="infoSuccess" class="success-banner">
            <i class="pi pi-check-circle" />
            {{ t("profile.saveSuccess") }}
          </div>
        </section>

        <section class="section">
          <div class="settings-head">
            <span class="settings-sup"><i class="pi pi-sliders-h" /> {{ t("profile.settingsSection").toUpperCase() }}</span>
            <h2 class="settings-title">{{ t("profile.settingsSection") }}</h2>
          </div>

          <div class="settings-list">

            <div class="settings-row" @click="expandedLang = !expandedLang">
              <div class="srow-icon">
                <i class="pi pi-globe" />
              </div>
              <span class="srow-label">{{ t("profile.language") }}</span>
              <span class="srow-value">
                {{ currentLangOption?.flag }} {{ currentLangOption?.name }}
              </span>
              <i class="pi pi-chevron-right srow-chevron" :class="{ 'srow-chevron--open': expandedLang }" />
            </div>

            <div v-if="expandedLang" class="settings-expand">
              <div class="lang-options">
                <button
                  v-for="lang in langOptions"
                  :key="lang.value"
                  class="lang-option-btn"
                  :class="{ active: selectedLang === lang.value }"
                  :disabled="savingLang"
                  @click.stop="changeLang(lang.value)"
                >
                  <span class="lang-flag">{{ lang.flag }}</span>
                  <span>{{ lang.name }}</span>
                  <i v-if="selectedLang === lang.value" class="pi pi-check lang-check" />
                </button>
              </div>
              <p v-if="langSaved" class="expand-success">
                <i class="pi pi-check-circle" /> {{ t("profile.languageSaved") }}
              </p>
            </div>

            <div class="srow-divider" />

            <div class="settings-row" @click="expandedPw = !expandedPw">
              <div class="srow-icon">
                <i class="pi pi-lock" />
              </div>
              <span class="srow-label">{{ t("profile.changePassword") }}</span>
              <i class="pi pi-chevron-right srow-chevron" :class="{ 'srow-chevron--open': expandedPw }" />
            </div>

            <div v-if="expandedPw" class="settings-expand">
              <div class="fields-grid">
                <div class="field">
                  <label class="field-label">{{ t("profile.currentPassword") }}</label>
                  <Password
                    v-model="pwForm.current_password"
                    :feedback="false"
                    toggle-mask
                    class="field-input"
                    input-class="field-pw-input"
                  />
                </div>
                <div class="field">
                  <label class="field-label">{{ t("profile.newPassword") }}</label>
                  <Password
                    v-model="pwForm.password"
                    :feedback="false"
                    toggle-mask
                    class="field-input"
                    input-class="field-pw-input"
                  />
                </div>
                <div class="field">
                  <label class="field-label">{{ t("profile.confirmPassword") }}</label>
                  <Password
                    v-model="pwForm.password_confirmation"
                    :feedback="false"
                    toggle-mask
                    class="field-input"
                    input-class="field-pw-input"
                  />
                </div>
              </div>
              <div v-if="pwError" class="error-banner">
                <i class="pi pi-exclamation-triangle" /> {{ pwError }}
              </div>
              <div v-if="pwSuccess" class="success-banner">
                <i class="pi pi-check-circle" /> {{ t("profile.passwordSuccess") }}
              </div>
              <div class="expand-action">
                <Button
                  :label="t('profile.changePassword')"
                  class="accent-btn"
                  :loading="savingPw"
                  :disabled="!pwForm.current_password || !pwForm.password || !pwForm.password_confirmation"
                  @click.stop="changePassword"
                />
              </div>
            </div>

          </div>
        </section>

      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import { authService } from "../../services/authService";

const { t, locale } = useI18n();

const loading = ref(true);
const profile = ref({});

const editingInfo = ref(false);
const savingInfo = ref(false);
const infoSuccess = ref(false);
const editForm = reactive({ first_name: "", last_name: "", phone: "", city: "" });

const savingPw = ref(false);
const pwSuccess = ref(false);
const pwError = ref("");
const pwForm = reactive({ current_password: "", password: "", password_confirmation: "" });

const expandedPw = ref(false);
const expandedLang = ref(false);

const selectedLang = ref(locale.value);
const savingLang = ref(false);
const langSaved = ref(false);

const langOptions = [
  { value: "fr", flag: "🇫🇷", name: "Français" },
  { value: "en", flag: "🇬🇧", name: "English" },
  { value: "sq", flag: "🇦🇱", name: "Shqip" },
];

const currentLangOption = computed(() => langOptions.find((l) => l.value === selectedLang.value));

async function changeLang(lang) {
  if (lang === selectedLang.value) return;
  selectedLang.value = lang;
  locale.value = lang;
  localStorage.setItem("locale", lang);
  savingLang.value = true;
  langSaved.value = false;
  try {
    await authService.updateProfile({ locale: lang });
  } catch (_) {
    // no-blocking
  } finally {
    savingLang.value = false;
    langSaved.value = true;
    setTimeout(() => (langSaved.value = false), 3000);
  }
}

const fullName = computed(() =>
  [profile.value.firstName, profile.value.lastName].filter(Boolean).join(" ") || "—"
);

const initials = computed(() => {
  const f = profile.value.firstName?.charAt(0) || "";
  const l = profile.value.lastName?.charAt(0) || "";
  return (f + l).toUpperCase() || "?";
});

const roleLabel = computed(() => {
  const r = profile.value.companyRole ?? profile.value.role;
  if (r === "owner") return t("profile.roleOwner");
  if (r === "employee") return t("profile.roleEmployee");
  return t("profile.roleUser");
});

const roleClass = computed(() => {
  const r = profile.value.companyRole ?? profile.value.role;
  if (r === "owner" || r === "employee") return "role-pro";
  return "role-client";
});

function startEditInfo() {
  editForm.first_name = profile.value.firstName ?? "";
  editForm.last_name = profile.value.lastName ?? "";
  editForm.phone = profile.value.phone ?? "";
  editForm.city = profile.value.city ?? "";
  editingInfo.value = true;
  infoSuccess.value = false;
}

function cancelEditInfo() {
  editingInfo.value = false;
}

async function saveInfo() {
  savingInfo.value = true;
  infoSuccess.value = false;
  try {
    const updated = await authService.updateProfile({
      first_name: editForm.first_name,
      last_name: editForm.last_name,
      phone: editForm.phone || null,
      city: editForm.city || null,
    });
    profile.value = updated;
    try {
      const stored = JSON.parse(localStorage.getItem("user") || "{}");
      localStorage.setItem("user", JSON.stringify({ ...stored, ...updated }));
    } catch (err) {
      console.warn("Failed to sync localStorage", err);
    }
    editingInfo.value = false;
    infoSuccess.value = true;
    setTimeout(() => (infoSuccess.value = false), 3000);
  } catch (e) {
    console.error("Failed to update profile", e);
  } finally {
    savingInfo.value = false;
  }
}

async function changePassword() {
  savingPw.value = true;
  pwError.value = "";
  pwSuccess.value = false;

  if (pwForm.password !== pwForm.password_confirmation) {
    pwError.value = t("profile.passwordMismatch");
    savingPw.value = false;
    return;
  }

  try {
    await authService.changePassword({
      current_password: pwForm.current_password,
      password: pwForm.password,
      password_confirmation: pwForm.password_confirmation,
    });
    pwForm.current_password = "";
    pwForm.password = "";
    pwForm.password_confirmation = "";
    pwSuccess.value = true;
    setTimeout(() => (pwSuccess.value = false), 3000);
  } catch (e) {
    const msg = e?.message || e?.errors?.current_password?.[0];
    pwError.value = msg || t("profile.genericError");
  } finally {
    savingPw.value = false;
  }
}

async function loadProfile() {
  loading.value = true;
  try {
    profile.value = await authService.getProfile();
  } catch (e) {
    try {
      profile.value = JSON.parse(localStorage.getItem("user") || "{}");
    } catch (err) {
      console.warn("Failed to read localStorage", err);
    }
  } finally {
    loading.value = false;
  }
}

onMounted(loadProfile);
</script>

<style scoped>
.profile-page {
  padding: 2rem 2.5rem;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 3rem;
  font-size: 2rem;
  color: var(--color-accent);
}

.page-header {
  margin-bottom: 1.75rem;
}

.page-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--color-text-dark);
  letter-spacing: -0.02em;
}

.page-subtitle {
  font-size: 0.9rem;
  color: var(--color-text-subtle);
  margin-top: 0.25rem;
}

.profile-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 1.75rem;
  align-items: start;
}

.profile-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  position: sticky;
  top: 1.5rem;
}

.identity-card {
  background: var(--color-black);
  border-radius: 20px;
  border: 1px solid var(--color-separator);
  padding: 2rem 1.5rem 1.75rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
}

.avatar-ring {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  padding: 3px;
  background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-accent-gold-light) 100%);
  margin-bottom: 0.5rem;
}

.avatar {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: var(--color-near-black);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--color-accent);
  letter-spacing: -0.02em;
}

.sid-name {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-white);
  letter-spacing: -0.01em;
  line-height: 1.3;
}

.sid-email {
  font-size: 0.82rem;
  color: var(--color-white-a45);
  word-break: break-all;
}

.role-badge {
  display: inline-block;
  width: fit-content;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 0.28rem 0.9rem;
  border-radius: 20px;
  margin-top: 0.25rem;
}

.role-badge.role-pro {
  background: var(--color-accent-a20);
  color: var(--color-accent);
}

.role-badge.role-client {
  background: var(--color-white-a08);
  color: var(--color-white-a60);
}

.meta-list {
  background: var(--color-bg-white);
  border: 1px solid var(--color-separator);
  border-radius: 16px;
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.8rem 0;
  border-bottom: 1px solid var(--color-separator);
}

.meta-item:last-child {
  border-bottom: none;
}

.meta-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--color-accent-a20);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.meta-icon i {
  font-size: 0.85rem;
  color: var(--color-accent);
}

.meta-body {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.meta-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
}

.meta-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text-dark);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta-value.verified {
  color: var(--color-success-mid);
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.meta-value.unverified {
  color: var(--color-text-error);
}

.profile-main {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.section {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.75rem 2rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-text-dark);
}

.section-title i {
  color: var(--color-accent);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.fields-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field-label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
}

.field-value {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-dark);
  padding: 0.4rem 0;
}

.field-readonly-hint {
  font-size: 0.72rem;
  color: var(--color-text-subtle);
  font-style: italic;
}

.field-input {
  width: 100%;
}

:deep(.field-pw-input) {
  width: 100%;
}

.pw-action {
  display: none;
}

.accent-btn {
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
  font-weight: 700;
}


.settings-head {
  margin-bottom: 1.25rem;
}

.settings-sup {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-accent);
  margin-bottom: 0.3rem;
}

.settings-sup i {
  font-size: 0.72rem;
}

.settings-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-text-dark);
}

.settings-list {
  display: flex;
  flex-direction: column;
}

.settings-row {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.9rem 0.25rem;
  cursor: pointer;
  border-radius: 10px;
  transition: background 0.15s;
  user-select: none;
}

.settings-row:hover {
  background: var(--color-accent-a08);
}

.srow-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--color-accent-a20);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.srow-icon i {
  font-size: 0.9rem;
  color: var(--color-accent);
}

.srow-label {
  flex: 1;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-dark);
}

.srow-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-accent);
  margin-right: 0.25rem;
}

.srow-chevron {
  font-size: 0.75rem;
  color: var(--color-text-subtle);
  transition: transform 0.2s;
}

.srow-chevron--open {
  transform: rotate(90deg);
}

.srow-divider {
  height: 1px;
  background: var(--color-separator);
  margin: 0 0.25rem;
}

.settings-expand {
  padding: 1rem 0.25rem 1.25rem;
  border-top: 1px solid var(--color-separator);
  margin-bottom: 0.25rem;
}

.expand-action {
  display: flex;
  justify-content: flex-end;
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--color-separator);
}

.expand-success {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: var(--color-success);
  margin-top: 0.75rem;
}

.success-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--color-success-bg);
  border: 1px solid var(--color-success-border);
  color: var(--color-success);
  padding: 0.75rem 1rem;
  border-radius: 10px;
  margin-top: 1rem;
  font-size: 0.9rem;
  font-weight: 500;
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  color: var(--color-text-error);
  padding: 0.75rem 1rem;
  border-radius: 10px;
  margin-top: 1rem;
  font-size: 0.9rem;
  font-weight: 500;
}

.lang-options {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.lang-option-btn {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.65rem 1.1rem;
  border-radius: 12px;
  border: 1px solid var(--color-separator);
  background: var(--color-bg-white);
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, color 0.2s;
}

.lang-option-btn:hover {
  border-color: var(--color-accent);
  color: var(--color-text-dark);
}

.lang-option-btn.active {
  border-color: var(--color-accent);
  background: var(--color-accent-a08);
  color: var(--color-accent);
  font-weight: 600;
}

.lang-option-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.lang-flag {
  font-size: 1.1rem;
  line-height: 1;
}

.lang-check {
  font-size: 0.8rem;
  color: var(--color-accent);
}

@media (max-width: 960px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }

  .profile-sidebar {
    position: static;
  }

  .identity-card {
    flex-direction: row;
    text-align: left;
    align-items: center;
    gap: 1.25rem;
    padding: 1.5rem;
  }

  .avatar-ring {
    width: 72px;
    height: 72px;
    margin-bottom: 0;
    flex-shrink: 0;
  }

  .avatar {
    font-size: 1.4rem;
  }

  .role-badge {
    margin-top: 0.1rem;
  }
}

@media (max-width: 600px) {
  .profile-page {
    padding: 1.5rem 1.25rem;
  }

  .identity-card {
    flex-direction: column;
    text-align: center;
  }

  .fields-grid {
    grid-template-columns: 1fr;
  }
}
</style>
