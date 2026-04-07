const BASE_URL = "http://localhost:8080/api";

async function request(endpoint, body) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await response.json();

  if (!response.ok) {
    throw data;
  }

  return data;
}

export const authService = {
  register(payload) {
    return request("/auth/register", payload);
  },

  login(payload) {
    return request("/auth/login", payload);
  },
};
