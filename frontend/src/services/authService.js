import { get, put } from "./api";

const BASE_URL =
  import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8080/api";

async function request(endpoint, body) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const json = await response.json();

  if (!response.ok) {
    throw json;
  }

  return json.data ?? json;
}

export const authService = {
  register(payload) {
    return request("/auth/register", payload);
  },

  login(payload) {
    return request("/auth/login", payload);
  },

  getProfile() {
    return get("/auth/profile");
  },

  updateProfile(payload) {
    return put("/auth/profile", payload);
  },

  changePassword(payload) {
    return put("/auth/change-password", payload);
  },

  forgotPassword(email) {
    return request("/auth/forgot-password", { email });
  },

  resetPassword(payload) {
    return request("/auth/reset-password", payload);
  },
};
