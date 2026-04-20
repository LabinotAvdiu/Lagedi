<template>
  <div class="my-salon-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">{{ t("dashboard.tabs.mySalon") }}</h1>
        <p class="page-subtitle">{{ t("dashboard.salon.subtitle") }}</p>
      </div>
    </header>

    <div v-if="loading" class="loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <template v-else-if="company">
      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-building" /> {{ t("dashboard.salon.info") }}</h2>
          <Button
            v-if="!editingInfo"
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            @click="editingInfo = true"
          />
          <Button
            v-else
            icon="pi pi-check"
            text
            rounded
            @click="saveCompanyInfo"
          />
        </div>
        <div v-if="!editingInfo" class="info-grid">
          <div class="info-item">
            <span class="info-label">{{ t("dashboard.salon.name") }}</span>
            <span class="info-value">{{ company.name }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">{{ t("dashboard.salon.address") }}</span>
            <span class="info-value">{{ company.address }}, {{ company.city }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">{{ t("dashboard.salon.phone") }}</span>
            <span class="info-value">{{ company.phone || "—" }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">{{ t("dashboard.salon.email") }}</span>
            <span class="info-value">{{ company.email || "—" }}</span>
          </div>
        </div>
        <div v-else class="edit-form">
          <div class="form-row">
            <label>{{ t("dashboard.salon.name") }}</label>
            <InputText v-model="editForm.name" />
          </div>
          <div class="form-row">
            <label>{{ t("dashboard.salon.address") }}</label>
            <InputText v-model="editForm.address" />
          </div>
          <div class="form-row">
            <label>{{ t("dashboard.salon.phone") }}</label>
            <InputText v-model="editForm.phone" />
          </div>
          <div class="form-row">
            <label>{{ t("dashboard.salon.email") }}</label>
            <InputText v-model="editForm.email" />
          </div>
        </div>
      </section>

      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-list" /> {{ t("dashboard.salon.services") }}</h2>
          <Button
            icon="pi pi-plus"
            :label="t('dashboard.salon.addCategory')"
            severity="secondary"
            outlined
            size="small"
            @click="showAddCategory = true"
          />
        </div>

        <div v-if="showAddCategory" class="add-form">
          <InputText
            v-model="newCategoryName"
            :placeholder="t('dashboard.salon.categoryName')"
          />
          <Button
            icon="pi pi-check"
            size="small"
            @click="addCategory"
            class="accent-button"
          />
          <Button
            icon="pi pi-times"
            severity="secondary"
            text
            size="small"
            @click="showAddCategory = false"
          />
        </div>

        <div v-for="cat in categories" :key="cat.id" class="category-block">
          <div class="category-header">
            <h3>{{ cat.name }}</h3>
            <div class="category-actions">
              <Button
                icon="pi pi-plus"
                severity="secondary"
                text
                size="small"
                @click="openAddService(cat.id)"
              />
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                size="small"
                @click="deleteCategory(cat.id)"
              />
            </div>
          </div>
          <div v-if="cat.services?.length" class="services-list">
            <div v-for="svc in cat.services" :key="svc.id" class="service-item">
              <div class="service-info">
                <span class="service-name">{{ svc.name }}</span>
                <span class="service-detail">{{ svc.durationMinutes }}min · {{ svc.price }}€</span>
              </div>
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                size="small"
                @click="deleteService(svc.id)"
              />
            </div>
          </div>
          <p v-else class="empty-text">{{ t("dashboard.salon.noServices") }}</p>
        </div>

        <Dialog
          v-model:visible="showAddService"
          :header="t('dashboard.salon.addService')"
          modal
          :style="{ width: '400px' }"
        >
          <div class="dialog-form">
            <div class="form-row">
              <label>{{ t("dashboard.salon.serviceName") }}</label>
              <InputText v-model="newService.name" />
            </div>
            <div class="form-row">
              <label>{{ t("dashboard.salon.duration") }}</label>
              <InputText v-model.number="newService.duration" type="number" min="5" />
            </div>
            <div class="form-row">
              <label>{{ t("dashboard.salon.price") }}</label>
              <InputText v-model.number="newService.price" type="number" min="0" step="0.01" />
            </div>
          </div>
          <template #footer>
            <Button
              :label="t('dashboard.salon.save')"
              @click="addService"
              class="accent-button"
            />
          </template>
        </Dialog>
      </section>

      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-users" /> {{ t("dashboard.salon.team") }}</h2>
          <Button
            icon="pi pi-plus"
            :label="t('dashboard.salon.addEmployee')"
            severity="secondary"
            outlined
            size="small"
            @click="showAddEmployee = true"
          />
        </div>
        <div v-if="employees.length" class="team-list">
          <div v-for="emp in employees" :key="emp.id" class="team-member">
            <div class="member-avatar">
              {{ emp.firstName?.charAt(0) }}{{ emp.lastName?.charAt(0) }}
            </div>
            <div class="member-info">
              <span class="member-name">{{ emp.firstName }} {{ emp.lastName }}</span>
              <span class="member-role">{{ emp.role }}</span>
            </div>
            <span
              class="member-status"
              :class="{ active: emp.isActive, inactive: !emp.isActive }"
            >
              {{ emp.isActive ? t("dashboard.salon.active") : t("dashboard.salon.inactive") }}
            </span>
          </div>
        </div>
        <p v-else class="empty-text">{{ t("dashboard.salon.noEmployees") }}</p>

        <Dialog
          v-model:visible="showAddEmployee"
          :header="t('dashboard.salon.addEmployee')"
          modal
          :style="{ width: '420px' }"
        >
          <div class="dialog-form">
            <div class="form-row">
              <label>{{ t("auth.firstName") }}</label>
              <InputText v-model="newEmployee.first_name" />
            </div>
            <div class="form-row">
              <label>{{ t("auth.lastName") }}</label>
              <InputText v-model="newEmployee.last_name" />
            </div>
            <div class="form-row">
              <label>{{ t("auth.email") }}</label>
              <InputText v-model="newEmployee.email" />
            </div>
            <div class="form-row">
              <label>{{ t("auth.password") }}</label>
              <InputText v-model="newEmployee.password" type="password" />
            </div>
          </div>
          <template #footer>
            <Button
              :label="t('dashboard.salon.save')"
              @click="addEmployee"
              class="accent-button"
            />
          </template>
        </Dialog>
      </section>

      <section class="section">
        <div class="section-header">
          <h2><i class="pi pi-clock" /> {{ t("dashboard.salon.openingHours") }}</h2>
          <Button
            v-if="!editingHours"
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            @click="editingHours = true"
          />
          <Button
            v-else
            icon="pi pi-check"
            text
            rounded
            @click="saveHours"
          />
        </div>
        <div class="hours-list">
          <div v-for="h in openingHours" :key="h.dayOfWeek" class="hour-row">
            <span class="day-name">{{ dayNames[h.dayOfWeek] }}</span>
            <template v-if="!editingHours">
              <span v-if="h.isClosed" class="closed-badge">{{ t("dashboard.salon.closed") }}</span>
              <span v-else class="hour-time">{{ h.openTime }} - {{ h.closeTime }}</span>
            </template>
            <template v-else>
              <label class="closed-toggle">
                <input type="checkbox" v-model="h.isClosed" />
                {{ t("dashboard.salon.closed") }}
              </label>
              <template v-if="!h.isClosed">
                <InputText v-model="h.openTime" type="time" class="time-input" />
                <span>—</span>
                <InputText v-model="h.closeTime" type="time" class="time-input" />
              </template>
            </template>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import InputText from "primevue/inputtext";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import { myCompanyService } from "../../services/myCompanyService";

const { t } = useI18n();

const loading = ref(true);
const company = ref(null);
const categories = ref([]);
const employees = ref([]);
const openingHours = ref([]);

const editingInfo = ref(false);
const editingHours = ref(false);
const editForm = reactive({ name: "", address: "", phone: "", email: "" });

const showAddCategory = ref(false);
const newCategoryName = ref("");

const showAddService = ref(false);
const selectedCategoryId = ref(null);
const newService = reactive({ name: "", duration: 30, price: 0 });

const showAddEmployee = ref(false);
const newEmployee = reactive({ first_name: "", last_name: "", email: "", password: "" });

const dayNames = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];

async function loadAll() {
  loading.value = true;
  try {
    const [comp, cats, emps, hours] = await Promise.all([
      myCompanyService.show(),
      myCompanyService.listCategories(),
      myCompanyService.listEmployees(),
      myCompanyService.listHours(),
    ]);
    company.value = comp;
    categories.value = Array.isArray(cats) ? cats : [];
    employees.value = Array.isArray(emps) ? emps : [];
    openingHours.value = Array.isArray(hours) ? hours : comp.openingHours ?? [];
    editForm.name = comp.name;
    editForm.address = comp.address;
    editForm.phone = comp.phone;
    editForm.email = comp.email;
  } catch (e) {
    console.error("Failed to load company data", e);
  } finally {
    loading.value = false;
  }
}

async function saveCompanyInfo() {
  try {
    await myCompanyService.update(editForm);
    company.value = { ...company.value, ...editForm };
    editingInfo.value = false;
  } catch (e) {
    console.error("Failed to update company", e);
  }
}

async function addCategory() {
  if (!newCategoryName.value.trim()) return;
  try {
    await myCompanyService.createCategory({ name: newCategoryName.value });
    newCategoryName.value = "";
    showAddCategory.value = false;
    categories.value = await myCompanyService.listCategories();
  } catch (e) {
    console.error("Failed to create category", e);
  }
}

async function deleteCategory(id) {
  try {
    await myCompanyService.deleteCategory(id);
    categories.value = categories.value.filter((c) => c.id !== id);
  } catch (e) {
    console.error("Failed to delete category", e);
  }
}

function openAddService(categoryId) {
  selectedCategoryId.value = categoryId;
  newService.name = "";
  newService.duration = 30;
  newService.price = 0;
  showAddService.value = true;
}

async function addService() {
  if (!newService.name.trim()) return;
  try {
    await myCompanyService.createService({
      category_id: selectedCategoryId.value,
      name: newService.name,
      duration: newService.duration,
      price: newService.price,
    });
    showAddService.value = false;
    categories.value = await myCompanyService.listCategories();
  } catch (e) {
    console.error("Failed to create service", e);
  }
}

async function deleteService(id) {
  try {
    await myCompanyService.deleteService(id);
    categories.value = await myCompanyService.listCategories();
  } catch (e) {
    console.error("Failed to delete service", e);
  }
}

async function addEmployee() {
  if (!newEmployee.email.trim()) return;
  try {
    await myCompanyService.createEmployee(newEmployee);
    showAddEmployee.value = false;
    newEmployee.first_name = "";
    newEmployee.last_name = "";
    newEmployee.email = "";
    newEmployee.password = "";
    employees.value = await myCompanyService.listEmployees();
  } catch (e) {
    console.error("Failed to add employee", e);
  }
}

async function saveHours() {
  try {
    const hours = openingHours.value.map((h) => ({
      day_of_week: h.dayOfWeek,
      open_time: h.openTime,
      close_time: h.closeTime,
      is_closed: h.isClosed,
    }));
    await myCompanyService.updateHours(hours);
    editingHours.value = false;
  } catch (e) {
    console.error("Failed to update hours", e);
  }
}

onMounted(loadAll);
</script>

<style scoped>
.my-salon-page {
  padding: 2rem 2.5rem;
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

.loading {
  display: flex;
  justify-content: center;
  padding: 3rem;
  font-size: 2rem;
  color: var(--color-accent);
}

.section {
  background: var(--color-bg-white);
  border-radius: 16px;
  border: 1px solid var(--color-separator);
  padding: 1.75rem 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px var(--color-shadow-sm);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.section-header h2 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-text-dark);
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.section-header h2 i {
  color: var(--color-accent);
  font-size: 1.1rem;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1.25rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.info-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
}

.info-value {
  font-size: 0.95rem;
  color: var(--color-text-dark);
  font-weight: 500;
}

.edit-form,
.dialog-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-row label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-subtle);
}

.add-form {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 1.25rem;
}

.category-block {
  margin-bottom: 1.25rem;
}

.category-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.6rem 0;
  border-bottom: 1px solid var(--color-separator);
}

.category-header h3 {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--color-text-dark);
}

.category-actions {
  display: flex;
  gap: 0.25rem;
}

.services-list {
  margin-top: 0.5rem;
}

.service-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0;
  border-bottom: 1px solid var(--color-separator);
}

.service-item:last-child {
  border-bottom: none;
}

.service-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.service-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-text-dark);
}

.service-detail {
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.empty-text {
  font-size: 0.85rem;
  color: var(--color-text-subtle);
  padding: 0.75rem 0;
}

.team-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
}

.team-member {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: var(--color-bg);
  transition: box-shadow 0.2s;
}

.team-member:hover {
  box-shadow: 0 2px 8px var(--color-shadow-md);
}

.member-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: var(--color-accent-a20);
  color: var(--color-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.member-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.member-name {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text-dark);
}

.member-role {
  font-size: 0.75rem;
  color: var(--color-text-subtle);
  text-transform: capitalize;
}

.member-status {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.6rem;
  border-radius: 20px;
}

.member-status.active {
  background: var(--color-success-bg-mid);
  color: var(--color-success);
}

.member-status.inactive {
  background: var(--color-danger-bg);
  color: var(--color-text-error);
}

.hours-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.hour-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0;
  border-bottom: 1px solid var(--color-separator);
}

.hour-row:last-child {
  border-bottom: none;
}

.day-name {
  width: 100px;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-text-dark);
}

.hour-time {
  font-size: 0.9rem;
  color: var(--color-text);
}

.closed-badge {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-error);
}

.closed-toggle {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
  color: var(--color-text-subtle);
}

.time-input {
  width: 120px;
}

.accent-button {
  --p-button-background: var(--color-accent);
  --p-button-border-color: var(--color-accent);
  --p-button-color: var(--color-primary);
  --p-button-hover-background: var(--color-accent-hover);
  --p-button-hover-border-color: var(--color-accent-hover);
}

@media (max-width: 860px) {
  .my-salon-page {
    padding: 1.5rem 1.25rem;
  }

  .info-grid {
    grid-template-columns: 1fr 1fr;
  }

  .team-list {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
