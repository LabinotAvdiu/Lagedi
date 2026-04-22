import { createRouter, createWebHistory } from "vue-router";
import HomePage from "../views/HomePage.vue";
import LoginPage from "../views/LoginPage.vue";
import RegisterPage from "../views/RegisterPage.vue";
import ProRegisterPage from "../views/ProRegisterPage.vue";

const routes = [
  {
    path: "/",
    name: "home",
    component: HomePage,
  },
  {
    path: "/connexion",
    name: "login",
    component: LoginPage,
  },
  {
    path: "/inscription",
    name: "register",
    component: RegisterPage,
  },
  {
    path: "/pro",
    name: "pro-register",
    component: ProRegisterPage,
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
