import { get } from "./api";

export const companyService = {
  list(params = {}) {
    return get("/companies", params);
  },

  show(id) {
    return get(`/companies/${id}`);
  },

  employees(id) {
    return get(`/companies/${id}/employees`);
  },

  availability(id, params = {}) {
    return get(`/companies/${id}/availability`, params);
  },

  slots(id, params = {}) {
    return get(`/companies/${id}/slots`, params);
  },
};
