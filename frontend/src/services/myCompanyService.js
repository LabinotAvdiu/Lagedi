import { get, put, post, del } from "./api";

export const myCompanyService = {
  show() {
    return get("/my-company");
  },

  update(payload) {
    return put("/my-company", payload);
  },

  listCategories() {
    return get("/my-company/categories");
  },

  createCategory(payload) {
    return post("/my-company/categories", payload);
  },

  updateCategory(id, payload) {
    return put(`/my-company/categories/${id}`, payload);
  },

  deleteCategory(id) {
    return del(`/my-company/categories/${id}`);
  },

  createService(payload) {
    return post("/my-company/services", payload);
  },

  updateService(id, payload) {
    return put(`/my-company/services/${id}`, payload);
  },

  deleteService(id) {
    return del(`/my-company/services/${id}`);
  },

  listEmployees() {
    return get("/my-company/employees");
  },

  inviteEmployee(payload) {
    return post("/my-company/employees/invite", payload);
  },

  createEmployee(payload) {
    return post("/my-company/employees/create", payload);
  },

  updateEmployee(id, payload) {
    return put(`/my-company/employees/${id}`, payload);
  },

  deleteEmployee(id) {
    return del(`/my-company/employees/${id}`);
  },

  listHours() {
    return get("/my-company/hours");
  },

  updateHours(hours) {
    return put("/my-company/hours", { hours });
  },
};
