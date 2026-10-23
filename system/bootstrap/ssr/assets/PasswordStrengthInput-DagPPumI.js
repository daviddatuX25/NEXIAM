import { l as bind_props, b as attr, e as escape_html, B as Button, s as stringify, a as ensure_array_like, c as attr_class, d as derived } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { E as Eye_off } from "./eye-off-Bx_raPQb.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { C as Check } from "./check-DK45Y9SJ.js";
const MIN_LENGTH = 8;
const STRONG_LENGTH = 12;
function getRequirements(password) {
  const p = password ?? "";
  return {
    minLength: p.length >= MIN_LENGTH,
    hasUppercase: /[A-Z]/.test(p),
    hasLowercase: /[a-z]/.test(p),
    hasNumber: /\d/.test(p)
  };
}
function getStrengthScore(password) {
  const p = password ?? "";
  if (p.length === 0) return 0;
  let score = 0;
  if (p.length >= MIN_LENGTH) score += 1;
  if (p.length >= 10) score += 1;
  if (p.length >= STRONG_LENGTH) score += 1;
  const variety = [
    /[A-Z]/.test(p),
    /[a-z]/.test(p),
    /\d/.test(p),
    /[^A-Za-z0-9]/.test(p)
  ].filter(Boolean).length;
  score += Math.min(variety, 2);
  return Math.min(score, 4);
}
function getStrengthLabel(score) {
  switch (score) {
    case 0:
      return "very-weak";
    case 1:
      return "weak";
    case 2:
      return "fair";
    case 3:
      return "strong";
    case 4:
    default:
      return "excellent";
  }
}
function passwordsMatch(password, confirmation) {
  if (!confirmation || confirmation.length === 0) return false;
  return password === confirmation;
}
function PasswordStrengthInput($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      password = "",
      passwordConfirmation = "",
      showConfirmation = true,
      id = "password",
      label = "Password",
      errors = {},
      confirmationLabel = "Confirm password",
      passwordError,
      confirmationError
    } = $$props;
    let showPassword = false;
    let showConfirmationPassword = false;
    const requirements = derived(() => getRequirements(password));
    const strengthScore = derived(() => getStrengthScore(password));
    const strengthLabel = derived(() => getStrengthLabel(strengthScore()));
    const match = derived(() => showConfirmation ? passwordsMatch(password, passwordConfirmation) : true);
    const pwError = derived(() => passwordError ?? errors?.password);
    const confError = derived(() => confirmationError ?? errors?.password_confirmation);
    const pwErrorText = derived(() => pwError() ? typeof pwError() === "string" ? pwError() : pwError()[0] : null);
    const confErrorText = derived(() => confError() ? typeof confError() === "string" ? confError() : confError()[0] : null);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="space-y-4"><div class="space-y-2"><label${attr("for", id)} class="text-sm font-medium leading-none">${escape_html(label)}</label> <div class="relative">`);
      Input($$renderer3, {
        id,
        type: showPassword ? "text" : "password",
        placeholder: "••••••••",
        autocomplete: "new-password",
        required: true,
        "aria-invalid": !!pwErrorText(),
        "aria-describedby": pwErrorText() ? `${id}-error` : `${id}-requirements`,
        class: "pr-10",
        get value() {
          return password;
        },
        set value($$value) {
          password = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      Button($$renderer3, {
        type: "button",
        variant: "ghost",
        size: "icon-sm",
        class: "absolute right-1 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground",
        onclick: () => showPassword = !showPassword,
        "aria-label": showPassword ? "Hide password" : "Show password",
        children: ($$renderer4) => {
          if (showPassword) {
            $$renderer4.push("<!--[0-->");
            Eye_off($$renderer4, { class: "size-4" });
          } else {
            $$renderer4.push("<!--[-1-->");
            Eye($$renderer4, { class: "size-4" });
          }
          $$renderer4.push(`<!--]-->`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> <div${attr("id", `${stringify(id)}-strength`)} role="status" aria-live="polite"${attr("aria-label", `Password strength: ${stringify(strengthLabel())}`)} class="mt-1.5"><div class="flex gap-1"><!--[-->`);
      const each_array = ensure_array_like([0, 1, 2, 3, 4]);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let segment = each_array[$$index];
        $$renderer3.push(`<div${attr_class("h-1 flex-1 rounded-full transition-colors", void 0, {
          "bg-destructive": strengthScore() >= 1 && segment < strengthScore() && strengthScore() <= 2,
          "bg-muted": strengthScore() === 0 || segment >= strengthScore(),
          "bg-yellow-500": strengthScore() === 3 && segment < strengthScore(),
          "bg-primary": strengthScore() >= 4 && segment < strengthScore()
        })}></div>`);
      }
      $$renderer3.push(`<!--]--></div></div> <ul${attr("id", `${stringify(id)}-requirements`)} class="mt-2 space-y-1 text-sm text-muted-foreground"><li class="flex items-center gap-2">`);
      if (requirements().minLength) {
        $$renderer3.push("<!--[0-->");
        Check($$renderer3, { class: "size-4 shrink-0 text-primary", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span class="text-primary">At least 8 characters</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<span class="size-4 shrink-0" aria-hidden="true">•</span> <span>At least 8 characters</span>`);
      }
      $$renderer3.push(`<!--]--></li> <li class="flex items-center gap-2">`);
      if (requirements().hasUppercase) {
        $$renderer3.push("<!--[0-->");
        Check($$renderer3, { class: "size-4 shrink-0 text-primary", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span class="text-primary">One uppercase letter</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<span class="size-4 shrink-0" aria-hidden="true">•</span> <span>One uppercase letter</span>`);
      }
      $$renderer3.push(`<!--]--></li> <li class="flex items-center gap-2">`);
      if (requirements().hasLowercase) {
        $$renderer3.push("<!--[0-->");
        Check($$renderer3, { class: "size-4 shrink-0 text-primary", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span class="text-primary">One lowercase letter</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<span class="size-4 shrink-0" aria-hidden="true">•</span> <span>One lowercase letter</span>`);
      }
      $$renderer3.push(`<!--]--></li> <li class="flex items-center gap-2">`);
      if (requirements().hasNumber) {
        $$renderer3.push("<!--[0-->");
        Check($$renderer3, { class: "size-4 shrink-0 text-primary", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span class="text-primary">One number</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<span class="size-4 shrink-0" aria-hidden="true">•</span> <span>One number</span>`);
      }
      $$renderer3.push(`<!--]--></li></ul> `);
      if (pwErrorText()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p${attr("id", `${stringify(id)}-error`)} class="text-sm text-destructive">${escape_html(pwErrorText())}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (showConfirmation) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="space-y-2"><label${attr("for", `${stringify(id)}-confirmation`)} class="text-sm font-medium leading-none">${escape_html(confirmationLabel)}</label> <div class="relative">`);
        Input($$renderer3, {
          id: `${stringify(id)}-confirmation`,
          type: showConfirmationPassword ? "text" : "password",
          placeholder: "••••••••",
          autocomplete: "new-password",
          required: true,
          "aria-invalid": !!confErrorText(),
          "aria-describedby": confErrorText() ? `${id}-confirmation-error` : `${id}-confirmation-match`,
          class: "pr-10",
          get value() {
            return passwordConfirmation;
          },
          set value($$value) {
            passwordConfirmation = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          type: "button",
          variant: "ghost",
          size: "icon-sm",
          class: "absolute right-1 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground",
          onclick: () => showConfirmationPassword = !showConfirmationPassword,
          "aria-label": showConfirmationPassword ? "Hide password" : "Show password",
          children: ($$renderer4) => {
            if (showConfirmationPassword) {
              $$renderer4.push("<!--[0-->");
              Eye_off($$renderer4, { class: "size-4" });
            } else {
              $$renderer4.push("<!--[-1-->");
              Eye($$renderer4, { class: "size-4" });
            }
            $$renderer4.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> `);
        if (passwordConfirmation.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p${attr("id", `${stringify(id)}-confirmation-match`)}${attr_class("text-sm", void 0, { "text-primary": match(), "text-destructive": !match() })}>${escape_html(match() ? "Passwords match" : "Passwords don't match")}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (confErrorText()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p${attr("id", `${stringify(id)}-confirmation-error`)} class="text-sm text-destructive">${escape_html(confErrorText())}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { password, passwordConfirmation });
  });
}
export {
  PasswordStrengthInput as P
};
