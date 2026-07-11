let currentMode = "resume";
let latestReport = null;
let previewMode = "raw";
let activePreviewText = "";
let activePreviewLabel = "Live preview from resume text";
let activePreviewSections = null;
let authUser = null;

const form = document.getElementById("resumeForm");
const resumeText = document.getElementById("resumeText");
const resumeFile = document.getElementById("resumeFile");
const jobFields = document.getElementById("jobFields");
const jobTitle = document.getElementById("jobTitle");
const jobDescription = document.getElementById("jobDescription");
const tabs = document.querySelectorAll(".mode-tab");
const printButton = document.getElementById("printButton");
const loginButton = document.getElementById("loginButton");
const signupButton = document.getElementById("signupButton");
const logoutButton = document.getElementById("logoutButton");
const authGuestActions = document.getElementById("authGuestActions");
const authUserActions = document.getElementById("authUserActions");
const accountName = document.getElementById("accountName");
const accountEmail = document.getElementById("accountEmail");
const submitButton = form.querySelector("button[type='submit']");
const fileLabel = document.querySelector(".custom-file-label");
const previewButtons = document.querySelectorAll(".preview-mode");
const resumePreview = document.getElementById("resumePreview");
const previewSource = document.getElementById("previewSource");
const previewWords = document.getElementById("previewWords");
const previewLines = document.getElementById("previewLines");
const previewSectionsCount = document.getElementById("previewSectionsCount");
const previewFitScore = document.getElementById("previewFitScore");
const previewSectionChips = document.getElementById("previewSectionChips");
const previewOutline = document.getElementById("previewOutline");
const previewSignals = document.getElementById("previewSignals");
const recommendedResumePanel = document.getElementById("recommendedResumePanel");
const recommendedResume = document.getElementById("recommendedResume");
const copyRecommendedResume = document.getElementById("copyRecommendedResume");
const downloadRecommendedResume = document.getElementById("downloadRecommendedResume");
const downloadRecommendedResumePdf = document.getElementById("downloadRecommendedResumePdf");
const butlerLauncher = document.getElementById("butlerLauncher");
const openButlerNav = document.getElementById("openButlerNav");
const butlerPanel = document.getElementById("resumoButler");
const butlerClose = document.getElementById("butlerClose");
const butlerMessages = document.getElementById("butlerMessages");
const butlerForm = document.getElementById("butlerForm");
const butlerInput = document.getElementById("butlerInput");
const butlerActions = document.querySelectorAll("[data-butler-action]");

const sectionPatterns = {
  contact: /(\b[\w.%+-]+@[\w.-]+\.[a-z]{2,}\b)|(\+?\d[\d\s().-]{7,})|(linkedin\.com\/in\/)/i,
  summary: /\b(summary|profile|objective|professional summary|career summary)\b/i,
  experience: /\b(experience|employment|work history|professional experience)\b/i,
  education: /\b(education|degree|university|college|bachelor|master|phd|diploma)\b/i,
  skills: /\b(skills|technical skills|core competencies|technologies)\b/i,
  certifications: /\b(certifications?|licenses?|accreditations?)\b/i,
  projects: /\b(projects?|portfolio|selected work)\b/i,
  awards: /\b(awards?|honors?|achievements?)\b/i
};

const processSteps = [
  { id: "prepare", label: "Preparing resume inputs", target: 10 },
  { id: "upload", label: "Uploading resume", target: 28 },
  { id: "extract", label: "Extracting document content", target: 44 },
  { id: "score", label: "Scoring resume quality", target: 64 },
  { id: "ai", label: "Generating resume draft", target: 84 },
  { id: "report", label: "Building report", target: 96 }
];

let progressTimer = null;
let activeStepIndex = 0;
let progressPercent = 0;

tabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    currentMode = tab.dataset.mode;
    tabs.forEach((button) => {
      const active = button === tab;
      button.classList.toggle("active", active);
      button.classList.toggle("btn-primary", active);
      button.classList.toggle("btn-outline-primary", !active);
      button.setAttribute("aria-selected", String(active));
    });
    jobFields.hidden = currentMode !== "job";
    syncJobFields();
  });
});

resumeFile.addEventListener("change", () => {
  const file = resumeFile.files[0];
  fileLabel.textContent = file ? file.name : "Choose TXT, PDF, DOC, or DOCX";
  if (!file) {
    setPreviewDocument(resumeText.value, "Live preview from resume text");
    return;
  }
  if (file.name.toLowerCase().endsWith(".txt")) {
    file.text().then((text) => {
      const pastedText = resumeText.value.trim();
      if (!pastedText) {
        resumeText.value = text;
        resumeFile.value = "";
        fileLabel.textContent = `${file.name} loaded`;
        showToast("TXT resume loaded into the editor.");
        setPreviewDocument(text, `Previewing exact text from ${file.name}`);
        return;
      }
      setPreviewDocument(analyzedResumeText(text), `Previewing exact analysis input from ${file.name}`);
    });
    return;
  }

  if (!resumeText.value.trim()) {
    renderPreviewWaiting(file.name);
  } else {
    setPreviewDocument(resumeText.value, `Previewing pasted text until ${file.name} is extracted`);
  }
});

printButton.addEventListener("click", async () => {
  if (latestReport?.pdf_url) {
    await openPdfWithAuthPrompt(latestReport.pdf_url);
    return;
  }
  showAlert("No report yet", "Run an analysis first, then Resumo can generate the PDF report.", "info");
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  await analyze();
});

loginButton.addEventListener("click", () => showAuthDialog("login"));
signupButton.addEventListener("click", () => showAuthDialog("signup"));
logoutButton.addEventListener("click", async () => {
  try {
    await postAuth("logout", {});
    setAuthUser(null);
    showToast("Signed out.");
  } catch (error) {
    showAlert("Sign out failed", error.message, "error");
  }
});

copyRecommendedResume.addEventListener("click", async () => {
  if (!latestReport?.recommended_resume) return;
  const text = recommendedResumeToText(latestReport.recommended_resume);
  try {
    await navigator.clipboard.writeText(text);
    showToast("Recommended resume copied.");
  } catch (error) {
    showAlert("Copy unavailable", "Select the recommended resume text and copy it manually.", "info");
  }
});

downloadRecommendedResumePdf.addEventListener("click", async (event) => {
  event.preventDefault();
  const url = downloadRecommendedResumePdf.getAttribute("href");
  if (!url || url === "#") {
    showAlert("No PDF yet", "Run an analysis first, then Resumo can generate a recommended resume PDF.", "info");
    return;
  }
  await openPdfWithAuthPrompt(url);
});

butlerLauncher.addEventListener("click", () => openButler());
butlerClose.addEventListener("click", () => closeButler());
openButlerNav.addEventListener("click", (event) => {
  event.preventDefault();
  openButler(true);
});

butlerActions.forEach((button) => {
  button.addEventListener("click", () => handleButlerQuickAction(button.dataset.butlerAction));
});

butlerForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const question = butlerInput.value.trim();
  if (!question) return;
  addButlerMessage("user", question);
  butlerInput.value = "";
  await askButler(question);
});

resumeText.addEventListener("input", () => {
  setPreviewDocument(resumeText.value, "Live preview from resume text");
});

previewButtons.forEach((button) => {
  button.addEventListener("click", () => {
    previewMode = button.dataset.previewMode;
    previewButtons.forEach((control) => {
      const active = control === button;
      control.classList.toggle("active", active);
      control.classList.toggle("btn-primary", active);
      control.classList.toggle("btn-outline-primary", !active);
      control.setAttribute("aria-selected", String(active));
    });
    updateResumePreview();
  });
});

async function analyze() {
  if (!resumeText.value.trim() && !resumeFile.files[0]) {
    showAlert("Resume required", "Paste resume text or upload a supported TXT, PDF, DOC, or DOCX file.", "warning");
    resumeText.focus();
    return;
  }

  if (currentMode === "job" && !jobDescription.value.trim()) {
    showAlert("Job description required", "Paste the target job description before running Job Match.", "warning");
    jobDescription.focus();
    return;
  }

  const formData = new FormData();
  formData.append("mode", currentMode);
  formData.append("resumeText", resumeText.value);
  if (currentMode === "job") {
    formData.append("jobTitle", jobTitle.value);
    formData.append("jobDescription", jobDescription.value);
  }
  if (resumeFile.files[0]) {
    formData.append("resumeFile", resumeFile.files[0]);
  }

  setBusy(true);
  startProgress(Boolean(resumeFile.files[0]));
  try {
    const data = await postAnalysis(formData);
    setProgressStep("report");
    setProgress(100, "Report ready");
    latestReport = data.analysis;
    renderReport(latestReport);
    if (latestReport.resume_text) {
      setPreviewDocument(latestReport.resume_text, "Previewing exact analyzed resume", latestReport.sections);
    } else if (!resumeText.value.trim() && latestReport.resume_excerpt) {
      setPreviewDocument(latestReport.resume_excerpt, "Previewing extracted resume excerpt", latestReport.sections);
    }
    await finishProgressModal();
    showToast("Analysis complete. Your report is ready.");
  } catch (error) {
    setProgress(progressPercent, "Analysis needs attention");
    renderError(error.message);
    closeProgressModal();
    showAlert("Analysis failed", error.message, "error");
  } finally {
    setBusy(false);
    stopProgress();
  }
}

function syncJobFields() {
  const enabled = currentMode === "job";
  jobTitle.disabled = !enabled;
  jobDescription.disabled = !enabled;
}

function setBusy(isBusy) {
  submitButton.disabled = isBusy;
  submitButton.innerHTML = isBusy
    ? `<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Analyzing...`
    : `<i class="fas fa-magnifying-glass-chart mr-1"></i>Analyze Resume`;
}

function renderReport(analysis) {
  document.getElementById("reportMode").textContent = analysis.mode === "job" ? "Resume + job description matching" : "Resume-only analysis";
  document.getElementById("reportTitle").textContent = analysis.title;
  const aiStatus = analysis.ai_status ? ` ${analysis.ai_status}` : "";
  document.getElementById("reportSubtitle").textContent = `${analysis.subtitle} Engine: ${analysis.engine}.${aiStatus}`;
  document.getElementById("overallScore").textContent = analysis.overall;
  printButton.textContent = "Download PDF";

  const metricGrid = document.getElementById("metricGrid");
  const icons = ["fa-robot", "fa-briefcase", "fa-layer-group"];
  const colors = ["bg-info", "bg-success", "bg-warning"];
  metricGrid.innerHTML = analysis.metrics.map(([label, value, note], index) => `
    <div class="col-md-4">
      <div class="info-box">
        <span class="info-box-icon ${colors[index % colors.length]}"><i class="fas ${icons[index % icons.length]}"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">${escapeHtml(label)}</span>
          <span class="info-box-number">${escapeHtml(value)}</span>
          <span class="info-box-note">${escapeHtml(note)}</span>
        </div>
      </div>
    </div>
  `).join("");

  document.getElementById("breakdownList").innerHTML = Object.entries(analysis.scores).map(([name, score]) => `
    <div class="score-row">
      <span>${escapeHtml(name)}</span>
      <div class="meter"><div style="width:${Number(score)}%"></div></div>
      <strong>${Number(score)}</strong>
    </div>
  `).join("");

  renderList("strengthsList", analysis.strengths);
  renderList("weaknessesList", analysis.weaknesses);
  renderList("recommendationsList", analysis.recommendations);
  renderRecommendedResume(analysis);
  const keywordsPanel = document.getElementById("keywordsPanel");
  keywordsPanel.hidden = analysis.mode !== "job";
  if (analysis.mode === "job") {
    renderList("keywordsList", analysis.keywords?.length ? analysis.keywords : ["No missing keywords detected."], Boolean(analysis.keywords?.length));
  } else {
    renderList("keywordsList", []);
  }
}

function renderRecommendedResume(analysis) {
  const resume = analysis.recommended_resume;
  recommendedResumePanel.hidden = !resume;
  if (!resume) {
    recommendedResume.innerHTML = "";
    return;
  }

  downloadRecommendedResume.href = analysis.recommended_resume_url || "#";
  downloadRecommendedResumePdf.href = analysis.recommended_resume_pdf_url || "#";
  recommendedResume.innerHTML = `
    <header>
      <h3>${escapeHtml(resume.candidate_name || resume.headline || "Recommended Resume Draft")}</h3>
      ${Array.isArray(resume.contact) && resume.contact.length ? `<div class="recommended-contact">${resume.contact.map((item) => `<span>${escapeHtml(item)}</span>`).join("")}</div>` : ""}
      ${resume.target_role ? `<strong>${escapeHtml(resume.target_role)}</strong>` : ""}
      <p>${escapeHtml(resume.summary || "")}</p>
    </header>
    ${(resume.sections || []).map((section) => `
      <section>
        <h4>${escapeHtml(section.heading || "")}</h4>
        <ul>
          ${(section.items || []).map((item) => `<li>${escapeHtml(item)}</li>`).join("")}
        </ul>
      </section>
    `).join("")}
  `;
}

async function openPdfWithAuthPrompt(url) {
  const shouldContinue = await showPdfAuthPrompt();
  if (shouldContinue) {
    window.open(url, "_blank");
  }
}

async function showPdfAuthPrompt() {
  if (authUser) return true;
  if (!window.Swal) return true;

  const result = await Swal.fire({
    title: "Log in or sign up first",
    html: `
      <p class="mb-2">Create an account before downloading PDFs so Resumo can keep your reports, recommended resumes, and job-match history in one place.</p>
      <p class="text-muted mb-0">You can continue without an account for this download.</p>
    `,
    icon: "info",
    showDenyButton: true,
    showCancelButton: true,
    confirmButtonText: "Sign up",
    denyButtonText: "Log in",
    cancelButtonText: "Continue PDF",
    confirmButtonColor: "#0c6b63",
    denyButtonColor: "#63717d",
    background: "#ffffff",
    color: "#18212a"
  });

  if (result.isConfirmed) {
    return Boolean(await showAuthDialog("signup"));
  }

  if (result.isDenied) {
    return Boolean(await showAuthDialog("login"));
  }

  return result.dismiss === Swal.DismissReason.cancel;
}

async function loadAuthState() {
  try {
    const response = await fetch("api/auth.php?action=status", { headers: { "Accept": "application/json" } });
    const data = await response.json().catch(() => ({}));
    if (response.ok) {
      setAuthUser(data.user || null);
    }
  } catch (error) {
    setAuthUser(null);
  }
}

function setAuthUser(user) {
  authUser = user;
  authGuestActions.hidden = Boolean(user);
  authUserActions.hidden = !user;
  accountName.textContent = user?.name || "Account";
  accountEmail.textContent = user?.email || "";
}

async function showAuthDialog(mode = "login") {
  const isSignup = mode === "signup";
  if (!window.Swal) return null;

  const result = await Swal.fire({
    title: isSignup ? "Create your Resumo account" : "Log in to Resumo",
    html: authFormMarkup(isSignup),
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: isSignup ? "Sign up" : "Log in",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#0c6b63",
    background: "#ffffff",
    color: "#18212a",
    showLoaderOnConfirm: true,
    preConfirm: async () => {
      const popup = Swal.getPopup();
      const payload = {
        email: popup.querySelector("[data-auth-email]")?.value.trim() || "",
        password: popup.querySelector("[data-auth-password]")?.value || "",
      };
      if (isSignup) {
        payload.name = popup.querySelector("[data-auth-name]")?.value.trim() || "";
      }

      try {
        const data = await postAuth(isSignup ? "signup" : "login", payload);
        setAuthUser(data.user || null);
        return data.user;
      } catch (error) {
        Swal.showValidationMessage(error.message);
        return false;
      }
    },
    allowOutsideClick: () => !Swal.isLoading()
  });

  if (result.isConfirmed && result.value) {
    showToast(isSignup ? "Account created. You are signed in." : "Welcome back.");
    return result.value;
  }

  return null;
}

function authFormMarkup(isSignup) {
  return `
    <div class="auth-modal-form">
      ${isSignup ? `
        <label>
          <span>Name</span>
          <input class="swal2-input" data-auth-name type="text" autocomplete="name" placeholder="Jane Applicant">
        </label>
      ` : ""}
      <label>
        <span>Email</span>
        <input class="swal2-input" data-auth-email type="email" autocomplete="email" placeholder="you@example.com">
      </label>
      <label>
        <span>Password</span>
        <input class="swal2-input" data-auth-password type="password" autocomplete="${isSignup ? "new-password" : "current-password"}" placeholder="At least 8 characters">
      </label>
      <p>${isSignup ? "Your account keeps future reports connected to your email." : "Use the email and password you used when signing up."}</p>
    </div>
  `;
}

async function postAuth(action, payload) {
  const response = await fetch(`api/auth.php?action=${encodeURIComponent(action)}`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || "Auth request failed.");
  }
  return data;
}

function recommendedResumeToText(resume) {
  const lines = [
    String(resume.candidate_name || resume.headline || "Recommended Resume Draft").toUpperCase()
  ];

  if (Array.isArray(resume.contact) && resume.contact.length) {
    lines.push(resume.contact.join(" | "));
  }
  if (resume.target_role) {
    lines.push(String(resume.target_role));
  }
  lines.push("", String(resume.summary || ""), "");

  (resume.sections || []).forEach((section) => {
    lines.push(String(section.heading || "").toUpperCase());
    (section.items || []).forEach((item) => lines.push(`- ${item}`));
    lines.push("");
  });

  return lines.join("\n").trim();
}

function openButler(focusInput = false) {
  butlerPanel.hidden = false;
  butlerLauncher.classList.add("is-open");
  if (!butlerMessages.childElementCount) {
    addButlerMessage("assistant", "Hi, I am Resumo Butler. I can walk you through the dashboard, explain scores, help with job matching, and point you to the right download flow.");
  }
  if (focusInput) {
    window.setTimeout(() => butlerInput.focus(), 80);
  }
}

function closeButler() {
  butlerPanel.hidden = true;
  butlerLauncher.classList.remove("is-open");
}

function addButlerMessage(role, text) {
  const message = document.createElement("div");
  message.className = `butler-message ${role}`;
  message.innerHTML = `<p>${escapeHtml(text)}</p>`;
  butlerMessages.appendChild(message);
  butlerMessages.scrollTop = butlerMessages.scrollHeight;
  return message;
}

function handleButlerQuickAction(action) {
  openButler();
  const prompts = {
    tutorial: "Start the tutorial.",
    resume: "How do I improve my resume score?",
    job: "How does Job Match work?",
    pdf: "How do PDF downloads work?"
  };
  const prompt = prompts[action] || "Help me use Resumo.";
  addButlerMessage("user", prompt);
  if (action === "tutorial") {
    askButler(prompt).then(() => startButlerTutorial());
    return;
  }
  askButler(prompt);
}

async function startButlerTutorial() {
  addButlerMessage("assistant", "I will guide you through the main Resumo workflow: add a resume, choose a mode, review the preview, run analysis, inspect the report, then download only after the account prompt.");
  const steps = [
    { selector: "#resumeForm", title: "Add resume content", text: "Paste resume text or upload TXT, PDF, DOC, or DOCX. TXT files can preview immediately; PDF and DOCX preview after extraction." },
    { selector: ".mode-tabs", title: "Choose analysis mode", text: "Use Resume Score for a general review, or Job Match when you have a specific role and job description." },
    { selector: "#resumePreviewPanel", title: "Check the preview", text: "The preview shows word count, detected sections, contact signals, bullets, metrics, and line-length warnings before scoring." },
    { selector: "#report", title: "Read the report", text: "After analysis, Resumo shows the overall score, score breakdown, strengths, weaknesses, recommendations, and missing keywords for job mode." },
    { selector: "#recommendedResumePanel", title: "Use the recommended draft", text: "When available, copy the ATS-friendly draft or download TXT/PDF. PDF downloads will prompt users to log in or sign up first." }
  ];

  for (const step of steps) {
    const target = document.querySelector(step.selector);
    target?.scrollIntoView({ behavior: "smooth", block: "center" });
    await showButlerStep(step.title, step.text);
  }
}

function showButlerStep(title, text) {
  if (!window.Swal) {
    addButlerMessage("assistant", `${title}: ${text}`);
    return Promise.resolve();
  }
  return Swal.fire({
    title,
    text,
    icon: "info",
    confirmButtonText: "Next",
    confirmButtonColor: "#0c6b63",
    background: "#ffffff",
    color: "#18212a"
  });
}

async function askButler(message) {
  const pending = addButlerMessage("assistant", "Thinking with Groq...");
  try {
    const response = await fetch("api/butler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        message,
        context: butlerContext()
      })
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || "Resumo Butler could not reach Groq.");
    }
    pending.querySelector("p").textContent = data.butler?.reply || "Groq returned an empty Butler reply.";
  } catch (error) {
    pending.querySelector("p").textContent = `${error.message} Check GROQ_ENABLED and GROQ_API_KEY in .env, then try again.`;
  }
  butlerMessages.scrollTop = butlerMessages.scrollHeight;
}

function butlerContext() {
  return {
    mode: currentMode,
    has_resume_text: Boolean(resumeText.value.trim()),
    has_resume_file: Boolean(resumeFile.files[0]),
    has_job_description: Boolean(jobDescription.value.trim()),
    has_report: Boolean(latestReport),
    has_recommended_resume: Boolean(latestReport?.recommended_resume),
    overall_score: latestReport?.overall ?? null,
    authenticated: Boolean(authUser)
  };
}

function setPreviewDocument(sourceText, sourceLabel = "Live preview from resume text", sections = null) {
  activePreviewText = String(sourceText ?? "");
  activePreviewLabel = sourceLabel;
  activePreviewSections = sections;
  updateResumePreview();
}

function updateResumePreview() {
  const text = String(activePreviewText ?? "");
  const trimmedText = text.trim();
  previewSource.textContent = trimmedText ? activePreviewLabel : "Live preview from resume text";
  updatePreviewStats(text, activePreviewSections);

  if (!trimmedText) {
    resumePreview.classList.remove("raw-mode");
    resumePreview.innerHTML = `
      <div class="preview-empty">
        <i class="fas fa-file-circle-plus" aria-hidden="true"></i>
        <h3>Resume preview will appear here</h3>
        <p>Paste resume text or load a TXT file to preview the document before scoring.</p>
      </div>
    `;
    return;
  }

  resumePreview.classList.toggle("raw-mode", previewMode === "raw");
  resumePreview.innerHTML = previewMode === "raw" ? renderRawPreview(text) : renderFormattedPreview(text);
}

function renderPreviewWaiting(fileName) {
  previewSource.textContent = `${fileName} selected`;
  activePreviewText = "";
  activePreviewLabel = `${fileName} selected`;
  activePreviewSections = null;
  updatePreviewStats("");
  resumePreview.classList.remove("raw-mode");
  resumePreview.innerHTML = `
    <div class="preview-empty">
      <i class="fas fa-file-import" aria-hidden="true"></i>
      <h3>Preview after extraction</h3>
      <p>Run analysis to extract this file and show the exact analyzed text here.</p>
    </div>
  `;
}

function updatePreviewStats(text, sectionsOverride = null) {
  const words = text ? (text.match(/\b[\p{L}\p{N}_]+\b/gu) || []).length : 0;
  const lines = text ? text.split(/\r?\n/).filter((line) => line.trim()).length : 0;
  const parsed = parseResumePreview(text);
  const sections = sectionsOverride ? sectionNamesFromAnalysis(sectionsOverride) : parsed.sections.map((section) => section.key);
  const quality = previewQuality(text, parsed, words);

  previewWords.textContent = String(words);
  previewLines.textContent = String(lines);
  previewSectionsCount.textContent = String(sections.length);
  previewFitScore.textContent = words ? `${quality.score}` : "--";
  previewSectionChips.innerHTML = sections.length
    ? sections.map((section) => `<span>${escapeHtml(toTitle(section))}</span>`).join("")
    : `<span class="muted-chip">No sections yet</span>`;
  previewOutline.innerHTML = parsed.sections.length
    ? parsed.sections.map((section) => `<li><button type="button" data-preview-jump="${escapeHtml(section.id)}">${escapeHtml(section.title)}</button></li>`).join("")
    : `<li class="preview-muted">No outline yet</li>`;
  previewSignals.innerHTML = quality.signals.map((signal) => `
    <li class="${signal.state}">
      <i class="fas ${signal.icon}" aria-hidden="true"></i>
      <span>${escapeHtml(signal.text)}</span>
    </li>
  `).join("");

  previewOutline.querySelectorAll("[data-preview-jump]").forEach((button) => {
    button.addEventListener("click", () => {
      const target = document.getElementById(button.dataset.previewJump);
      target?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
}

function renderRawPreview(text) {
  return `<pre>${escapeHtml(text)}</pre>`;
}

function renderFormattedPreview(text) {
  const parsed = parseResumePreview(text);
  const contact = parsed.contact.length
    ? `<div>${parsed.contact.map((item) => `<span>${escapeHtml(item)}</span>`).join("")}</div>`
    : "";
  const header = parsed.name || parsed.contact.length
    ? `<header class="preview-resume-header"><h3>${escapeHtml(parsed.name || "Resume Candidate")}</h3>${contact}</header>`
    : "";
  const body = parsed.sections.length
    ? parsed.sections.map((section) => `
      <section class="preview-resume-section" id="${escapeHtml(section.id)}">
        <h4>${escapeHtml(section.title)}</h4>
        ${section.items.map((item) => renderPreviewItem(item)).join("")}
      </section>
    `).join("")
    : text.split(/\r?\n/).map((line) => renderPreviewLine(line)).join("");

  return `
    ${header}
    <div class="preview-resume-body">${body || `<p class="preview-muted">Add more resume content to build the preview.</p>`}</div>
  `;
}

function renderPreviewItem(item) {
  const value = String(item ?? "").trim();
  if (!value) return "";
  if (/^[-*•]\s+/.test(value)) {
    return `<p class="preview-bullet"><span></span><b>${escapeHtml(value.replace(/^[-*•]\s+/, ""))}</b></p>`;
  }
  return `<p>${escapeHtml(value)}</p>`;
}

function renderPreviewLine(line) {
  const exactLine = String(line);
  const trimmed = exactLine.trim();
  if (!trimmed) {
    return `<div class="preview-space"></div>`;
  }

  if (isSectionHeading(trimmed)) {
    return `<h4>${escapeHtml(exactLine)}</h4>`;
  }

  return `<p>${escapeHtml(exactLine)}</p>`;
}

function detectSections(text) {
  if (!text.trim()) return [];
  return Object.entries(sectionPatterns)
    .filter(([, pattern]) => pattern.test(text))
    .map(([name]) => name);
}

function parseResumePreview(text) {
  const lines = String(text ?? "").split(/\r?\n/);
  const nonEmpty = lines.map((line) => line.trim()).filter(Boolean);
  const contact = extractContact(text);
  const name = inferPreviewName(nonEmpty, contact);
  const sections = [];
  let current = null;

  nonEmpty.forEach((line, index) => {
    const headingKey = sectionKeyFromHeading(line);
    if (headingKey && line.length <= 48) {
      current = {
        id: `preview-section-${sections.length + 1}`,
        key: headingKey,
        title: toTitle(headingKey),
        items: []
      };
      sections.push(current);
      return;
    }

    if (index <= 2 && (line === name || contact.includes(line))) {
      return;
    }

    if (!current) {
      current = {
        id: "preview-section-1",
        key: "summary",
        title: "Profile",
        items: []
      };
      sections.push(current);
    }
    current.items.push(line);
  });

  return {
    name,
    contact,
    sections: sections.filter((section) => section.items.length || section.key !== "summary")
  };
}

function extractContact(text) {
  const items = [];
  const email = text.match(/\b[\w.%+-]+@[\w.-]+\.[a-z]{2,}\b/i)?.[0];
  const phone = text.match(/\+?\d[\d\s().-]{7,}/)?.[0];
  const linkedin = text.match(/linkedin\.com\/in\/[^\s|,;]+/i)?.[0];
  if (email) items.push(email);
  if (phone) items.push(phone.trim());
  if (linkedin) items.push(linkedin);
  return items;
}

function inferPreviewName(lines, contact) {
  const contactText = contact.join(" ").toLowerCase();
  const candidate = lines.find((line) => {
    const normalized = line.toLowerCase();
    return line.length <= 46
      && !contactText.includes(normalized)
      && !sectionKeyFromHeading(line)
      && !/[|@]|\d{3,}/.test(line);
  });
  return candidate || "";
}

function sectionKeyFromHeading(line) {
  const normalized = line.replace(/:$/, "").trim();
  if (normalized.length > 48) return null;
  const found = Object.entries(sectionPatterns).find(([, pattern]) => pattern.test(normalized));
  return found?.[0] || null;
}

function previewQuality(text, parsed, words) {
  if (!String(text ?? "").trim()) {
    return {
      score: 0,
      signals: [{ state: "muted", icon: "fa-circle-info", text: "Paste or upload a resume to inspect it." }]
    };
  }

  const bullets = (text.match(/(^|\n)\s*[-*•]/g) || []).length;
  const metrics = (text.match(/\b\d+[%+]?|\$[\d,.]+/g) || []).length;
  const longLines = String(text).split(/\r?\n/).filter((line) => line.trim().length > 140).length;
  const hasEmail = /\b[\w.%+-]+@[\w.-]+\.[a-z]{2,}\b/i.test(text);
  const hasPhone = /\+?\d[\d\s().-]{7,}/.test(text);
  const hasLinkedIn = /linkedin\.com\/in\//i.test(text);
  const sectionCount = parsed.sections.length;
  const score = Math.max(0, Math.min(100, Math.round(
    18
    + Math.min(sectionCount, 7) * 7
    + Math.min(bullets, 10) * 2
    + Math.min(metrics, 6) * 4
    + (hasEmail ? 8 : 0)
    + (hasPhone ? 6 : 0)
    + (hasLinkedIn ? 5 : 0)
    + (words >= 320 && words <= 900 ? 12 : 0)
    - Math.min(longLines * 4, 16)
  )));

  const signals = [
    signal(hasEmail && hasPhone, "fa-address-card", "Contact details are easy to find.", "Add email and phone near the top."),
    signal(sectionCount >= 5, "fa-list-check", `${sectionCount} resume sections detected.`, "Add standard sections like Summary, Experience, Skills, and Education."),
    signal(bullets >= 5, "fa-align-left", `${bullets} bullet points detected.`, "Use bullets for faster recruiter scanning."),
    signal(metrics >= 3, "fa-chart-simple", `${metrics} measurable details found.`, "Add more numbers for impact, scale, or time saved."),
    signal(longLines === 0, "fa-ruler-horizontal", "Line lengths look ATS-friendly.", `${longLines} long line${longLines === 1 ? "" : "s"} may be hard to scan.`),
  ];

  return { score, signals };
}

function signal(ok, icon, goodText, fixText) {
  return {
    state: ok ? "good" : "warn",
    icon: ok ? icon : "fa-triangle-exclamation",
    text: ok ? goodText : fixText
  };
}

function sectionNamesFromAnalysis(sections) {
  if (Array.isArray(sections)) {
    return sections;
  }
  return Object.entries(sections)
    .filter(([, detected]) => Boolean(detected))
    .map(([name]) => name);
}

function analyzedResumeText(fileText = "") {
  return [fileText, resumeText.value]
    .map((value) => String(value ?? "").trim())
    .filter(Boolean)
    .join("\n\n");
}

function isSectionHeading(line) {
  const normalized = line.replace(/:$/, "");
  if (normalized.length > 38) return false;
  return Boolean(sectionKeyFromHeading(normalized));
}

function toTitle(value) {
  return value.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function renderList(id, items, asTags = false) {
  const list = document.getElementById(id);
  list.className = asTags ? "tag-list" : "";
  list.innerHTML = items.map((item) => `<li>${escapeHtml(item)}</li>`).join("");
}

function renderError(message) {
  document.getElementById("reportTitle").textContent = "Analysis needs attention";
  document.getElementById("reportSubtitle").textContent = message;
  document.getElementById("overallScore").textContent = "--";
  recommendedResumePanel.hidden = true;
}

function postAnalysis(formData) {
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open("POST", "api/analyze.php");
    request.responseType = "text";

    request.upload.addEventListener("progress", (event) => {
      if (!event.lengthComputable) return;
      const uploaded = event.loaded / event.total;
      setProgressStep("upload");
      setProgress(10 + Math.round(uploaded * 18), "Uploading resume");
    });

    request.addEventListener("load", () => {
      let data = {};
      try {
        data = request.responseText ? JSON.parse(request.responseText) : {};
      } catch (error) {
        reject(new Error("Analysis returned an unreadable response. Check the server error log for details."));
        return;
      }

      if (request.status >= 200 && request.status < 300) {
        if (!data.analysis || typeof data.analysis !== "object") {
          reject(new Error(data.error || "Analysis completed without a report payload."));
          return;
        }
        resolve(data);
        return;
      }
      reject(new Error(data.error || "Analysis failed."));
    });

    request.addEventListener("error", () => {
      reject(new Error("Unable to reach the analysis service."));
    });

    request.send(formData);
  });
}

function startProgress(hasFile) {
  stopProgress();
  activeStepIndex = 0;
  progressPercent = 0;
  processSteps.find((step) => step.id === "upload").label = hasFile ? "Uploading resume" : "No file upload";
  openProgressModal();
  setProgress(4, "Preparing resume inputs");

  const schedule = hasFile ? processSteps : processSteps.filter((step) => step.id !== "upload");
  let scheduleIndex = 0;

  progressTimer = window.setInterval(() => {
    const step = schedule[Math.min(scheduleIndex, schedule.length - 1)];
    setProgressStep(step.id);

    const ceiling = Math.min(step.target, 94);
    if (progressPercent < ceiling) {
      setProgress(progressPercent + Math.max(1, Math.round((ceiling - progressPercent) * 0.22)), step.label);
      return;
    }

    if (scheduleIndex < schedule.length - 1) {
      scheduleIndex += 1;
    }
  }, 650);
}

function stopProgress() {
  if (progressTimer) {
    window.clearInterval(progressTimer);
    progressTimer = null;
  }
}

function finishProgressModal() {
  return new Promise((resolve) => {
    window.setTimeout(() => {
      closeProgressModal();
      resolve();
    }, 450);
  });
}

function closeProgressModal() {
  if (window.Swal && Swal.isVisible()) {
    Swal.close();
  }
}

function setProgress(percent, label) {
  progressPercent = Math.max(0, Math.min(100, percent));
  updateProgressModal(label);
}

function setProgressStep(stepId) {
  const nextIndex = processSteps.findIndex((step) => step.id === stepId);
  if (nextIndex === -1) return;
  activeStepIndex = nextIndex;
  updateProgressModal(processSteps[activeStepIndex].label);
}

function openProgressModal() {
  if (!window.Swal) return;
  Swal.fire({
    title: "Analyzing resume",
    html: progressMarkup("Preparing resume inputs"),
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      updateProgressModal("Preparing resume inputs");
    }
  });
}

function updateProgressModal(label) {
  const container = document.querySelector(".swal-progress");
  if (!container) return;

  const roundedProgress = Math.round(progressPercent);
  const labelNode = container.querySelector("[data-progress-label]");
  const valueNode = container.querySelector("[data-progress-value]");
  const barNode = container.querySelector("[data-progress-bar]");
  const listNode = container.querySelector("[data-process-list]");

  labelNode.textContent = label;
  valueNode.textContent = `${roundedProgress}%`;
  barNode.style.width = `${roundedProgress}%`;
  barNode.setAttribute("aria-valuenow", String(roundedProgress));
  listNode.innerHTML = processSteps.map((step, index) => {
    const state = progressPercent === 100 || index < activeStepIndex ? "done" : (index === activeStepIndex ? "active" : "");
    return `<li class="${state}"><span></span>${escapeHtml(step.label)}</li>`;
  }).join("");
}

function progressMarkup(label) {
  return `
    <div class="swal-progress">
      <div class="swal-progress-value">
        <span data-progress-label>${escapeHtml(label)}</span>
        <strong data-progress-value>0%</strong>
      </div>
      <div class="swal-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="swal-progress-bar" data-progress-bar></div>
      </div>
      <ol class="swal-process-list" data-process-list></ol>
    </div>
  `;
}

function showToast(message) {
  if (!window.Swal) return;
  Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true
  }).fire({
    icon: "success",
    title: message
  });
}

function showAlert(title, message, icon = "info") {
  if (!window.Swal) return;
  Swal.fire({
    title,
    text: message,
    icon,
    confirmButtonColor: "#0c6b63",
    background: "#ffffff",
    color: "#18212a"
  });
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

syncJobFields();
loadAuthState();
setProgress(0, "Ready");
setPreviewDocument(resumeText.value, "Live preview from resume text");
