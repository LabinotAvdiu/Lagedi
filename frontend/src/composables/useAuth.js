import { ref } from "vue";

export const isLoggedIn = ref(!!localStorage.getItem("token"));

export function setLoggedIn(value) {
  isLoggedIn.value = value;
  if (!value) {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
  }
}
