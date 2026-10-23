import { e as escape_html, B as Button, d as derived } from "../app.js";
import { D as Dialog, e as Dialog_portal, f as Dialog_overlay, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "qrcode";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { X } from "./x-CnxrMbMn.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { C as Copy } from "./copy-D9_YDhto.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { M as Mail } from "./mail-DBtkjaCt.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./Icon-Dal7PqKK.js";
function VoucherDialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { open = false, user = null, onOpenChange = () => {
    } } = $$props;
    let selectedPurpose = "activation";
    let voucher = null;
    let busy = false;
    let error = "";
    let confirmingReissue = false;
    let emailSent = false;
    let copiedType = null;
    let currentTime = Date.now();
    const secondsRemaining = derived(() => voucher ? Math.max(0, Math.floor((new Date(voucher.expires_at).getTime() - currentTime) / 1e3)) : 0);
    const expiryCountdown = derived(() => `${String(Math.floor(secondsRemaining() / 3600)).padStart(2, "0")}:${String(Math.floor(secondsRemaining() % 3600 / 60)).padStart(2, "0")}:${String(secondsRemaining() % 60).padStart(2, "0")}`);
    async function send(url, payload = { purpose: selectedPurpose }) {
      busy = true;
      error = "";
      try {
        const response = await fetch(url, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? ""
          },
          body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message ?? "The voucher request failed.");
        return data;
      } catch (caught) {
        error = caught.message ?? "The voucher request failed.";
        return null;
      } finally {
        busy = false;
      }
    }
    async function issue() {
      if (!user || busy) return;
      voucher = await send(`/staff/personnel/${user.id}/vouchers`, { purpose: selectedPurpose, send_email: false });
    }
    async function reissue() {
      if (!user) return;
      if (!confirmingReissue) {
        confirmingReissue = true;
        return;
      }
      confirmingReissue = false;
      emailSent = false;
      voucher = await send(`/staff/personnel/${user.id}/vouchers/reissue`, { purpose: selectedPurpose, send_email: false });
    }
    async function sendActivationEmail() {
      if (!user) return;
      const replacement = await send(`/staff/personnel/${user.id}/vouchers/reissue`, { purpose: selectedPurpose, send_email: true });
      if (replacement) {
        voucher = replacement;
        emailSent = replacement.email_sent === true;
      }
    }
    function printSlip() {
      window.print();
    }
    function close() {
      voucher = null;
      confirmingReissue = false;
      emailSent = false;
      error = "";
      copiedType = null;
      onOpenChange(false);
    }
    if (Dialog) {
      $$renderer2.push("<!--[-->");
      Dialog($$renderer2, {
        open,
        onOpenChange: (value) => value ? onOpenChange(true) : close(),
        children: ($$renderer3) => {
          if (Dialog_portal) {
            $$renderer3.push("<!--[-->");
            Dialog_portal($$renderer3, {
              children: ($$renderer4) => {
                if (Dialog_overlay) {
                  $$renderer4.push("<!--[-->");
                  Dialog_overlay($$renderer4, {});
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Dialog_content) {
                  $$renderer4.push("<!--[-->");
                  Dialog_content($$renderer4, {
                    class: "max-h-[92vh] overflow-y-auto sm:max-w-3xl border border-border/80 shadow-2xl p-6",
                    children: ($$renderer5) => {
                      if (Dialog_header) {
                        $$renderer5.push("<!--[-->");
                        Dialog_header($$renderer5, {
                          class: "pb-3 border-b border-border/60",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<div class="flex items-center justify-between"><div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">`);
                            Shield_check($$renderer6, { class: "size-4 text-primary" });
                            $$renderer6.push(`<!----> <span>Staff Access Issuance</span></div> <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">`);
                            Clock($$renderer6, { class: "size-3" });
                            $$renderer6.push(`<!----> Single-use · 24h</span></div> `);
                            if (Dialog_title) {
                              $$renderer6.push("<!--[-->");
                              Dialog_title($$renderer6, {
                                class: "text-xl sm:text-2xl font-bold",
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(voucher ? "Staff Access Voucher" : "Staff Activation Voucher")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push("<!--]-->");
                            } else {
                              $$renderer6.push("<!--[!-->");
                              $$renderer6.push("<!--]-->");
                            }
                            $$renderer6.push(` `);
                            if (Dialog_description) {
                              $$renderer6.push("<!--[-->");
                              Dialog_description($$renderer6, {
                                class: "text-sm text-muted-foreground",
                                children: ($$renderer7) => {
                                  {
                                    $$renderer7.push("<!--[-1-->");
                                    $$renderer7.push(`Issue a secure, zero-password onboarding credential for <span class="font-medium text-foreground">${escape_html(user?.name ?? "Staff member")}</span> (${escape_html(user?.email ?? "No email on record")}).`);
                                  }
                                  $$renderer7.push(`<!--]-->`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push("<!--]-->");
                            } else {
                              $$renderer6.push("<!--[!-->");
                              $$renderer6.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` `);
                      if (error) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div role="alert" class="mt-4 rounded-lg bg-destructive/10 p-3.5 text-sm text-destructive flex items-center gap-2">`);
                        Triangle_alert($$renderer5, { class: "size-4 shrink-0" });
                        $$renderer5.push(`<!----> <span>${escape_html(error)}</span></div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (!voucher && !busy) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="mt-4 space-y-3 rounded-lg border border-border/60 bg-muted/20 p-4"><div class="space-y-2"><label for="voucher-purpose" class="text-sm font-medium">Voucher purpose</label> `);
                        $$renderer5.select(
                          {
                            id: "voucher-purpose",
                            value: selectedPurpose,
                            class: "flex min-h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm",
                            disabled: busy
                          },
                          ($$renderer6) => {
                            $$renderer6.option({ value: "activation" }, ($$renderer7) => {
                              $$renderer7.push(`Account Activation`);
                            });
                            $$renderer6.option({ value: "password_reset" }, ($$renderer7) => {
                              $$renderer7.push(`Password Reset`);
                            });
                            $$renderer6.option({ value: "mfa_reset" }, ($$renderer7) => {
                              $$renderer7.push(`MFA Device Reset`);
                            });
                          }
                        );
                        $$renderer5.push(`</div> `);
                        {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]--> `);
                        Button($$renderer5, {
                          type: "button",
                          onclick: issue,
                          disabled: busy,
                          class: "w-full",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->${escape_html(busy ? "Generating…" : "Generate Voucher")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----></div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (busy && !voucher) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="py-16 text-center space-y-3" aria-live="polite"><div class="mx-auto size-8 animate-spin rounded-full border-2 border-primary border-r-transparent"></div> <p class="text-sm text-muted-foreground">Generating cryptographic voucher &amp; QR code…</p></div>`);
                      } else if (voucher) {
                        $$renderer5.push("<!--[1-->");
                        $$renderer5.push(`<div class="grid grid-cols-1 md:grid-cols-12 gap-6 my-4"><div class="md:col-span-5 flex flex-col items-center"><div class="voucher-slip w-full rounded-xl border border-border/80 bg-card p-5 text-center shadow-xs flex flex-col items-center space-y-3.5"><div class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">`);
                        Shield_check($$renderer5, { class: "size-3.5 text-primary" });
                        $$renderer5.push(`<!----> <span>NEXIAM Staff Slip</span></div> <div class="rounded-lg border border-border/60 bg-white p-2 shadow-xs"><canvas class="size-44 sm:size-48" aria-label="Voucher QR code"></canvas></div> <div class="w-full space-y-1"><p class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Reference</p> <div class="flex items-center justify-center gap-1.5"><span class="font-mono text-base font-bold tracking-wider">${escape_html(voucher.public_reference)}</span> <button type="button" class="p-1 text-muted-foreground hover:text-foreground rounded transition-colors" title="Copy reference">`);
                        if (copiedType === "ref") {
                          $$renderer5.push("<!--[0-->");
                          Check($$renderer5, { class: "size-3.5 text-emerald-600" });
                        } else {
                          $$renderer5.push("<!--[-1-->");
                          Copy($$renderer5, { class: "size-3.5" });
                        }
                        $$renderer5.push(`<!--]--></button></div></div> <div class="w-full space-y-1"><p class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Fallback Code</p> <div class="flex items-center justify-center gap-1.5"><span class="font-mono text-xl font-bold tracking-[0.2em] text-primary">${escape_html(voucher.fallback)}</span> <button type="button" class="p-1 text-muted-foreground hover:text-foreground rounded transition-colors" title="Copy fallback code">`);
                        if (copiedType === "code") {
                          $$renderer5.push("<!--[0-->");
                          Check($$renderer5, { class: "size-3.5 text-emerald-600" });
                        } else {
                          $$renderer5.push("<!--[-1-->");
                          Copy($$renderer5, { class: "size-3.5" });
                        }
                        $$renderer5.push(`<!--]--></button></div></div> <div class="w-full pt-2 border-t border-border/50 text-[11px] text-muted-foreground space-y-1"><p class="flex items-center justify-center gap-1 font-medium">`);
                        Clock($$renderer5, { class: "size-3" });
                        $$renderer5.push(`<!----> Expires in ${escape_html(expiryCountdown())}</p> <p class="text-[10px] text-muted-foreground/80">Hand slip directly or print for physical delivery.</p></div></div></div> <div class="md:col-span-7 flex flex-col justify-between space-y-4"><div class="rounded-lg border border-border/60 bg-muted/30 p-3.5 space-y-1"><div class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Target Recipient</span> <span class="text-xs font-medium text-muted-foreground capitalize">${escape_html(user?.office ?? "Campus Office")}</span></div> <p class="text-sm font-bold text-foreground">${escape_html(user?.name ?? "Staff member")}</p> <p class="text-xs text-muted-foreground font-mono">${escape_html(user?.email ?? "No email on record")}</p></div> <div class="rounded-lg border border-border/60 p-3.5 space-y-2 bg-card"><div class="flex items-start justify-between gap-3"><div><h4 class="text-sm font-semibold flex items-center gap-1.5">`);
                        Printer($$renderer5, { class: "size-4 text-primary" });
                        $$renderer5.push(`<!----> In-Person Hand-Off (Recommended)</h4> <p class="text-xs text-muted-foreground mt-0.5">Print a physical slip with QR code and fallback PIN to hand directly to the staff member on campus.</p></div> `);
                        Button($$renderer5, {
                          type: "button",
                          variant: "outline",
                          size: "sm",
                          onclick: printSlip,
                          disabled: busy,
                          class: "shrink-0 gap-1.5",
                          children: ($$renderer6) => {
                            Printer($$renderer6, { class: "size-3.5" });
                            $$renderer6.push(`<!----> Print Slip`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----></div></div> `);
                        {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<div class="rounded-lg border border-border/60 p-3.5 space-y-2 bg-card"><div class="flex items-start justify-between gap-3"><div class="space-y-1"><h4 class="text-sm font-semibold flex items-center gap-1.5">`);
                          Mail($$renderer5, { class: "size-4 text-primary" });
                          $$renderer5.push(`<!----> Email Invitation</h4> <p class="text-xs text-muted-foreground">Dispatches a branded activation email with direct setup link to <span class="font-medium text-foreground">${escape_html(user?.email)}</span>.</p> `);
                          if (emailSent) {
                            $$renderer5.push("<!--[0-->");
                            $$renderer5.push(`<p role="status" aria-live="polite" class="text-xs font-medium text-emerald-700 dark:text-emerald-400 flex items-center gap-1 pt-1">`);
                            Check($$renderer5, { class: "size-3.5" });
                            $$renderer5.push(`<!----> Activation email dispatched successfully.</p>`);
                          } else {
                            $$renderer5.push("<!--[-1-->");
                          }
                          $$renderer5.push(`<!--]--></div> `);
                          Button($$renderer5, {
                            type: "button",
                            variant: "outline",
                            size: "sm",
                            onclick: sendActivationEmail,
                            disabled: busy || !user?.email,
                            class: "shrink-0 gap-1.5",
                            children: ($$renderer6) => {
                              Mail($$renderer6, { class: "size-3.5" });
                              $$renderer6.push(`<!----> ${escape_html(busy ? "Sending…" : "Send Email")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push(`<!----></div> <p class="text-[11px] text-muted-foreground/80">Dispatching email generates a replacement voucher and supersedes prior printed slips.</p></div>`);
                        }
                        $$renderer5.push(`<!--]--> <div class="rounded-lg border border-destructive/20 bg-destructive/5 p-3 space-y-2"><div class="space-y-2"><label for="reissue-voucher-purpose" class="text-xs font-semibold text-foreground">Purpose for replacement voucher</label> `);
                        $$renderer5.select(
                          {
                            id: "reissue-voucher-purpose",
                            value: selectedPurpose,
                            class: "flex min-h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm",
                            disabled: busy
                          },
                          ($$renderer6) => {
                            $$renderer6.option({ value: "activation" }, ($$renderer7) => {
                              $$renderer7.push(`Account Activation`);
                            });
                            $$renderer6.option({ value: "password_reset" }, ($$renderer7) => {
                              $$renderer7.push(`Password Reset`);
                            });
                            $$renderer6.option({ value: "mfa_reset" }, ($$renderer7) => {
                              $$renderer7.push(`MFA Device Reset`);
                            });
                          }
                        );
                        $$renderer5.push(` `);
                        {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]--></div> <div class="flex items-center justify-between gap-2"><div><p class="text-xs font-semibold text-destructive">Credential Replacement</p> <p class="text-[11px] text-muted-foreground">If the slip was lost or compromised, issue a fresh token.</p></div> `);
                        Button($$renderer5, {
                          type: "button",
                          variant: confirmingReissue ? "destructive" : "outline",
                          size: "sm",
                          onclick: reissue,
                          disabled: busy,
                          class: "shrink-0 text-xs gap-1",
                          children: ($$renderer6) => {
                            Refresh_cw($$renderer6, { class: "size-3" });
                            $$renderer6.push(`<!----> ${escape_html(confirmingReissue ? "Confirm Invalidation" : "Reissue Voucher")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----></div> `);
                        if (confirmingReissue) {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<p role="alert" class="text-xs font-medium text-destructive">Warning: The current voucher QR and fallback code will be immediately revoked.</p>`);
                        } else {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]--></div></div></div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (Dialog_footer) {
                        $$renderer5.push("<!--[-->");
                        Dialog_footer($$renderer5, {
                          class: "pt-3 border-t border-border/60 flex flex-col sm:flex-row items-center justify-between gap-3 w-full",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<div class="flex items-center gap-2 text-xs text-muted-foreground text-center sm:text-left">`);
                            Shield_check($$renderer6, { class: "size-3.5 shrink-0 text-muted-foreground" });
                            $$renderer6.push(`<!----> <span>Single-use credential · Automatically invalidated upon activation</span></div> `);
                            Button($$renderer6, {
                              type: "button",
                              variant: "outline",
                              onclick: close,
                              class: "w-full sm:w-auto gap-1.5 shrink-0",
                              children: ($$renderer7) => {
                                X($$renderer7, { class: "size-4" });
                                $$renderer7.push(`<!----> Close`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!---->`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
        },
        $$slots: { default: true }
      });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
  });
}
export {
  VoucherDialog as default
};
