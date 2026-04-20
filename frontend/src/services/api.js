const BASE_URL =
  import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8080/api";

function getHeaders() {
  const headers = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };
  const token = localStorage.getItem("token");
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

async function handleResponse(response) {
  const json = await response.json();
  if (!response.ok) {
    throw json;
  }
  return json.data ?? json;
}

export async function get(endpoint, params = {}) {
  const url = new URL(`${BASE_URL}${endpoint}`);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.append(key, value);
    }
  });
  const response = await fetch(url.toString(), {
    method: "GET",
    headers: getHeaders(),
  });
  return handleResponse(response);
}

export async function post(endpoint, body = {}) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "POST",
    headers: getHeaders(),
    body: JSON.stringify(body),
  });
  return handleResponse(response);
}

export async function put(endpoint, body = {}) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "PUT",
    headers: getHeaders(),
    body: JSON.stringify(body),
  });
  return handleResponse(response);
}

export async function del(endpoint) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "DELETE",
    headers: getHeaders(),
  });
  return handleResponse(response);
}

export async function patch(endpoint, body = {}) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "PATCH",
    headers: getHeaders(),
    body: JSON.stringify(body),
  });
  return handleResponse(response);
}
