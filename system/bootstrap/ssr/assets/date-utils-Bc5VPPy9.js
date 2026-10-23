function parseDate(value) {
  if (!value) return null;
  if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
  if (typeof value !== "string") return null;
  const ymd = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (ymd) {
    return new Date(Number(ymd[1]), Number(ymd[2]) - 1, Number(ymd[3]));
  }
  const d = new Date(value);
  return isNaN(d.getTime()) ? null : d;
}
function formatDate(value, length = "short") {
  const d = parseDate(value);
  if (!d) return "—";
  return d.toLocaleDateString("en-US", {
    month: length === "short" ? "short" : "long",
    day: "numeric",
    year: "numeric"
  });
}
function formatHumanDate(value) {
  const d = parseDate(value);
  if (!d) return "—";
  const today = /* @__PURE__ */ new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(d);
  target.setHours(0, 0, 0, 0);
  const diffDays = Math.round((target.getTime() - today.getTime()) / 864e5);
  if (diffDays === 0) return "Today";
  if (diffDays === 1) return "Tomorrow";
  if (diffDays === -1) return "Yesterday";
  if (diffDays > 1 && diffDays <= 6) {
    return target.toLocaleDateString("en-US", { weekday: "short", month: "short", day: "numeric" });
  }
  return formatDate(value, "short");
}
function formatTime(timeStr) {
  if (!timeStr) return "";
  const parts = String(timeStr).split(":");
  if (parts.length < 2) return timeStr;
  let hour = parseInt(parts[0], 10);
  const minute = parts[1].padStart(2, "0");
  const ampm = hour >= 12 ? "PM" : "AM";
  hour = hour % 12;
  if (hour === 0) hour = 12;
  return `${hour}:${minute} ${ampm}`;
}
function formatTimeRange(start, end) {
  const startFmt = formatTime(start);
  const endFmt = formatTime(end);
  if (startFmt && endFmt) return `${startFmt} – ${endFmt}`;
  return startFmt || endFmt || "—";
}
function formatHumanSchedule(date, start, end, includeEnd = false) {
  const day = formatHumanDate(date);
  const time = includeEnd ? formatTimeRange(start, end) : formatTime(start);
  if (day === "—" && !time) return "—";
  if (!time) return day;
  return `${day}, ${time}`;
}
export {
  formatTimeRange as a,
  formatHumanSchedule as b,
  formatDate as f
};
