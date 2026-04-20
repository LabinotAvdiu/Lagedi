import { createI18n } from "vue-i18n";
import fr from "./fr.json";
import en from "./en.json";
import sq from "./sh.json";

const i18n = createI18n({
  locale: "fr",
  fallbackLocale: "en",
  messages: { fr, en, sq },
});

export default i18n;
