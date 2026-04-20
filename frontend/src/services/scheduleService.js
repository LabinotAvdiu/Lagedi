import { get, put, post, del } from "./api";

export const scheduleService = {
  show(params = {}) {
    return get("/my-schedule", params);
  },

  upcoming() {
    return get("/my-schedule/upcoming");
  },

  settings() {
    return get("/my-schedule/settings");
  },

  updateHours(hours) {
    return put("/my-schedule/hours", { hours });
  },

  createBreak(payload) {
    return post("/my-schedule/breaks", payload);
  },

  deleteBreak(id) {
    return del(`/my-schedule/breaks/${id}`);
  },

  createDayOff(payload) {
    return post("/my-schedule/days-off", payload);
  },

  deleteDayOff(id) {
    return del(`/my-schedule/days-off/${id}`);
  },

  storeWalkIn(payload) {
    return post("/my-schedule/walk-in", payload);
  },
};
