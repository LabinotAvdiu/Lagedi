import { createRouter, createWebHistory } from "vue-router";
import HomePage from "../views/HomePage.vue";
import LoginPage from "../views/LoginPage.vue";
import RegisterPage from "../views/RegisterPage.vue";
import SearchResultsPage from "../views/SearchResultsPage.vue";

const routes = [
  {
    path: "/",
    name: "home",
    component: HomePage,
  },
  {
    path: "/recherche",
    name: "search",
    component: SearchResultsPage,
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
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
