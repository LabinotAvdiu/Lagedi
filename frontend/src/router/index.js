import { createRouter, createWebHistory } from "vue-router";
import HomePage from "../views/HomePage.vue";
import LoginPage from "../views/LoginPage.vue";
import RegisterPage from "../views/RegisterPage.vue";
import ForgotPasswordPage from "../views/ForgotPasswordPage.vue";
import ResetPasswordPage from "../views/ResetPasswordPage.vue";
import ProRegisterPage from "../views/ProRegisterPage.vue";
import DashboardLayout from "../views/dashboard/DashboardLayout.vue";
import SearchPage from "../views/dashboard/SearchPage.vue";
import MySalonPage from "../views/dashboard/MySalonPage.vue";
import PlanningPage from "../views/dashboard/PlanningPage.vue";
import HoursPage from "../views/dashboard/HoursPage.vue";
import AppointmentsPage from "../views/dashboard/AppointmentsPage.vue";
import SalonDetailPage from "../views/dashboard/SalonDetailPage.vue";
import BookingPage from "../views/dashboard/BookingPage.vue";
import ProfilePage from "../views/dashboard/ProfilePage.vue";

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
  {
    path: "/mot-de-passe-oublie",
    name: "forgot-password",
    component: ForgotPasswordPage,
  },
  {
    path: "/reinitialiser-mot-de-passe",
    name: "reset-password",
    component: ResetPasswordPage,
  },
  {
    path: "/dashboard",
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: "",
        redirect: "/dashboard/rechercher",
      },
      {
        path: "rechercher",
        name: "dashboard-search",
        component: SearchPage,
      },
      {
        path: "mon-salon",
        name: "dashboard-salon",
        component: MySalonPage,
      },
      {
        path: "planning",
        name: "dashboard-planning",
        component: PlanningPage,
      },
      {
        path: "horaires",
        name: "dashboard-hours",
        component: HoursPage,
      },
      {
        path: "mes-rdv",
        name: "dashboard-appointments",
        component: AppointmentsPage,
      },
      {
        path: "profil",
        name: "dashboard-profile",
        component: ProfilePage,
      },
      {
        path: "salon/:id",
        name: "dashboard-salon-detail",
        component: SalonDetailPage,
      },
      {
        path: "reserver/:id",
        name: "dashboard-booking",
        component: BookingPage,
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  if (to.matched.some((r) => r.meta.requiresAuth)) {
    const token = localStorage.getItem("token");
    if (!token) {
      return next({ name: "login" });
    }
  }
  next();
});

export default router;
