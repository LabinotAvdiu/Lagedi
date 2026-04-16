import { createI18n } from "vue-i18n";
import fr from "./fr.json";
import en from "./en.json";
import sh from "./sh.json";

const i18n = createI18n({
  locale: "fr",
  fallbackLocale: "en",
  messages: { fr, en, sh },
});

export default i18n;
