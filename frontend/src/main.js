import { createApp } from "vue";
import PrimeVue from "primevue/config";
import "primeicons/primeicons.css";
import App from "./App.vue";
import router from "./router";
import i18n from "./i18n";
import TerminiPreset from "./theme/preset.js";

const app = createApp(App);

app.use(PrimeVue, {
  theme: {
    preset: TerminiPreset,
    options: {
      darkModeSelector: false,
    },
  },
});
app.use(router);
app.use(i18n);

app.mount("#app");
