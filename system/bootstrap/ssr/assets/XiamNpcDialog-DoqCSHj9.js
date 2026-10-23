import { b as attr, c as attr_class, e as escape_html, s as stringify, a as ensure_array_like, l as bind_props, d as derived } from "../app.js";
/* empty css                                                        */
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { X } from "./x-CnxrMbMn.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
const FALLBACK_FRAME = "/assets/xiam/emotions/v2/frames/idle-open.png";
function XiamAvatar($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      label = "Xiam assistant",
      size = 64
    } = $$props;
    let displayedFrame = FALLBACK_FRAME;
    $$renderer2.push(`<img class="xiam-avatar svelte-1kj6a6c"${attr("src", displayedFrame)}${attr("width", size)}${attr("height", size)}${attr("alt", label)} onerror="this.__e=event"/>`);
  });
}
function TourTriggerGlyph($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { tourKey, onclick, label = "Xiam Guide & Tour" } = $$props;
    $$renderer2.push(`<button type="button"${attr_class(
      `relative inline-flex size-8 shrink-0 items-center justify-center rounded-full text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${"opacity-35 transition-opacity hover:opacity-100"}`,
      "svelte-m6oanl"
    )}${attr("aria-label", label)}${attr("title", label)}>`);
    Circle_question_mark($$renderer2, { class: "size-4.5" });
    $$renderer2.push(`<!----> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></button>`);
  });
}
const PHASE_DEFAULT_EMOTIONS = {
  overview: "hi",
  orientation: "hi",
  concept: "thinking",
  workflow: "talking-presenting",
  action: "talking-presenting",
  guidance: "thumbs-up",
  milestone: "celebrating",
  diagnostic: "shock",
  recovery: "sad"
};
const PERSONA_CONFIGS = {
  "staff-copilot": {
    badgeLabel: "Xiam • Staff Co-pilot",
    badgeClass: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20",
    bubbleBorderClass: "border-emerald-500/25 bg-card/80 backdrop-blur-xs shadow-xs",
    accentClass: "text-primary",
    avatarContainerClass: ""
  },
  "applicant-mentor": {
    badgeLabel: "Xiam • Student Guide",
    badgeClass: "bg-amber-500/15 text-amber-800 dark:text-amber-300 border border-amber-500/25",
    bubbleBorderClass: "border-amber-500/30 bg-card/80 backdrop-blur-xs shadow-xs",
    accentClass: "text-amber-600 dark:text-amber-400",
    avatarContainerClass: ""
  },
  "proctor-guide": {
    badgeLabel: "Xiam • Proctor Guide",
    badgeClass: "bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/25",
    bubbleBorderClass: "border-blue-500/30 bg-card/80 backdrop-blur-xs shadow-xs",
    accentClass: "text-blue-600 dark:text-blue-400",
    avatarContainerClass: ""
  }
};
function resolveStepEmotion(stepEmotion, phase, _persona) {
  if (stepEmotion) return stepEmotion;
  if (phase && PHASE_DEFAULT_EMOTIONS[phase]) return PHASE_DEFAULT_EMOTIONS[phase];
  return "idle";
}
function evaluateValue(value, context) {
  if (value === void 0) return void 0;
  if (typeof value === "function") {
    return value(context);
  }
  return value;
}
function resolveTour(source, context) {
  const ctx = context ?? {
    entityKey: "global",
    safetyKey: "default",
    freshness: "current"
  };
  const def = typeof source === "function" ? source(ctx) : source;
  const variant = def.variant ?? "default";
  const entityKey = ctx.entityKey ?? "global";
  const safetyKey = ctx.safetyKey ?? "default";
  const instanceKey = `${def.id}:r${def.revision}:${variant}:${entityKey}:${safetyKey}`;
  const stepInclusion = /* @__PURE__ */ new Map();
  for (const step of def.steps) {
    if (step.when && !step.when(ctx)) {
      stepInclusion.set(step.id, {
        included: false,
        otherwise: step.otherwise ?? { kind: "complete" }
      });
    } else {
      stepInclusion.set(step.id, { included: true });
    }
  }
  function resolveDestination(dest, visited = /* @__PURE__ */ new Set()) {
    if (dest.kind === "complete") return dest;
    const info = stepInclusion.get(dest.stepId);
    if (!info || info.included) return dest;
    if (visited.has(dest.stepId)) {
      return { kind: "complete" };
    }
    visited.add(dest.stepId);
    return resolveDestination(info.otherwise ?? { kind: "complete" }, visited);
  }
  const resolvedSteps = [];
  for (const step of def.steps) {
    const info = stepInclusion.get(step.id);
    if (!info?.included) continue;
    const resolvedTitle = evaluateValue(step.title, ctx) ?? "";
    const resolvedText = evaluateValue(step.text, ctx) ?? "";
    const resolvedNotice = evaluateValue(step.notice, ctx) ?? null;
    const resolvedTarget = evaluateValue(step.target, ctx) ?? null;
    let resolvedAdvance;
    if (step.advance.kind === "next") {
      resolvedAdvance = {
        kind: "next",
        destination: resolveDestination(step.advance.destination)
      };
    } else {
      const validOptions = [];
      for (const opt of step.advance.options) {
        if (opt.when && !opt.when(ctx)) continue;
        const label = evaluateValue(opt.label, ctx) ?? "";
        validOptions.push({
          id: opt.id,
          label,
          destination: resolveDestination(opt.destination)
        });
      }
      if (validOptions.length > 0) {
        resolvedAdvance = {
          kind: "choices",
          options: validOptions
        };
      } else {
        const fallback = step.advance.fallback ?? { kind: "complete" };
        resolvedAdvance = {
          kind: "next",
          destination: resolveDestination(fallback)
        };
      }
    }
    resolvedSteps.push({
      id: step.id,
      phase: step.phase ?? "action",
      title: resolvedTitle,
      text: resolvedText,
      emotion: resolveStepEmotion(step.emotion, step.phase, step.personaOverride ?? def.persona),
      personaOverride: step.personaOverride,
      speechMode: step.speechMode ?? "typewriter",
      notice: resolvedNotice,
      target: resolvedTarget,
      advance: resolvedAdvance
    });
  }
  const resolvedStartDest = resolveDestination({ kind: "step", stepId: def.start });
  const resolvedStart = resolvedStartDest.kind === "step" ? resolvedStartDest.stepId : resolvedSteps[0]?.id ?? "";
  return {
    id: def.id,
    revision: def.revision,
    variant,
    instanceKey,
    title: def.title,
    domain: def.domain,
    persona: def.persona,
    start: resolvedStart,
    diagnostic: def.diagnostic,
    notice: def.notice ?? null,
    steps: resolvedSteps
  };
}
const done$m = { kind: "complete" };
function buildAdmissionSupportTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Admission support",
      text: "Open TabFM to manage program benchmarks. The empty model slot explains how to request another model.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "authority-concept" } }
    },
    {
      id: "authority-concept",
      phase: "concept",
      title: "Staff make the decision",
      text: "TabFM compares applicant results with program benchmarks. Its results support counseling; they do not admit or reject an applicant.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "benchmark-status-badge" },
      advance: { kind: "next", destination: { kind: "step", stepId: "studios-workflow" } }
    },
    {
      id: "studios-workflow",
      phase: "workflow",
      title: "Prepare and review benchmarks",
      text: "Choose a program in TabFM and save your changes as a draft. Guidance reviews the exact draft before publishing. A published version needs a separate action before it is used.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "tabfm-studio-card" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Check what is in use",
      text: "The status shows whether the saved benchmarks need review. Open Previous versions to see published versions. Saving a draft does not change the version in use.",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$m }
    }
  ];
  return {
    id: "admission-support",
    revision: 3,
    title: "Admission support guide",
    domain: "setup",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const admissionSupportTour = buildAdmissionSupportTour;
const done$l = { kind: "complete" };
function buildApplicantAccessKeySafetyTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Application Submitted Successfully",
      text: "Huge congratulations! Taking that first step and applying for college takes courage, and you did it! Before you dive into your portal, let's make sure your keys to the campus stay super safe and sound.",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "key-concept" } }
    },
    {
      id: "key-concept",
      phase: "concept",
      title: "Your 8-Character Digital Keycard",
      text: "NEXIAM uses a modern passwordless access key instead of passwords you might forget. Your 8-character code (like ABCD-1234) works together with your birthdate as your private two-factor keycard for all future admissions steps.",
      emotion: "searching-phone",
      speechMode: "typewriter",
      target: { dataTour: "access-key-display" },
      advance: { kind: "next", destination: { kind: "step", stepId: "safety-workflow" } }
    },
    {
      id: "safety-workflow",
      phase: "workflow",
      title: "Personal Phone vs. Shared Cafe PC",
      text: "Using your own phone or laptop? Click 'Remember on this device' for instant logins. But if you are on a shared computer at a cafe or library, never save it—jot it down in a private notebook or snap a clear photo with your phone!",
      emotion: "searching-phone",
      speechMode: "typewriter",
      target: { dataTour: "access-key-save" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Ready to Explore Your Portal",
      text: "We also sent a backup copy of your access info to your email. Whenever you are ready, tap 'Go to Applicant Portal' to track your application review and get set for exam day. We're with you all the way!",
      emotion: "hi",
      speechMode: "typewriter",
      target: { dataTour: "access-key-sign-in" },
      advance: { kind: "next", destination: done$l }
    }
  ];
  return {
    id: "applicant-access-key-safety",
    revision: 2,
    title: "Access Key Safety & Portal Onboarding",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantAccessKeySafetyTour = buildApplicantAccessKeySafetyTour;
const done$k = { kind: "complete" };
function buildApplicantApplyGuideTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Welcome to NEXIAM Admissions",
      text: "Mabuhay! I am Kuya Xiam, your admissions guide. Applying online is straightforward. Fill out your personal details, academic background, and program choices to receive your official access key.",
      emotion: "hi",
      speechMode: "typewriter",
      target: { dataTour: "apply-header" },
      advance: { kind: "next", destination: { kind: "step", stepId: "personal-identity" } }
    },
    {
      id: "personal-identity",
      phase: "concept",
      title: "Your Official Identity & Access Key",
      text: "Enter your legal name, birthdate, and contact details. When you submit, NEXIAM issues a secure 8-character access key (format ABCD-EFGH). Along with your birthdate, that key lets you sign into the applicant portal anytime on shared or personal devices.",
      emotion: "searching-phone",
      speechMode: "typewriter",
      target: { dataTour: "apply-personal-info" },
      advance: { kind: "next", destination: { kind: "step", stepId: "academic-grades" } }
    },
    {
      id: "academic-grades",
      phase: "workflow",
      title: "GWA & Optional Subject Grades",
      text: "Your General Weighted Average (GWA) is entered here. Subject grades (Math, Science, English, etc.) are completely optional and used as advisory evidence for Guidance consultation. You may leave them blank or update them later while your application is pending!",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "apply-academic-background" },
      advance: { kind: "next", destination: { kind: "step", stepId: "degree-preferences" } }
    },
    {
      id: "degree-preferences",
      phase: "guidance",
      title: "Degree Choices & Next Steps",
      text: "Select your 1st, 2nd, and 3rd preferred degree programs. Review and accept the Privacy Policy to submit your application. Hard copy Form 138 / TOR documents will still be verified in person at the Registrar.",
      emotion: "celebrating",
      speechMode: "typewriter",
      target: { dataTour: "apply-program-preferences" },
      advance: { kind: "next", destination: done$k }
    }
  ];
  return {
    id: "applicant-apply-guide",
    revision: 1,
    title: "Admission Application Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantApplyGuideTour = buildApplicantApplyGuideTour;
const done$j = { kind: "complete" };
function buildApplicantConsultationTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Guidance Consultation & Advising",
      text: "Mabuhay! Post-exam consultation is your personal session with a Guidance Counselor to discuss your entrance exam results, degree program options, and career goals.",
      emotion: "hi",
      speechMode: "typewriter",
      target: { dataTour: "consultation-header" },
      advance: { kind: "next", destination: { kind: "step", stepId: "schedule-concept" } }
    },
    {
      id: "schedule-concept",
      phase: "concept",
      title: "Booking or Staff Assignment",
      text: "Guidance may assign you directly to a consultation session, or open sessions here for self-booking. When slots open, you can pick a convenient date and time.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "consultation-progress" },
      advance: { kind: "next", destination: { kind: "step", stepId: "preparation-concept" } }
    },
    {
      id: "preparation-concept",
      phase: "workflow",
      title: "What to Bring for Counseling",
      text: "Bring your physical Form 138 / TOR report card and a valid photo ID to your consultation. Your counselor will explore program compatibility with you and record your degree endorsement.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "consultation-content" },
      advance: { kind: "next", destination: done$j }
    }
  ];
  return {
    id: "applicant-consultation",
    revision: 1,
    title: "Consultation & Advising Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantConsultationTour = buildApplicantConsultationTour;
const done$i = { kind: "complete" };
function buildApplicantScheduleTour(c) {
  const hasAppointment = c?.pageState === "assigned_published" && c.appointment !== null;
  const canReserve = c?.pageState === "calendar_open" && c.availableSlotCount > 0;
  const schedulingText = hasAppointment ? c?.appointment?.source === "applicant_reserved" ? "You reserved this exam slot. Check its publication status and appointment details below." : "Guidance assigned your exam slot. Check its publication status and appointment details below." : canReserve ? "Choose an available exam slot from the calendar, then confirm your reservation. Staff may also assign applicants directly. Campus emergencies may still require rescheduling." : c?.message || "Check your current scheduling status here. Reservation is available only when Guidance opens an eligible session for booking.";
  const appointmentText = hasAppointment ? c?.appointment?.published ? "Your appointment card shows the exam date, start and end time, and assigned room. Check these details before planning your trip." : "Your assignment is not yet published. Check back for the official schedule before treating these details as final." : "An appointment card appears after an exam slot is assigned or reserved. Until then, follow the current status message on this page.";
  const seatingInstruction = c?.hasAssignedSeatInstruction === true && hasAppointment ? " Follow staff-provided assigned-seat instructions at the venue." : "";
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Your Entrance Exam Schedule",
      text: "This page shows your current exam scheduling status. When an appointment is available, check its date, time, room, and publication status here.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "scheduling-concept" } }
    },
    {
      id: "scheduling-concept",
      phase: "concept",
      title: "How Exam Scheduling Works",
      text: schedulingText,
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "exam-schedule-progress" },
      advance: { kind: "next", destination: { kind: "step", stepId: "appointment-workflow" } }
    },
    {
      id: "appointment-workflow",
      phase: "workflow",
      title: "Checking Your Slot & Room Details",
      text: appointmentText,
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "exam-schedule-appointment" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Your Exam Day Checklist",
      text: `Follow the official exam notice and Guidance instructions for arrival time and what to bring. Check this page for schedule changes before travelling.${seatingInstruction}`,
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$i }
    }
  ];
  return {
    id: "applicant-exam-schedule",
    revision: 4,
    title: "Exam Scheduling & Test Day Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantExamScheduleTour = buildApplicantScheduleTour;
const done$h = { kind: "complete" };
function buildApplicantMyApplicationTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Your Official Application Record",
      text: "Here is your official story on paper! This page holds everything the admissions team knows about your academic journey so far—your grades, your senior high track, and your contact info. Think of it as your admissions mirror.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "review-concept" } }
    },
    {
      id: "review-concept",
      phase: "concept",
      title: "How Document Checks Work",
      text: "Friendly admissions evaluators double-check your grades and strand against your Form 138 report card. Once verified, it gets locked in safely for test day. If anything looks blurry, a helpful notice pops up right here so you can re-upload.",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "my-application-details" },
      advance: { kind: "next", destination: { kind: "step", stepId: "preferences-workflow" } }
    },
    {
      id: "preferences-workflow",
      phase: "workflow",
      title: "Your Program Wishlist",
      text: "These are your 1st, 2nd, and 3rd choice college majors! After you take your entrance exam, your counselor uses this exact wishlist to match your exam strengths with the degree program you love most.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "my-application-preferences" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "What Comes Next?",
      text: "After your application is accepted, check Exam Scheduling on the main portal. Guidance may assign your exam slot, or open eligible sessions for you to reserve. Follow the current status shown there.",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$h }
    }
  ];
  return {
    id: "applicant-my-application",
    revision: 3,
    title: "Application Record & Verification Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantMyApplicationTour = buildApplicantMyApplicationTour;
const done$g = { kind: "complete" };
function buildApplicantPortalTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Welcome to Your Admission Journey",
      text: "Take a deep breath—you made it to your NEXIAM portal! Think of this as your personal admissions compass. From your first document check to test day and enrollment, I'm right here beside you. You never have to guess what comes next.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "milestones" } }
    },
    {
      id: "milestones",
      phase: "concept",
      title: "Your Step-by-Step Roadmap",
      text: "Your journey moves through a clear sequence of milestones: Application Review, Exam Scheduling, Test Day, Results, Advising, and Enrollment Clearance. As each step is verified, the next one unlocks automatically!",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "applicant-stages-list" },
      advance: { kind: "next", destination: { kind: "step", stepId: "action-cards" } }
    },
    {
      id: "action-cards",
      phase: "workflow",
      title: "Action Cards & Live Updates",
      text: "Whenever an action is ready for you—like picking an exam slot or confirming your major—the active card will light up with a green button. The notification bell above will also ping you with official updates from the Registrar.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "applicant-active-stage" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "You've Got This!",
      text: "No need to stress or constantly refresh—your progress is saved safely. Keep your 8-character Access Key somewhere private. If you ever feel stuck, click this tour glyph anytime. We are cheering you on!",
      emotion: "searching-phone",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$g }
    }
  ];
  return {
    id: "applicant-portal",
    revision: 2,
    title: "Applicant Portal Journey Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantPortalTour = buildApplicantPortalTour;
const done$f = { kind: "complete" };
function buildApplicantProgramChoiceTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Choosing Your College Major",
      text: "This is the really exciting part—where your dreams turn into your actual college schedule! Choosing your degree is about matching your unique talents with programs where you can truly thrive.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "qualification-concept" } }
    },
    {
      id: "qualification-concept",
      phase: "concept",
      title: "How Program Matching Works",
      text: "Degree qualification brings together your entrance exam results, senior high strand, and available departmental slots. Your guidance counselor looks at all of these with you to recommend pathways where you will shine brightest.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "program-choice-status" },
      advance: { kind: "next", destination: { kind: "step", stepId: "advising-workflow" } }
    },
    {
      id: "advising-workflow",
      phase: "workflow",
      title: "Recommendations & Alternative Paths",
      text: "When your counselor completes your advising review, their recommended program lights up here with an 'Acknowledge' button. You can also explore alternative degree options that still have open student seats!",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "program-choice-default" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Locking In for Enrollment",
      text: "Take your time discussing choices with family and trusted teachers. Once you confirm your program, the Registrar prepares your final enrollment clearance. We can't wait to see you on campus as an official student!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$f }
    }
  ];
  return {
    id: "applicant-program-choice",
    revision: 2,
    title: "Degree Program Choice & Advising Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantProgramChoiceTour = buildApplicantProgramChoiceTour;
const done$e = { kind: "complete" };
function buildApplicantQrTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Your NEXIAM QR",
      text: "This code identifies your admission attempt when NEXIAM staff look up your record. Show it when asked at the consultation front desk, registrar counter, or staff scan.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "pass-concept" } }
    },
    {
      id: "pass-concept",
      phase: "concept",
      title: "One Pass for the Entire Journey",
      text: "The same permanent code identifies your admission attempt. It contains no name, scores, program, action, or permission. Staff still check your current record and appointment status.",
      emotion: "searching-phone",
      speechMode: "typewriter",
      target: { dataTour: "qr-card" },
      advance: { kind: "next", destination: { kind: "step", stepId: "print-workflow" } }
    },
    {
      id: "print-workflow",
      phase: "workflow",
      title: "Save to Phone & Print a Backup",
      text: "Snap a screenshot or tap 'Print approved copy'. Pro-tip: definitely print out a paper backup copy to slip into your exam folder, just in case your phone battery decides to take an unexpected nap on test morning!",
      emotion: "searching-phone",
      speechMode: "typewriter",
      target: { dataTour: "qr-print-button" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Keep It Personal & Safe",
      text: "Keep your QR copy private and show it only when asked by NEXIAM staff. The code is a record locator, not permission to enter an exam room or access private results.",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$e }
    }
  ];
  return {
    id: "applicant-qr",
    revision: 3,
    title: "NEXIAM QR Guide",
    domain: "applicant",
    persona: "applicant-mentor",
    start: "welcome",
    steps
  };
}
const applicantQrTour = buildApplicantQrTour;
const done$d = { kind: "complete" };
function buildGradingDashboardTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Assessment & Grading Operations Hub",
      text: "Welcome to the Grading Operations Hub! This is where raw question-level bubble reads are transformed into standardized cognitive scores, percentiles, and composite institutional ratings.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "scoring-concept" } }
    },
    {
      id: "scoring-concept",
      phase: "concept",
      title: "Aptitude Areas & Composite Ratings",
      text: "NEXIAM aggregates scores across defined sub-test areas (e.g. Abstract Reasoning, Verbal, Quantitative). Raw points are normalized and weighted according to university senate approved grading rubrics.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "grading-summary-card" },
      advance: { kind: "next", destination: { kind: "step", stepId: "review-workflow" } }
    },
    {
      id: "review-workflow",
      phase: "workflow",
      title: "Reviewing Score Distributions & Quality Checks",
      text: "Browse examinee score cards, inspect score distributions, and verify grading consistency across sessions. Any automated scoring anomalies or manual review flags can be investigated before finalizing batch releases.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "grading-session-list" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Scoring Immutability & Audit Trail",
      text: "Once raw grading is locked, every student score is permanently recorded with cryptographic provenance. This guarantees that student percentiles remain mathematically sound and dispute-proof!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$d }
    }
  ];
  return {
    id: "assessment-grading-dashboard",
    revision: 2,
    title: "Assessment Scoring & Quality Control Guide",
    domain: "examinations",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const assessmentGradingDashboardTour = buildGradingDashboardTour;
const done$c = { kind: "complete" };
function buildOmrScannerTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "High-Speed OMR Camera Scanner Station",
      text: "Welcome to the OMR Camera Scanning Station! This browser-native scanner uses computer vision to grade physical answer sheets in milliseconds using any standard webcam or document camera.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "vision-concept" } }
    },
    {
      id: "vision-concept",
      phase: "concept",
      title: "Fiducials, Rectification, & Darkness Density",
      text: "The scanner automatically detects the four black corner fiducials to rectify camera tilt and skew. It then samples each bubble’s pixel darkness against the background paper to distinguish intentional marks from smudges or eraser marks.",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "omr-viewfinder" },
      advance: { kind: "next", destination: { kind: "step", stepId: "capture-workflow" } }
    },
    {
      id: "capture-workflow",
      phase: "workflow",
      title: "Operating the Camera Viewfinder",
      text: "Place the student’s answer sheet within the green viewfinder boundary. Hold it steady under even lighting without heavy overhead shadows. The scanner emits an audio chime and immediately projects the verified score upon successful read!",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "omr-capture-controls" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Handling Smudges & Display Code Overrides",
      text: "If a student’s examinee number bubble has a smudge, the scanner pauses for human confirmation. Proctors can visually inspect the high-resolution crop and verify the physical Display Code without discarding the student’s exam!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$c }
    }
  ];
  return {
    id: "assessment-omr-camera-scanner",
    revision: 2,
    title: "OMR Camera Scanner Operations Guide",
    domain: "examinations",
    persona: "proctor-guide",
    start: "welcome",
    steps
  };
}
const assessmentOmrCameraScannerTour = buildOmrScannerTour;
const done$b = { kind: "complete" };
function buildConsultationHubTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Guidance Consultation & Triage Hub",
      text: "Welcome to the Guidance Consultation Hub! This workspace is dedicated to post-exam student advising, holistic career counseling, and academic placement recommendations.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "triage-concept" } }
    },
    {
      id: "triage-concept",
      phase: "concept",
      title: "Student Triage & Holistic Assessment",
      text: "Guidance counselors bridge the gap between entrance exam percentiles and student aspirations. The hub triages students into scheduled consultation slots, walk-in inquiries, and re-advising cases where department heads requested alternative degree tracks.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "consultation-kpis" },
      advance: { kind: "next", destination: { kind: "step", stepId: "advising-workflow" } }
    },
    {
      id: "advising-workflow",
      phase: "workflow",
      title: "Conducting Sessions & Recording Advising",
      text: "Open a student's counseling sheet to view their cognitive sub-test scores, senior high school background, and TabFM compatibility suggestions. Once you discuss career options with the student, click 'Submit Advisory' to record your official endorsement.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "consultation-queue" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Respecting Counselor Authority",
      text: "In NEXIAM, automated compatibility algorithms are strictly advisory tools. The human counselor holds 100% authority to recommend the degree program that best nurtures each student’s potential!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$b }
    }
  ];
  return {
    id: "consultation-hub",
    revision: 2,
    title: "Guidance Consultation & Triage Guide",
    domain: "consultation",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const consultationHubTour = buildConsultationHubTour;
const done$a = { kind: "complete" };
function buildConsultationSchedulingTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Counseling Shifts & Rostering Hub",
      text: "Welcome to the Consultation Scheduling Hub! This is where Guidance Directors manage counseling appointments, assign designated counselors to rooms, and establish student capacity.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "rostering-concept" } }
    },
    {
      id: "rostering-concept",
      phase: "concept",
      title: "Dedicated Counseling Windows",
      text: "To maintain meaningful, unhurried career advising, consultations are organized into specific daily blocks and room assignments. This prevents long waiting lines and ensures every applicant receives focused guidance.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "consultation-schedule-summary" },
      advance: { kind: "next", destination: { kind: "step", stepId: "booking-workflow" } }
    },
    {
      id: "booking-workflow",
      phase: "workflow",
      title: "Authoring & Publishing Sessions",
      text: "Click 'Create Session' to designate a counseling date, time window, room, and assigned counselor. Publishing sessions enables students who have received their exam scores to book an available consultation slot on their portal.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "consultation-sessions-table" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Balancing Triage & Walk-ins",
      text: "Ensure consultation slots are published ahead of exam release days. Reserving buffer slots for afternoon walk-ins helps counselors accommodate students traveling from distant provinces!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$a }
    }
  ];
  return {
    id: "consultation-scheduling",
    revision: 2,
    title: "Consultation Scheduling & Rostering Guide",
    domain: "consultation",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const consultationSchedulingTour = buildConsultationSchedulingTour;
const done$9 = { kind: "complete" };
function buildDataIngestionTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Examinee Data Ingestion & Staging Hub",
      text: "Welcome to the Data Ingestion Hub! This tool handles the secure, high-volume batch import of examinee records, high school masterlists, and external registration spreadsheets.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "staging-concept" } }
    },
    {
      id: "staging-concept",
      phase: "concept",
      title: "Staged Ingestion & De-duplication",
      text: "To protect the integrity of the live student database, NEXIAM uses a two-phase ingestion architecture: initial upload into an isolated staging buffer, followed by automated schema validation and de-duplication before final commitment.",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "data-ingestion-uploader" },
      advance: { kind: "next", destination: { kind: "step", stepId: "validation-workflow" } }
    },
    {
      id: "validation-workflow",
      phase: "workflow",
      title: "Uploading & Validating Spreadsheets",
      text: "Upload your CSV or Excel workbook to begin. The ingestion engine automatically parses column headers, validates Philippine standard phone formats and birthdates, and flags any conflicting duplicate records for manual review.",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "data-ingestion-preview" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Transactional Safety & Error Rollbacks",
      text: "Imports execute within atomic database transactions. If an invalid row or corrupted record is detected, the engine pinpoints the exact row number, ensuring that faulty data is never written to the live institutional register!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$9 }
    }
  ];
  return {
    id: "data-ingestion",
    revision: 2,
    title: "Data Ingestion & Staging Operations Guide",
    domain: "setup",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const dataIngestionTour = buildDataIngestionTour;
const done$8 = { kind: "complete" };
function buildEnrollmentEligibilityTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Admission Clearance & Eligibility Hub",
      text: "Welcome to the Admission Clearance & Eligibility Hub! Here, authorized Registrar staff verify candidate prerequisites, review physical intake documents, and issue official admission clearance.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "pre-clearance-milestones" } }
    },
    {
      id: "pre-clearance-milestones",
      phase: "concept",
      title: "1. Pre-Clearance Milestones",
      text: "Before admission clearance can proceed, prerequisite milestones must be satisfied: Examination (session attended), Result (scores published), and Advising / Consultation (counselor endorsement and applicant program selection acknowledged).",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "pre-clearance-milestones" },
      advance: { kind: "next", destination: { kind: "step", stepId: "document-requirements" } }
    },
    {
      id: "document-requirements",
      phase: "workflow",
      title: "2. Document Requirements Panel",
      text: "Inspect and verify the applicant’s physical intake documents (such as Form 138, PSA Birth Certificate, Certificate of Good Moral Character, and 2x2 Photos). All mandatory requirements must be verified before clearance can be confirmed.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "document-requirements-panel" },
      advance: { kind: "next", destination: { kind: "step", stepId: "confirm-clearance" } }
    },
    {
      id: "confirm-clearance",
      phase: "workflow",
      title: "3. Confirming Admission Clearance",
      text: "Once all milestones and mandatory documents are verified, click Confirm Admission Clearance. This records an immutable clearance receipt, issues the official clearance record, and locks prerequisite mutations.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "admission-clearance-action" },
      advance: { kind: "next", destination: { kind: "step", stepId: "record-handoff" } }
    },
    {
      id: "record-handoff",
      phase: "guidance",
      title: "4. Physical Enrollment Sheet Handoff",
      text: "After admission clearance is confirmed, record the Physical Enrollment Sheet Handoff when handing the printed sheet to the candidate at the Registrar desk. This captures serial numbers and paper references for complete auditability.",
      emotion: "celebrating",
      speechMode: "typewriter",
      target: { dataTour: "sheet-handoff-action" },
      advance: { kind: "next", destination: done$8 }
    }
  ];
  return {
    id: "enrollment-eligibility",
    revision: 2,
    title: "Enrollment Eligibility & Clearance Guide",
    domain: "consultation",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const enrollmentEligibilityTour = buildEnrollmentEligibilityTour;
const done$7 = { kind: "complete" };
function buildExamMaterialsTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Examination Materials & Security Hub",
      text: "Welcome to the Exam Materials & Printing Hub! This workspace manages physical and digital test artifacts—including scannable OMR answer sheets and answer keys for external question booklets.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "master-sheet-concept" } }
    },
    {
      id: "master-sheet-concept",
      phase: "concept",
      title: "The Master Answer Sheet Anchor",
      text: "In NEXIAM, booklets link to a calibrated Master Answer Sheet template. The answer sheet defines the geometric bubble grid, timing fiducials, and sub-test sections (e.g. Science, Mathematics, Language Proficiency).",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "exam-materials-templates" },
      advance: { kind: "next", destination: { kind: "step", stepId: "booklet-workflow" } }
    },
    {
      id: "booklet-workflow",
      phase: "workflow",
      title: "Answer Key Booklets & Scoring Keys",
      text: "Click 'Open Booklets' to manage the catalog and create answer-key booklets linked to your active Answer Sheet. Since current booklets are answer-key only by default, questions remain in your externally printed paper booklets while staff enter and map the scoring keys (A, B, C, D) in the workspace for automated OMR grading. Each booklet version undergoes validation to ensure answer keys match question counts exactly.",
      emotion: "searching-paper",
      speechMode: "typewriter",
      target: { dataTour: "booklets-inventory" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Material Custody & Finalization",
      text: "Only finalize booklets once answer sheet calibrations are locked in. Master answer keys remain securely stored until scoring sessions begin, preserving absolute examination confidentiality!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$7 }
    }
  ];
  return {
    id: "exam-materials",
    revision: 2,
    title: "Exam Materials & Answer Key Guide",
    domain: "examinations",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const examMaterialsTour = buildExamMaterialsTour;
const done$6 = { kind: "complete" };
function buildExamMonitoringTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Live Exam Day Monitoring Console",
      text: "Welcome to the Live Exam Monitoring Console! This is mission control on test day, providing real-time oversight of room check-ins, proctor attendance, and candidate flow across all campus buildings.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "attendance-concept" } }
    },
    {
      id: "attendance-concept",
      phase: "concept",
      title: "Attendance Telemetry & Irregularity Logging",
      text: "As proctors scan candidate QR passes at room entrances, attendance telemetry updates instantly. The system tracks verified arrivals, unconfirmed candidates, and room irregularities in real time.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "exam-monitoring-kpis" },
      advance: { kind: "next", destination: { kind: "step", stepId: "shifts-workflow" } }
    },
    {
      id: "shifts-workflow",
      phase: "workflow",
      title: "Managing Rooms & Shift Timers",
      text: "Monitor each testing room’s status: Standby, In Progress, or Concluded. Room supervisors can log operational incident reports—such as power fluctuations or booklet replacements—with exact timestamps for institutional auditability.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "exam-monitoring-rooms-grid" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Post-Exam Handover to Grading",
      text: "Once a session concludes, proctors verify physical answer sheet counts against verified attendee tallies before sealing the test packets. Concluded sessions flow seamlessly into the OMR grading stations!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$6 }
    }
  ];
  return {
    id: "exam-monitoring",
    revision: 2,
    title: "Live Exam Monitoring & Operations Guide",
    domain: "examinations",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const examMonitoringTour = buildExamMonitoringTour;
const done$5 = { kind: "complete" };
function buildExamSessionsTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Examination Sessions & Scheduling Hub",
      text: "Welcome to the Examination Sessions Hub! This is where admissions officers structure examination dates, allocate testing rooms, and manage candidate capacity across the testing window.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "capacity-concept" } }
    },
    {
      id: "capacity-concept",
      phase: "concept",
      title: "Rooms, Shifts, & Capacity Invariants",
      text: "Each examination session is bound to a physical room, building, proctor staff, and time window (Morning AM or Afternoon PM). NEXIAM strictly enforces room capacity limits to prevent overcrowding and maintain standardized testing conditions.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "exam-sessions-summary" },
      advance: { kind: "next", destination: { kind: "step", stepId: "distribution-workflow" } }
    },
    {
      id: "distribution-workflow",
      phase: "workflow",
      title: "Publication & Cohort Auto-Distribution",
      text: "Sessions start in 'Draft' mode while rooms are being coordinated. Once finalized, clicking 'Publish' locks in the schedule and opens the seats for examinee booking. You can also use the 'Cohort Auto-Distribute' tool to automatically distribute unassigned applicants evenly across published sessions without manual data entry!",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "exam-sessions-table" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Best Practices for Exam Scheduling",
      text: "Always ensure room capacities match physical desk counts before publishing. Published sessions automatically integrate into live Exam Monitoring and applicant QR check-in on test day!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$5 }
    }
  ];
  return {
    id: "exam-sessions",
    revision: 2,
    title: "Exam Sessions & Capacity Planning Guide",
    domain: "examinations",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const examSessionsTour = buildExamSessionsTour;
const done$4 = { kind: "complete" };
function buildProgramHeadOverviewTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Departmental Degree Quota & Review Hub",
      text: "Welcome to the Program Head Overview! This executive workspace allows academic department heads and college deans to monitor applicant demand, review endorsements, and manage program quota limits.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "quota-concept" } }
    },
    {
      id: "quota-concept",
      phase: "concept",
      title: "Program Quotas & Departmental Governance",
      text: "Each degree program has a maximum authorized intake quota set by the university board. Department heads review incoming student endorsements to maintain high academic standards while preventing over-enrollment.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "program-head-kpis" },
      advance: { kind: "next", destination: { kind: "step", stepId: "endorsement-workflow" } }
    },
    {
      id: "endorsement-workflow",
      phase: "workflow",
      title: "Reviewing Endorsements & Re-advising",
      text: "Inspect student profiles endorsed by guidance counselors. If a candidate meets your department's specific criteria, you can accept the endorsement for enrollment clearance. If program capacity is full, you can return the profile for re-advising to help the student find an open alternative.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "program-head-programs-grid" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Real-Time Capacity Protection",
      text: "Quota counters update immediately upon endorsement acceptance, ensuring transparent, fair placement across all academic colleges and departments!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$4 }
    }
  ];
  return {
    id: "program-head-overview",
    revision: 2,
    title: "Program Head & Quota Governance Guide",
    domain: "consultation",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const programHeadOverviewTour = buildProgramHeadOverviewTour;
const done$3 = { kind: "complete" };
function buildReleaseBatchReleasingTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Examination Results Release & Printing Hub",
      text: "Welcome to the Results Release Hub! This workspace coordinates the official publication of entrance exam scores and manages the batch printing of official student result slips.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "release-concept" } }
    },
    {
      id: "release-concept",
      phase: "concept",
      title: "Two-Stage Release Authorization",
      text: "Score release follows a deliberate two-stage protocol: Internal Grade Verification by Testing Officers, followed by Official Executive Release by the Registrar. Scores remain strictly confidential until the release batch is formally approved.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "release-batch-summary" },
      advance: { kind: "next", destination: { kind: "step", stepId: "printing-workflow" } }
    },
    {
      id: "printing-workflow",
      phase: "workflow",
      title: "Batch Selection & Template Printing",
      text: "Select a completed exam session to preview release candidate counts. You can trigger official digital portal releases and print physical result certificates using calibrated institutional print templates.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "release-batches-table" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Unlocking Downstream Advising",
      text: "Publishing an examination batch instantly unlocks the score view on applicant portals and allows students to schedule their post-exam guidance counseling and degree selection!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$3 }
    }
  ];
  return {
    id: "release-batch-releasing",
    revision: 2,
    title: "Results Release & Certificate Printing Guide",
    domain: "examinations",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const releaseBatchReleasingTour = buildReleaseBatchReleasingTour;
const done$2 = { kind: "complete" };
function buildSetupReadinessTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Institutional Setup & Readiness Hub",
      text: "Welcome to the Setup & Readiness Hub! This is where university administrators and committee heads verify that all institutional parameters, testing environments, and scoring policies are fully calibrated before admissions launch.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "pillars-concept" } }
    },
    {
      id: "pillars-concept",
      phase: "concept",
      title: "The Four Readiness Pillars",
      text: "Readiness in NEXIAM is organized around four core pillars: Academic Cycles (intake years & terms), Degree Programs (strands, cut-offs, & quotas), Examination Architecture (OMR templates, shifts, & rooms), and Scoring Rules (aptitude areas & weights). Every pillar must be validated to protect admissions integrity.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "readiness-summary-card" },
      advance: { kind: "next", destination: { kind: "step", stepId: "categories-workflow" } }
    },
    {
      id: "categories-workflow",
      phase: "workflow",
      title: "Navigating Categories & Resolving Checks",
      text: "Each readiness card shows the operational status of that category with a direct configuration link. If an item needs attention—such as an unassigned room or an unactivated result template—clicking into that category takes you straight to the authoring interface.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "readiness-groups-list" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Audit-Proof Launch Standards",
      text: "NEXIAM prevents accidental operational launch while critical prerequisites are incomplete. Once all categories reach 100% readiness, admissions intake can open with complete confidence that every applicant will experience a smooth, fair journey!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$2 }
    }
  ];
  return {
    id: "setup-readiness-hub",
    revision: 2,
    title: "Institutional Readiness & Setup Guide",
    domain: "setup",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const setupReadinessHubTour = buildSetupReadinessTour;
const done$1 = { kind: "complete" };
function buildStaffOperationsTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Institutional Operations Dashboard",
      text: "Welcome to the NEXIAM Institutional Operations Dashboard! This centralized console provides institutional leaders with a real-time, cross-departmental view of the active admissions cycle.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "funnel-concept" } }
    },
    {
      id: "funnel-concept",
      phase: "concept",
      title: "The Operational Intake Funnel",
      text: "Admissions operations function as an interconnected pipeline: from intake registrations and test scheduling, through live examination, scoring, guidance advising, and final enrollment clearance.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "staff-operations-funnel" },
      advance: { kind: "next", destination: { kind: "step", stepId: "metrics-workflow" } }
    },
    {
      id: "metrics-workflow",
      phase: "workflow",
      title: "Cross-Departmental Metrics & Alerts",
      text: "Review real-time conversion rates across each stage of the funnel. Quick-action links let you jump directly into active operational hubs—such as live exam monitoring or urgent counseling queues—wherever attention is needed.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "staff-operations-metrics" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Cohesive Multi-Office Coordination",
      text: "All metrics update in real time without manual spreadsheet reconciliation, fostering seamless collaboration between the Registrar, Testing Center, and Guidance Office!",
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done$1 }
    }
  ];
  return {
    id: "staff-operations-dashboard",
    revision: 2,
    title: "Staff Operations & Intake Funnel Guide",
    domain: "setup",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const staffOperationsDashboardTour = buildStaffOperationsTour;
const done = { kind: "complete" };
function buildXiamKnowledgeTour(c) {
  const steps = [
    {
      id: "welcome",
      phase: "overview",
      title: "Governed Knowledge & Rules Hub",
      text: "Welcome to the Xiam Knowledge & Rules Hub! This is the single source of truth for all institutional policies, admission requirements, exam guidelines, and public student FAQs across the university.",
      emotion: "hi",
      speechMode: "typewriter",
      advance: { kind: "next", destination: { kind: "step", stepId: "pipeline-concept" } }
    },
    {
      id: "pipeline-concept",
      phase: "concept",
      title: "The 4-Stage Governance Pipeline",
      text: "Every policy and rule passes through a strict 4-stage governance pipeline: Draft, Text Extraction, Supervisor Review, and Final Publication. AI models and applicant assistants can ONLY cite rules that have completed this full pipeline and bear an immutable cryptographic provenance hash.",
      emotion: "thinking",
      speechMode: "typewriter",
      target: { dataTour: "governance-pipeline" },
      advance: { kind: "next", destination: { kind: "step", stepId: "catalog-workflow" } }
    },
    {
      id: "catalog-workflow",
      phase: "workflow",
      title: "Filtering, Search, & Card Previews",
      text: "Use the search bar and category tabs above to browse active policies, student FAQs, and operational handbooks. You can toggle between rich Bento preview cards and a high-density tabular view depending on your inspection needs.",
      emotion: "talking-presenting",
      speechMode: "typewriter",
      target: { dataTour: "knowledge-catalog-filters" },
      advance: { kind: "next", destination: { kind: "step", stepId: "guidance" } }
    },
    {
      id: "guidance",
      phase: "guidance",
      title: "Authoring & Maintaining Rules",
      text: 'Authorized staff can click "New Knowledge Source" to draft updated admission guidelines. Once reviewed and published, Xiam instantly incorporates the new rules into applicant guidance and advisory consultations with zero downtime!',
      emotion: "celebrating",
      speechMode: "typewriter",
      advance: { kind: "next", destination: done }
    }
  ];
  return {
    id: "xiam-knowledge-governance",
    revision: 2,
    title: "Xiam Knowledge & Rules Governance Guide",
    domain: "setup",
    persona: "staff-copilot",
    start: "welcome",
    steps
  };
}
const xiamKnowledgeTour = buildXiamKnowledgeTour;
const tours = {
  "admission-support": admissionSupportTour,
  "applicant-access-key-safety": applicantAccessKeySafetyTour,
  "applicant-apply-guide": applicantApplyGuideTour,
  "applicant-consultation": applicantConsultationTour,
  "applicant-exam-schedule": applicantExamScheduleTour,
  "applicant-my-application": applicantMyApplicationTour,
  "applicant-portal": applicantPortalTour,
  "applicant-program-choice": applicantProgramChoiceTour,
  "applicant-qr": applicantQrTour,
  "assessment-grading-dashboard": assessmentGradingDashboardTour,
  "assessment-omr-camera-scanner": assessmentOmrCameraScannerTour,
  "consultation-hub": consultationHubTour,
  "consultation-scheduling": consultationSchedulingTour,
  "data-ingestion": dataIngestionTour,
  "enrollment-eligibility": enrollmentEligibilityTour,
  "exam-materials": examMaterialsTour,
  "exam-monitoring": examMonitoringTour,
  "exam-sessions": examSessionsTour,
  "program-head-overview": programHeadOverviewTour,
  "release-batch-releasing": releaseBatchReleasingTour,
  "setup-readiness-hub": setupReadinessHubTour,
  "staff-operations-dashboard": staffOperationsDashboardTour,
  "xiam-knowledge": xiamKnowledgeTour
};
function getTour(tourKey, context) {
  const raw = tours[tourKey];
  if (!raw) return null;
  return resolveTour(raw, context);
}
function XiamNpcDialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { tourKey, context = void 0, open = false } = $$props;
    const tour = derived(() => getTour(tourKey, context));
    let currentStepId = "";
    let history = [];
    let displayedText = "";
    const currentStep = derived(() => tour()?.steps.find((step) => step.id === currentStepId) ?? null);
    const stepNumber = derived(() => tour() ? Math.max(0, tour().steps.findIndex((step) => step.id === currentStepId) + 1) : 0);
    const activePersona = derived(() => currentStep()?.personaOverride ?? tour()?.persona ?? "staff-copilot");
    const personaConfig = derived(() => PERSONA_CONFIGS[activePersona()] ?? PERSONA_CONFIGS["staff-copilot"]);
    const stepPlaybackKey = derived(() => `${tour()?.instanceKey ?? tourKey}:${currentStepId}:${history.length}`);
    if (open && tour()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-6 md:p-8 bg-slate-950/65 backdrop-blur-md transition-all duration-200" role="presentation"><div class="relative flex w-full max-w-2xl flex-col justify-between overflow-hidden rounded-3xl border border-border/80 bg-card/90 backdrop-blur-xl p-6 shadow-2xl outline-none sm:min-h-[460px] sm:max-w-3xl sm:p-8 lg:max-w-4xl text-card-foreground craft-card-depth" role="dialog" aria-modal="true" aria-labelledby="xiam-tour-title" tabindex="-1">`);
      if (currentStep()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="flex flex-col gap-3.5 sm:gap-4.5"><header class="flex items-center justify-between gap-3"><div class="flex items-center gap-2.5">`);
        if (currentStep().phase) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="rounded-md border border-border/40 bg-muted/60 px-2 py-0.5 text-[11px] font-mono font-semibold uppercase tracking-wider text-muted-foreground">${escape_html(currentStep().phase)}</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> <span class="text-xs font-medium text-muted-foreground/80">Step ${escape_html(stepNumber())} of ${escape_html(tour().steps.length)}</span></div> <button type="button" class="inline-flex size-8 items-center justify-center rounded-full text-muted-foreground/80 hover:bg-muted/60 hover:text-foreground transition-colors" aria-label="Close tour" title="Close">`);
        X($$renderer2, { class: "size-4" });
        $$renderer2.push(`<!----></button></header> <h2 id="xiam-tour-title" class="text-xl sm:text-2xl font-bold tracking-tight text-foreground leading-snug">${escape_html(currentStep().title)}</h2> <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-end sm:gap-6 pt-1"><div class="shrink-0 flex items-end justify-center self-end sm:self-end"><div class="w-[130px] sm:w-[176px] flex items-end justify-center -mb-1 sm:-mb-2">`);
        XiamAvatar($$renderer2, {
          size: 180,
          label: `Xiam, ${stringify(currentStep().emotion)}`,
          heldEmotion: currentStep().emotion,
          playbackKey: stepPlaybackKey()
        });
        $$renderer2.push(`<!----></div></div> <div class="min-w-0 flex-1 w-full flex flex-col justify-end"><button type="button"${attr_class(`group relative flex flex-col justify-between w-full h-[168px] sm:h-[184px] cursor-text rounded-2xl border p-5 sm:p-6 text-left transition-all duration-200 ${personaConfig().bubbleBorderClass} hover:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/25 overflow-y-auto`)} aria-label="Tour explanation. Click to reveal all text immediately."><div class="grid grid-cols-1 w-full"><div class="invisible col-start-1 row-start-1 select-none pointer-events-none text-base sm:text-[1.0625rem] font-normal leading-relaxed sm:leading-8 tracking-normal whitespace-pre-line antialiased" aria-hidden="true">${escape_html(currentStep().text)}</div> <div class="col-start-1 row-start-1 text-base sm:text-[1.0625rem] text-foreground/90 font-normal leading-relaxed sm:leading-8 tracking-normal whitespace-pre-line antialiased">${escape_html(displayedText)}`);
        {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div></div> `);
        {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></button> <div class="mt-3.5 flex items-center gap-1.5 px-1" aria-hidden="true"><!--[-->`);
        const each_array = ensure_array_like(tour().steps);
        for (let idx = 0, $$length = each_array.length; idx < $$length; idx++) {
          let s = each_array[idx];
          $$renderer2.push(`<div${attr_class(`h-1.5 rounded-full transition-all duration-300 ${s.id === currentStepId ? "w-7 bg-primary shadow-xs" : idx < stepNumber() ? "w-2 bg-primary/40" : "w-2 bg-muted/80"}`)}${attr("title", `Step ${idx + 1}: ${s.title}`)}></div>`);
        }
        $$renderer2.push(`<!--]--></div></div></div></div> <footer class="mt-6 sm:mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-4 sm:pt-5">`);
        if (currentStep().advance.kind === "next") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="flex items-center gap-2"><button type="button" class="rounded-xl px-3.5 py-2 text-sm text-muted-foreground hover:bg-muted/70 hover:text-foreground transition-colors">Skip Tour</button> <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-primary/25 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/20 transition-all shadow-xs" title="Ask Xiam AI assistant about this topic">`);
          Sparkles($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----> <span>Ask Xiam AI</span></button></div> <div class="ml-auto flex items-center gap-2.5"><button type="button" class="inline-flex items-center gap-1 rounded-xl border border-border bg-muted/30 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40 transition-colors"${attr("disabled", history.length === 0, true)}>`);
          Chevron_left($$renderer2, { class: "size-4" });
          $$renderer2.push(`<!----> <span>Back</span></button> <button type="button" class="inline-flex items-center gap-1 rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm hover:opacity-95 active:scale-[0.99] transition-all"><span>${escape_html(currentStep().advance.destination.kind === "complete" ? "Complete Tour" : "Next Step")}</span> `);
          if (currentStep().advance.destination.kind !== "complete") {
            $$renderer2.push("<!--[0-->");
            Chevron_right($$renderer2, { class: "size-4" });
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></button></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<div class="flex items-center gap-2"><button type="button" class="rounded-xl px-3.5 py-2 text-sm text-muted-foreground hover:bg-muted/70 hover:text-foreground transition-colors">Skip Tour</button> <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-primary/25 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/20 transition-all shadow-xs" title="Ask Xiam AI assistant about this topic">`);
          Sparkles($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----> <span>Ask Xiam AI</span></button></div> <div class="ml-auto flex flex-wrap justify-end gap-2.5">`);
          if (history.length > 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<button type="button" class="inline-flex items-center gap-1 rounded-xl border border-border bg-muted/30 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors">`);
            Chevron_left($$renderer2, { class: "size-4" });
            $$renderer2.push(`<!----> <span>Back</span></button>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <!--[-->`);
          const each_array_1 = ensure_array_like(currentStep().advance.options);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let option = each_array_1[$$index_1];
            $$renderer2.push(`<button type="button" class="rounded-full border border-primary/30 bg-primary/10 px-5 py-2 text-sm font-semibold text-foreground hover:bg-primary/20 transition-all shadow-xs">${escape_html(option.label)}</button>`);
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]--></footer>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { open });
  });
}
export {
  TourTriggerGlyph as T,
  XiamNpcDialog as X
};
